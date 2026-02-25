package main

import (
	"errors"
	"fmt"
	"time"

	obs "github.com/alexis-bouchez/obs/sdk/go"
)

func main() {
	obs.Init(obs.Options{
		DSN:         "http://test123@localhost:8000",
		Environment: "development",
		ServerName:  "example-app",
	})

	// Capture a simple message
	id := obs.CaptureMessage("Application started")
	fmt.Printf("Sent message event: %s\n", id)

	// Capture an error
	err := doSomethingRisky()
	if err != nil {
		id = obs.CaptureException(err)
		fmt.Printf("Sent error event: %s\n", id)
	}

	// Capture a panic via Recover
	func() {
		defer obs.Recover()
		panic("unexpected failure in goroutine")
	}()

	// Flush before exit
	if obs.Flush(5 * time.Second) {
		fmt.Println("All events flushed successfully")
	} else {
		fmt.Println("Timed out flushing events")
	}
}

func doSomethingRisky() error {
	return errors.New("database connection failed: timeout after 30s")
}
