package obs

import (
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"
	"time"
)

func TestParseDSN(t *testing.T) {
	tests := []struct {
		name     string
		dsn      string
		wantKey  string
		wantHost string
		wantErr  bool
	}{
		{
			name:     "valid DSN",
			dsn:      "http://mykey@localhost:8000",
			wantKey:  "mykey",
			wantHost: "http://localhost:8000",
		},
		{
			name:     "valid HTTPS DSN",
			dsn:      "https://secret@example.com",
			wantKey:  "secret",
			wantHost: "https://example.com",
		},
		{
			name:    "missing key",
			dsn:     "http://localhost:8000",
			wantErr: true,
		},
		{
			name:    "empty key",
			dsn:     "http://@localhost:8000",
			wantErr: true,
		},
		{
			name:    "invalid URL",
			dsn:     "://bad",
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			endpoint, key, err := parseDSN(tt.dsn)
			if tt.wantErr {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				return
			}
			if err != nil {
				t.Fatalf("unexpected error: %v", err)
			}
			if key != tt.wantKey {
				t.Errorf("key = %q, want %q", key, tt.wantKey)
			}
			if endpoint != tt.wantHost {
				t.Errorf("endpoint = %q, want %q", endpoint, tt.wantHost)
			}
		})
	}
}

func TestCaptureStack(t *testing.T) {
	stack := captureStack(1)
	if len(stack) == 0 {
		t.Fatal("expected non-empty stack")
	}
	found := false
	for _, f := range stack {
		if f.Function == "github.com/alexis-bouchez/obs/sdk/go.TestCaptureStack" {
			found = true
			break
		}
	}
	if !found {
		t.Error("expected to find TestCaptureStack in stack frames")
	}
}

func TestGenerateEventID(t *testing.T) {
	id := generateEventID()
	if len(id) != 36 {
		t.Errorf("expected UUID length 36, got %d: %s", len(id), id)
	}

	// Check uniqueness
	seen := make(map[string]bool)
	for i := 0; i < 100; i++ {
		id := generateEventID()
		if seen[id] {
			t.Fatalf("duplicate ID generated: %s", id)
		}
		seen[id] = true
		// Small sleep to ensure different nanosecond timestamps
		time.Sleep(time.Microsecond)
	}
}

func TestCaptureException(t *testing.T) {
	var mu sync.Mutex
	var received []Event

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != "POST" || r.URL.Path != "/api/events" {
			t.Errorf("unexpected request: %s %s", r.Method, r.URL.Path)
			w.WriteHeader(404)
			return
		}

		if r.Header.Get("X-OBS-Key") != "testkey" {
			t.Error("missing or wrong API key")
			w.WriteHeader(401)
			return
		}

		body, _ := io.ReadAll(r.Body)
		var event Event
		if err := json.Unmarshal(body, &event); err != nil {
			t.Errorf("invalid JSON: %v", err)
			w.WriteHeader(400)
			return
		}

		mu.Lock()
		received = append(received, event)
		mu.Unlock()

		w.WriteHeader(201)
		w.Write([]byte(`{"id":"` + event.EventID + `"}`))
	}))
	defer server.Close()

	// Reset global client
	globalMu.Lock()
	globalClient = nil
	globalMu.Unlock()

	Init(Options{
		DSN:         "http://testkey@" + server.Listener.Addr().String(),
		Environment: "test",
		ServerName:  "test-host",
	})

	id := CaptureException(errors.New("something broke"))
	if id == "" {
		t.Fatal("expected non-empty event ID")
	}

	ok := Flush(5 * time.Second)
	if !ok {
		t.Fatal("flush timed out")
	}

	mu.Lock()
	defer mu.Unlock()
	if len(received) != 1 {
		t.Fatalf("expected 1 event, got %d", len(received))
	}

	event := received[0]
	if event.Message != "something broke" {
		t.Errorf("message = %q, want %q", event.Message, "something broke")
	}
	if event.Level != "error" {
		t.Errorf("level = %q, want %q", event.Level, "error")
	}
	if event.Platform != "go" {
		t.Errorf("platform = %q, want %q", event.Platform, "go")
	}
	if event.Environment != "test" {
		t.Errorf("environment = %q, want %q", event.Environment, "test")
	}
	if event.ServerName != "test-host" {
		t.Errorf("server_name = %q, want %q", event.ServerName, "test-host")
	}
	if len(event.Stacktrace) == 0 {
		t.Error("expected non-empty stacktrace")
	}
}

func TestCaptureMessage(t *testing.T) {
	var mu sync.Mutex
	var received []Event

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		body, _ := io.ReadAll(r.Body)
		var event Event
		json.Unmarshal(body, &event)
		mu.Lock()
		received = append(received, event)
		mu.Unlock()
		w.WriteHeader(201)
	}))
	defer server.Close()

	globalMu.Lock()
	globalClient = nil
	globalMu.Unlock()

	Init(Options{
		DSN: "http://testkey@" + server.Listener.Addr().String(),
	})

	CaptureMessage("deployment started")
	Flush(5 * time.Second)

	mu.Lock()
	defer mu.Unlock()
	if len(received) != 1 {
		t.Fatalf("expected 1 event, got %d", len(received))
	}
	if received[0].Level != "info" {
		t.Errorf("level = %q, want %q", received[0].Level, "info")
	}
	if received[0].Message != "deployment started" {
		t.Errorf("message = %q, want %q", received[0].Message, "deployment started")
	}
}

func TestCaptureNilError(t *testing.T) {
	id := CaptureException(nil)
	if id != "" {
		t.Errorf("expected empty ID for nil error, got %q", id)
	}
}

