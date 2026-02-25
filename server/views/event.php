<?php

require_once __DIR__ . '/../db.php';

function renderEvent(string $id): string {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $event = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$event) {
        http_response_code(404);
        $content = '<div class="empty-state"><h2>Event not found</h2></div>';
        $title = 'Not Found';
        ob_start();
        include __DIR__ . '/layout.php';
        return ob_get_clean();
    }

    $stacktrace = $event['stacktrace'] ? json_decode($event['stacktrace'], true) : null;
    $extra = $event['extra'] ? json_decode($event['extra'], true) : null;
    $badgeClass = 'badge-' . htmlspecialchars($event['level']);

    ob_start(); ?>
    <p style="margin-bottom: 16px;"><a href="/">&larr; Back to events</a></p>

    <div class="detail-section">
        <h2>Event</h2>
        <div style="margin-bottom: 12px;">
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($event['level']) ?></span>
            <span style="font-size: 18px; font-weight: 600; margin-left: 8px;"><?= htmlspecialchars($event['message']) ?></span>
        </div>
        <div class="meta-grid">
            <span class="meta-label">ID</span>
            <span><code><?= htmlspecialchars($event['id']) ?></code></span>
            <span class="meta-label">Timestamp</span>
            <span><?= htmlspecialchars($event['timestamp']) ?></span>
            <?php if ($event['platform']): ?>
                <span class="meta-label">Platform</span>
                <span><?= htmlspecialchars($event['platform']) ?></span>
            <?php endif; ?>
            <?php if ($event['server_name']): ?>
                <span class="meta-label">Server</span>
                <span><?= htmlspecialchars($event['server_name']) ?></span>
            <?php endif; ?>
            <?php if ($event['environment']): ?>
                <span class="meta-label">Environment</span>
                <span><?= htmlspecialchars($event['environment']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($stacktrace): ?>
    <div class="detail-section">
        <h2>Stacktrace</h2>
        <div class="stacktrace">
            <?php foreach ($stacktrace as $frame): ?>
                <div class="stack-frame">
                    <span class="stack-func"><?= htmlspecialchars($frame['function'] ?? '?') ?></span>
                    <?php if (!empty($frame['filename'])): ?>
                        <br><span class="stack-file"><?= htmlspecialchars($frame['filename']) ?></span><?php if (isset($frame['lineno'])): ?>:<span class="stack-line"><?= (int)$frame['lineno'] ?></span><?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($extra): ?>
    <div class="detail-section">
        <h2>Extra Data</h2>
        <pre class="json"><?= htmlspecialchars(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
    </div>
    <?php endif; ?>

    <?php
    $content = ob_get_clean();
    $title = $event['message'];
    ob_start();
    include __DIR__ . '/layout.php';
    return ob_get_clean();
}
