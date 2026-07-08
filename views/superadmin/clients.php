<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Clients for <?= htmlspecialchars($group['name'] ?? 'Party') ?></title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
    <link rel="stylesheet" href="public/css/superadmin_clients.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Clients for <?= htmlspecialchars($group['name'] ?? 'Unknown Party') ?></h1>
            <div class="header-actions">
                <?php if (isset($groupId) && $groupId): ?>
                    <form action="superadmin/clear_clients" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all connection logs for this party?');">
                        <input type="hidden" name="group_id" value="<?= htmlspecialchars($groupId) ?>">
                        <button type="submit" class="btn btn-danger">Clear logs</button>
                    </form>
                <?php endif; ?>
                <a href="superadmin" class="btn btn-secondary">&larr; Back to Dashboard</a>
            </div>
        </div>

        <div class="clients-card">
            <div class="table-responsive">
                <table class="clients-table">
                    <thead>
                        <tr>
                            <th>Last Activity</th>
                            <th>Type</th>
                            <th>Identity</th>
                            <th>Technical Data</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;" class="text-muted">No clients connected yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <tr class="<?= $client['is_banned'] ? 'row-banned' : '' ?>">
                                    <td style="white-space: nowrap;" class="text-muted"><?= htmlspecialchars($client['max_last_activity']) ?></td>
                                    <td>
                                        <?php 
                                            $type = strtolower($client['type']);
                                            $badgeClass = 'badge-' . $type;
                                        ?>
                                        <span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($client['type']) ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $identitiesData = json_decode($client['identities_json'], true);
                                        $displayIdentities = [];
                                        
                                        foreach ($identitiesData as $idData) {
                                            $identity = $idData['identity'] ?? 'Unknown';
                                            $lastActivity = strtotime($idData['last_activity']);
                                            $isOnline = (bool)$idData['is_online'];
                                            $minutesSinceActivity = floor((time() - $lastActivity) / 60);
                                            
                                            $statusClass = 'offline';
                                            $statusText = htmlspecialchars($identity);
                                            
                                            if ($isOnline) {
                                                if ($minutesSinceActivity < 10) {
                                                    $statusClass = 'online';
                                                } else {
                                                    $statusClass = 'away';
                                                    $statusText .= ' (' . $minutesSinceActivity . ')';
                                                }
                                            }
                                            
                                            $displayIdentities[] = '<strong class="status-' . $statusClass . '">' . $statusText . '</strong>';
                                        }
                                        echo implode(', ', $displayIdentities);
                                        ?>
                                    </td>
                                    <td>
                                        <div class="technical-info">
                                            <pre class="data-json"><?php 
                                                $data = json_decode($client['data'], true);
                                                echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                            ?></pre>
                                            <?php if (isset($data['fingerprint'])): ?>
                                                <div class="fingerprint-hash" title="Browser Fingerprint (helps identify guests without cookies)">
                                                    <small>Fingerprint Hash:</small>
                                                    <code><?= substr(md5(json_encode($data['fingerprint'])), 0, 12) ?></code>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="actions-flex">
                                            <form action="superadmin/delete_client" method="POST" onsubmit="return confirm('Are you sure you want to delete this log entry? This will also unban the user if they were banned.');">
                                                <input type="hidden" name="client_id" value="<?= htmlspecialchars($client['client_id']) ?>">
                                                <input type="hidden" name="group_id" value="<?= htmlspecialchars($client['group_id']) ?>">
                                                <button type="submit" class="action-btn" title="Delete entry">🗑️</button>
                                            </form>
                                            
                                            <?php if (!$client['is_banned']): ?>
                                                <form action="superadmin/ban_client" method="POST" onsubmit="return confirm('Are you sure you want to ban this device? They will be unable to log in.');">
                                                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($client['client_id']) ?>">
                                                    <button type="submit" class="action-btn ban-btn" title="Ban device">🚫</button>
                                                </form>
                                            <?php else: ?>
                                                <form action="superadmin/unban_client" method="POST" onsubmit="return confirm('Are you sure you want to unban this device?');">
                                                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($client['client_id']) ?>">
                                                    <button type="submit" class="banned-label-btn" title="Unban device">BANNED</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="footer-info">
            <p><strong>Note:</strong> Multiple identities on the same row indicate the same device/browser used by different names.</p>
            <div class="legend">
                <div class="legend-item"><span class="legend-color online"></span> Active</div>
                <div class="legend-item"><span class="legend-color away"></span> Away (>10m)</div>
                <div class="legend-item"><span class="legend-color offline"></span> Offline</div>
            </div>
        </div>
    </div>
</body>
</html>
