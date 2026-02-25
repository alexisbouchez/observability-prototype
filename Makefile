PORT      := 8000
API_KEY   := test123
SERVER_URL := http://localhost:$(PORT)
PHP_PID   := /tmp/obs-server.pid

.PHONY: help server stop test test-go test-php test-js demo demo-go demo-php demo-js clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

# --- Server ---

server: ## Start the PHP server
	@rm -f server/obs.sqlite
	@echo "Starting obs server on $(SERVER_URL) ..."
	@OBS_API_KEY=$(API_KEY) php -S localhost:$(PORT) server/index.php & echo $$! > $(PHP_PID)
	@sleep 1
	@echo "Server running (PID $$(cat $(PHP_PID)))"

stop: ## Stop the PHP server
	@if [ -f $(PHP_PID) ]; then \
		kill $$(cat $(PHP_PID)) 2>/dev/null || true; \
		rm -f $(PHP_PID); \
		echo "Server stopped"; \
	else \
		echo "No server running"; \
	fi

# --- Tests ---

test: test-go test-php test-js ## Run all SDK tests

test-go: ## Run Go SDK tests
	@echo "=== Go SDK tests ==="
	@cd sdk/go && go test -v ./...

test-php: ## Run PHP SDK tests
	@echo "=== PHP SDK tests ==="
	@cd sdk/php && vendor/bin/phpunit tests/

test-js: ## Run JS SDK tests
	@echo "=== JS SDK tests ==="
	@cd sdk/js && bun test

# --- Demos ---

demo: server demo-go demo-php demo-js ## Run full demo (server + all SDKs + dashboard check)
	@echo ""
	@echo "=== Dashboard events ==="
	@curl -s $(SERVER_URL)/ | grep -oP '(?<=event-message">)[^<]+'
	@echo ""
	@echo "Open $(SERVER_URL) to view the dashboard."
	@echo "Run 'make stop' to shut down the server."

demo-go: ## Run Go example (requires server)
	@echo ""
	@echo "=== Go example ==="
	@cd example && go run main.go

demo-php: ## Run PHP example (requires server)
	@echo ""
	@echo "=== PHP example ==="
	@php example/php/main.php

demo-js: ## Run JS example (requires server)
	@echo ""
	@echo "=== JS example ==="
	@bun example/js/main.ts

# --- Cleanup ---

clean: stop ## Stop server and remove generated files
	@rm -f server/obs.sqlite
	@echo "Cleaned"
