<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>obs — <?= htmlspecialchars($title ?? 'Dashboard') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
            background: #0d1117;
            color: #c9d1d9;
            line-height: 1.6;
        }
        a { color: #58a6ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 960px; margin: 0 auto; padding: 20px; }
        header {
            border-bottom: 1px solid #21262d;
            padding: 16px 0;
            margin-bottom: 24px;
        }
        header h1 { font-size: 20px; font-weight: 600; }
        header h1 a { color: #c9d1d9; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-error { background: #da3633; color: #fff; }
        .badge-warning { background: #d29922; color: #fff; }
        .badge-info { background: #1f6feb; color: #fff; }
        .event-list { list-style: none; }
        .event-item {
            padding: 12px 16px;
            border: 1px solid #21262d;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #161b22;
        }
        .event-item:hover { border-color: #388bfd; }
        .event-meta {
            font-size: 12px;
            color: #8b949e;
            margin-top: 4px;
        }
        .event-message {
            font-size: 14px;
            font-weight: 500;
            word-break: break-word;
        }
        .event-count {
            float: right;
            background: #21262d;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            color: #8b949e;
        }
        .detail-section {
            background: #161b22;
            border: 1px solid #21262d;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .detail-section h2 {
            font-size: 14px;
            color: #8b949e;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stacktrace {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        .stack-frame {
            padding: 8px 12px;
            border-bottom: 1px solid #21262d;
        }
        .stack-frame:last-child { border-bottom: none; }
        .stack-file { color: #58a6ff; }
        .stack-func { color: #d2a8ff; }
        .stack-line { color: #8b949e; }
        .meta-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px;
            font-size: 14px;
        }
        .meta-label { color: #8b949e; }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8b949e;
        }
        pre.json {
            background: #0d1117;
            padding: 12px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><a href="/">obs</a></h1>
        </header>
        <?= $content ?>
    </div>
</body>
</html>
