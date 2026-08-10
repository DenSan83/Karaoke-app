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
    <nav class="navbar">
        <div class="logo">SuperAdmin</div>
        <button id="hamburgerBtn" class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <div class="layout superadmin-layout">
        <aside class="sidebar" id="sidebar">
            <a href="superadmin" class="sidebar-btn">
                <span class="icon">&larr;</span> <span class="btn-text">Back to Dashboard</span>
            </a>

            <div class="dropdown" style="width: 100%; margin-top: 10px;" id="managementDropdown">
                <button class="sidebar-btn btn-management" style="width: 100%; text-align: left;" onclick="toggleManagement(event)">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </span> 
                    <span class="btn-text">Management</span>
                </button>
                <div class="dropdown-content">
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/contact">Edit Contact</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/access_keys">Access keys bank</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/logs">See logs</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/clients">See clients</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/tests">Tests</a>
                </div>
            </div>

            <div class="sidebar-options-container" style="margin-top: auto; width: 100%;">
                <a href="logout" class="sidebar-btn logout-btn">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                            <line x1="12" y1="2" x2="12" y2="12"></line>
                        </svg>
                    </span>
                    <span class="btn-text">Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
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
    </main>
</div>
    <script>
        function toggleManagement(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('managementDropdown');
            const content = dropdown.querySelector('.dropdown-content');
            content.classList.toggle('show');
            dropdown.classList.toggle('active');
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#managementDropdown')) {
                const mgmtDropdown = document.getElementById('managementDropdown');
                if (mgmtDropdown) {
                    mgmtDropdown.classList.remove('active');
                    const content = mgmtDropdown.querySelector('.dropdown-content');
                    if (content) content.classList.remove('show');
                }
            }
        });

        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                hamburgerBtn.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
