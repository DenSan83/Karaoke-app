<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Activity Logs</title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
    <link rel="stylesheet" href="public/css/admin_logs.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Activity Logs (Last 1000)</h1>
            <a href="superadmin" class="btn btn-secondary">&larr; Back to Dashboard</a>
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
                                        if ($log['type'] === 'BANNED_TRY') $badgeClass = 'badge-danger';
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
