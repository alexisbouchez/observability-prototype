// Package obs provides error tracking for Go applications.
package obs

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"os"
	"runtime"
	"sync"
	"time"
)

// Options configures the obs SDK.
type Options struct {
	DSN         string // http://key@host:port
	Environment string
	ServerName  string
}

type client struct {
	endpoint   string
	apiKey     string
	env        string
	serverName string
	events     chan *Event
	quit       chan struct{}
	done       chan struct{}
	mu         sync.Mutex
}

// Event represents an error event sent to the server.
type Event struct {
	EventID    string            `json:"event_id"`
	Level      string            `json:"level"`
	Message    string            `json:"message"`
	Stacktrace []StackFrame     `json:"stacktrace,omitempty"`
	Platform   string            `json:"platform"`
	Timestamp  string            `json:"timestamp"`
	ServerName string            `json:"server_name,omitempty"`
	Environment string           `json:"environment,omitempty"`
	Extra      map[string]string `json:"extra,omitempty"`
}

// StackFrame represents a single frame in a stacktrace.
type StackFrame struct {
	Filename string `json:"filename"`
	Function string `json:"function"`
	Lineno   int    `json:"lineno"`
}

var (
	globalClient *client
	globalMu     sync.RWMutex
)

// Init initializes the obs SDK with the given options.
func Init(options Options) {
	endpoint, apiKey, err := parseDSN(options.DSN)
	if err != nil {
		fmt.Fprintf(os.Stderr, "obs: invalid DSN: %v\n", err)
		return
	}

	serverName := options.ServerName
	if serverName == "" {
		serverName, _ = os.Hostname()
	}

	c := &client{
		endpoint:   endpoint,
		apiKey:     apiKey,
		env:        options.Environment,
		serverName: serverName,
		events:     make(chan *Event, 256),
		quit:       make(chan struct{}),
		done:       make(chan struct{}),
	}

	go c.worker()

	globalMu.Lock()
	globalClient = c
	globalMu.Unlock()
}

// CaptureException captures an error and sends it to the server.
// Returns the event ID.
func CaptureException(err error) string {
	if err == nil {
		return ""
	}
	return captureEvent("error", err.Error(), captureStack(3))
}

// CaptureMessage captures a message and sends it to the server.
// Returns the event ID.
func CaptureMessage(msg string) string {
	return captureEvent("info", msg, nil)
}

// Recover recovers from a panic and captures it as an error event.
// Usage: defer obs.Recover()
func Recover() {
	if r := recover(); r != nil {
		msg := fmt.Sprintf("%v", r)
		captureEvent("error", msg, captureStack(4))
	}
}

// Flush waits for all pending events to be sent, up to the given timeout.
// Returns true if all events were flushed.
func Flush(timeout time.Duration) bool {
	globalMu.RLock()
	c := globalClient
	globalMu.RUnlock()

	if c == nil {
		return true
	}

	close(c.quit)

	select {
	case <-c.done:
		return true
	case <-time.After(timeout):
		return false
	}
}

func captureEvent(level, message string, stack []StackFrame) string {
	globalMu.RLock()
	c := globalClient
	globalMu.RUnlock()

	if c == nil {
		return ""
	}

	id := generateEventID()

	event := &Event{
		EventID:     id,
		Level:       level,
		Message:     message,
		Stacktrace:  stack,
		Platform:    "go",
		Timestamp:   time.Now().UTC().Format("2006-01-02 15:04:05"),
		ServerName:  c.serverName,
		Environment: c.env,
	}

	select {
	case c.events <- event:
	default:
		// Drop event if buffer is full
	}

	return id
}

func captureStack(skip int) []StackFrame {
	var pcs [50]uintptr
	n := runtime.Callers(skip, pcs[:])
	frames := runtime.CallersFrames(pcs[:n])

	var stack []StackFrame
	for {
		frame, more := frames.Next()
		stack = append(stack, StackFrame{
			Filename: frame.File,
			Function: frame.Function,
			Lineno:   frame.Line,
		})
		if !more {
			break
		}
	}
	return stack
}

func (c *client) worker() {
	defer close(c.done)
	for {
		select {
		case event := <-c.events:
			c.send(event)
		case <-c.quit:
			// Drain remaining events
			for {
				select {
				case event := <-c.events:
					c.send(event)
				default:
					return
				}
			}
		}
	}
}

func (c *client) send(event *Event) {
	body, err := json.Marshal(event)
	if err != nil {
		return
	}

	req, err := http.NewRequest("POST", c.endpoint+"/api/events", bytes.NewReader(body))
	if err != nil {
		return
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-OBS-Key", c.apiKey)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		fmt.Fprintf(os.Stderr, "obs: failed to send event: %v\n", err)
		return
	}
	resp.Body.Close()
}

func parseDSN(dsn string) (endpoint, apiKey string, err error) {
	u, err := url.Parse(dsn)
	if err != nil {
		return "", "", fmt.Errorf("failed to parse DSN: %w", err)
	}

	if u.User == nil {
		return "", "", fmt.Errorf("DSN must contain an API key as username")
	}

	apiKey = u.User.Username()
	if apiKey == "" {
		return "", "", fmt.Errorf("DSN must contain a non-empty API key")
	}

	endpoint = fmt.Sprintf("%s://%s", u.Scheme, u.Host)
	return endpoint, apiKey, nil
}

func generateEventID() string {
	b := make([]byte, 16)
	// Use time-based prefix for rough ordering
	now := time.Now().UnixNano()
	b[0] = byte(now >> 56)
	b[1] = byte(now >> 48)
	b[2] = byte(now >> 40)
	b[3] = byte(now >> 32)
	b[4] = byte(now >> 24)
	b[5] = byte(now >> 16)

	// Fill rest with random-ish data from runtime
	var buf [8]byte
	for i := range buf {
		buf[i] = byte(time.Now().UnixNano() >> (i * 8))
	}
	copy(b[6:], buf[:])

	// Format as UUID
	b[6] = (b[6] & 0x0f) | 0x40
	b[8] = (b[8] & 0x3f) | 0x80
	return fmt.Sprintf("%x-%x-%x-%x-%x", b[0:4], b[4:6], b[6:8], b[8:10], b[10:16])
}
