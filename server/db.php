<?php

function getDB(): SQLite3 {
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    $dbPath = getenv('OBS_DB_PATH') ?: __DIR__ . '/obs.sqlite';
    $db = new SQLite3($dbPath);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');

    $db->exec('CREATE TABLE IF NOT EXISTS events (
        id TEXT PRIMARY KEY,
        level TEXT NOT NULL DEFAULT "error",
        message TEXT NOT NULL,
        stacktrace TEXT,
        platform TEXT,
        timestamp DATETIME NOT NULL,
        server_name TEXT,
        environment TEXT,
        extra TEXT
    )');

    $db->exec('CREATE INDEX IF NOT EXISTS idx_events_timestamp ON events(timestamp DESC)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_events_message ON events(message)');

    return $db;
}
