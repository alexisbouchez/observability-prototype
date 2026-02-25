<?php

require_once __DIR__ . '/../../sdk/php/src/Client.php';

\Obs\Client::init([
    'dsn' => 'http://test123@localhost:8000',
    'environment' => 'development',
    'server_name' => 'example-app',
]);

// Capture a simple message
$id = \Obs\Client::captureMessage('Application started');
echo "Sent message event: $id\n";

// Capture an exception
try {
    throw new \RuntimeException('database connection failed: timeout after 30s');
} catch (\Throwable $e) {
    $id = \Obs\Client::captureException($e);
    echo "Sent error event: $id\n";
}

// Capture a warning
$id = \Obs\Client::captureMessage('Disk usage above 80%', 'warning');
echo "Sent warning event: $id\n";

// Flush happens automatically on shutdown
echo "Events will flush on shutdown...\n";
