<?php

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API route
if ($uri === '/api/events' && $method === 'POST') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/api.php';
    handleEventIngestion();
    return true;
}

// Event detail
if (preg_match('#^/events/([a-f0-9\-]+)$#', $uri, $matches)) {
    require_once __DIR__ . '/views/event.php';
    echo renderEvent($matches[1]);
    return true;
}

// Dashboard
if ($uri === '/') {
    require_once __DIR__ . '/views/dashboard.php';
    echo renderDashboard();
    return true;
}

// Static files — let PHP built-in server handle them
return false;
