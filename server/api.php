<?php

require_once __DIR__ . '/db.php';

function handleEventIngestion(): void {
    $apiKey = getenv('OBS_API_KEY');
    if (!$apiKey) {
        http_response_code(500);
        echo json_encode(['error' => 'OBS_API_KEY not configured']);
        return;
    }

    $providedKey = $_SERVER['HTTP_X_OBS_KEY'] ?? '';
    if ($providedKey !== $apiKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        return;
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if ($data === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }

    if (empty($data['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required field: message']);
        return;
    }

    $id = $data['event_id'] ?? generateUUID();
    $level = $data['level'] ?? 'error';
    $message = $data['message'];
    $stacktrace = isset($data['stacktrace']) ? json_encode($data['stacktrace']) : null;
    $platform = $data['platform'] ?? null;
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    $serverName = $data['server_name'] ?? null;
    $environment = $data['environment'] ?? null;
    $extra = isset($data['extra']) ? json_encode($data['extra']) : null;

    $allowedLevels = ['error', 'warning', 'info'];
    if (!in_array($level, $allowedLevels)) {
        $level = 'error';
    }

    $db = getDB();
    $stmt = $db->prepare('INSERT INTO events (id, level, message, stacktrace, platform, timestamp, server_name, environment, extra) VALUES (:id, :level, :message, :stacktrace, :platform, :timestamp, :server_name, :environment, :extra)');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':level', $level, SQLITE3_TEXT);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->bindValue(':stacktrace', $stacktrace, SQLITE3_TEXT);
    $stmt->bindValue(':platform', $platform, SQLITE3_TEXT);
    $stmt->bindValue(':timestamp', $timestamp, SQLITE3_TEXT);
    $stmt->bindValue(':server_name', $serverName, SQLITE3_TEXT);
    $stmt->bindValue(':environment', $environment, SQLITE3_TEXT);
    $stmt->bindValue(':extra', $extra, SQLITE3_TEXT);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['id' => $id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to store event']);
    }
}

function generateUUID(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
    $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
