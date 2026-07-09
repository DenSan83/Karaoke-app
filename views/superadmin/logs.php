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
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        .tab-btn {
            background: #222;
            color: #888;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            background: #333;
            color: #fff;
        }
        .tab-btn.active {
            background: #9965f4;
            color: #fff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .visits-table {
            width: 100%;
            border-collapse: collapse;
            background: #111;
            border-radius: 8px;
            overflow: hidden;
        }
        .visits-table th, .visits-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #222;
        }
        .visits-table th {
            background: #1a1a1a;
            color: #9965f4;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .visits-table tr:hover {
            background: #161616;
        }
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .action-btn:hover {
            opacity: 1;
        }
        .technical-info pre {
            margin: 0;
            font-size: 0.8rem;
            color: #aaa;
            max-width: 300px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Activity Logs (Last 1000)</h1>
            <a href="superadmin" class="btn btn-secondary">&larr; Back to Dashboard</a>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="openTab('logs-tab')">Logs</button>
            <button class="tab-btn" onclick="openTab('visits-tab')">Visits</button>
        </div>

        <!-- LOGS TAB -->
        <div id="logs-tab" class="tab-content active">
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

        <!-- VISITS TAB -->
        <div id="visits-tab" class="tab-content">
            <div style="overflow-x: auto;">
                <table class="visits-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Page</th>
                            <th>Tech Data</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($visits)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: #888;">No visits recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($visits as $visit): 
                                $vData = json_decode($visit['data'], true);
                            ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?= htmlspecialchars($visit['timestamp']) ?></td>
                                    <td style="max-width: 200px; word-break: break-all;"><code><?= htmlspecialchars($visit['page']) ?></code></td>
                                    <td class="technical-info">
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                            <div><strong>Browser:</strong> <?= htmlspecialchars($vData['browser'] ?? 'Unknown') ?></div>
                                            <div><strong>Device:</strong> <?= htmlspecialchars($vData['device'] ?? 'Unknown') ?></div>
                                            <div><strong>Lang:</strong> <?= htmlspecialchars($vData['language'] ?? 'Unknown') ?></div>
                                            <div><strong>IP:</strong> <?= htmlspecialchars($vData['ip_address'] ?? 'Unknown') ?></div>
                                        </div>
                                        <?php if (isset($vData['fingerprint'])): ?>
                                            <details>
                                                <summary><small>Fingerprint</small></summary>
                                                <pre><?= htmlspecialchars(json_encode($vData['fingerprint'], JSON_PRETTY_PRINT)) ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="superadmin/delete_visit_log" method="POST" onsubmit="return confirm('Delete this entry?');">
                                            <input type="hidden" name="visit_id" value="<?= $visit['id'] ?>">
                                            <button type="submit" class="action-btn" title="Delete entry">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>
