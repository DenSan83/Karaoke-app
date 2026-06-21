<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Activity Logs</title>
    <link rel="stylesheet" href="<?= ($basePath ?? '') ?>/public/css/admin.css">
    <link rel="stylesheet" href="<?= ($basePath ?? '') ?>/public/css/admin_logs.css">
    <style>
        :root {
            --primary-color: #bb86fc;
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
        }
        body { background-color: var(--bg-color); color: var(--text-color); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .container { padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { color: var(--primary-color); }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; transition: background-color 0.2s; font-size: 14px; }
        .btn-secondary { background: #333; color: #fff; }
        .btn-secondary:hover { background-color: #444; }
        .logs-table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 8px; overflow: hidden; }
        .logs-table th, .logs-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #333; }
        .logs-table th { background: #2c2c2c; color: var(--primary-color); font-weight: 600; }
        .logs-table tr:hover { background: #252525; }
        .type-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
        .badge-login { background: rgba(3, 218, 198, 0.2); color: #03dac6; }
        .badge-track { background: rgba(187, 134, 252, 0.2); color: #bb86fc; }
        .badge-system { background: rgba(255, 255, 255, 0.1); color: #fff; }
        pre { margin: 0; font-family: monospace; font-size: 0.9em; white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Activity Logs (Last 1000)</h1>
            <a href="<?= ($basePath ?? '') ?>/superadmin" class="btn btn-secondary">&larr; Back to Dashboard</a>
        </div>

        <div style="overflow-x: auto;">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Party ID</th>
                        <th>Type</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #888;">No logs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= date('Y-m-d H:i:s', (int)$log['timestamp']) ?></td>
                                <td><code><?= htmlspecialchars($log['group_id']) ?></code></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'badge-system';
                                        if (strpos($log['type'], 'login') !== false) $badgeClass = 'badge-login';
                                        if (strpos($log['type'], 'track') !== false || strpos($log['type'], 'song') !== false) $badgeClass = 'badge-track';
                                    ?>
                                    <span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($log['type']) ?></span>
                                </td>
                                <td>
                                    <pre><?php 
                                        $data = json_decode($log['data'], true);
                                        echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)); 
                                    ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
