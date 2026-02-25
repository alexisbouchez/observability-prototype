<?php

require_once __DIR__ . '/../db.php';

function renderDashboard(): string {
    $db = getDB();

    $result = $db->query('
        SELECT message, level, platform, MAX(timestamp) as last_seen, COUNT(*) as count
        FROM events
        GROUP BY message
        ORDER BY last_seen DESC
        LIMIT 100
    ');

    $groups = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $groups[] = $row;
    }

    ob_start();
    if (empty($groups)): ?>
        <div class="empty-state">
            <h2>No events yet</h2>
            <p>Send your first event via <code>POST /api/events</code></p>
        </div>
    <?php else: ?>
        <ul class="event-list">
            <?php foreach ($groups as $group):
                $badgeClass = 'badge-' . htmlspecialchars($group['level']);
                // Find latest event with this message for linking
                $stmt = $db->prepare('SELECT id FROM events WHERE message = :msg ORDER BY timestamp DESC LIMIT 1');
                $stmt->bindValue(':msg', $group['message'], SQLITE3_TEXT);
                $latest = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            ?>
                <li class="event-item">
                    <a href="/events/<?= htmlspecialchars($latest['id']) ?>">
                        <span class="event-count"><?= (int)$group['count'] ?></span>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($group['level']) ?></span>
                        <span class="event-message"><?= htmlspecialchars($group['message']) ?></span>
                        <div class="event-meta">
                            <?= htmlspecialchars($group['last_seen']) ?>
                            <?php if ($group['platform']): ?>
                                &middot; <?= htmlspecialchars($group['platform']) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;

    $content = ob_get_clean();
    $title = 'Dashboard';
    ob_start();
    include __DIR__ . '/layout.php';
    return ob_get_clean();
}
