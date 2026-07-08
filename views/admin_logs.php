<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($partyName) ?> - Activity Logs</title>
    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/admin_logs.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <a href="admin" class="back-link">← Back to Dashboard</a>
                <h1><?= htmlspecialchars($partyName) ?> - Activity Logs</h1>
            </div>
        </header>

        <main>
            <div class="logs-card">
                <div class="tabs">
                    <button class="tab-btn active" onclick="openTab('users')">Users</button>
                    <button class="tab-btn" onclick="openTab('tracks')">Tracks</button>
                    <button class="tab-btn" onclick="openTab('system')">System</button>
                </div>

                <div id="users" class="tab-content active">
                    <div class="log-container">
                        <?php if (empty($userLogs)): ?>
                            <p class="empty-msg">User login activity and registrations will appear here.</p>
                        <?php else: ?>
                            <table class="log-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Guest Name</th>
                                        <th>Code Used</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userLogs as $log): 
                                        $data = $log['data'];
                                        $timestamp = $data['timestamp'] ?? $log['timestamp'];
                                        $guestId = $data['guestId'] ?? '';
                                    ?>
                                        <tr>
                                            <td class="log-time"><?= date('H:i:s', $timestamp) ?><br><small><?= date('d M', $timestamp) ?></small></td>
                                            <td class="log-name"><?= htmlspecialchars($data['name']) ?></td>
                                            <td class="log-code"><code><?= htmlspecialchars($data['code'] ?? '-') ?></code></td>
                                            <td>
                                                <?php if ($guestId): ?>
                                                    <button class="remove-btn" title="Delete & Logout Guest" onclick="deleteUser('<?= htmlspecialchars($guestId) ?>', '<?= htmlspecialchars($data['name']) ?>')">🗑️</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="tracks" class="tab-content">
                    <div class="log-container">
                        <div class="log-header-active">
                            <h3>Current Tracks</h3>
                            <button class="download-btn" onclick="downloadTrackList()">📥 Download Song List</button>
                        </div>
                        
                        <?php if (empty($trackLogs)): ?>
                            <p class="empty-msg">No tracks have been requested yet.</p>
                        <?php else: ?>
                            <table class="log-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>User</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trackLogs as $index => $track): ?>
                                        <tr>
                                            <td class="log-index"><?= count($trackLogs) - $index ?></td>
                                            <td class="track-title"><?= htmlspecialchars($track['title']) ?></td>
                                            <td class="track-user">
                                                <strong><?= htmlspecialchars($track['userName']) ?></strong>
                                            </td>
                                            <td class="log-time"><?= date('H:i:s', $track['added_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="system" class="tab-content">
                    <div class="log-container">
                        <p class="empty-msg">System events, errors, and configuration changes will appear here.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php if (isset($showCodeChangeModal) && $showCodeChangeModal): ?>
<!-- Code Change Mandatory Modal -->
<div id="codeChangeModal" class="modal visible" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); z-index: 10000; display: flex; justify-content: center; align-items: center;">
    <div style="text-align: center; padding: 40px 20px; background: #1a1a1a; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); max-width: 500px; width: 90%;">
        <h2 style="color: #9965f4; margin-bottom: 20px;">Access Code Updated</h2>
        <p style="font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6; color: #eee;">The guest access code for this party has been updated.<br>Please review the new code and inform your guests if necessary.</p>
        <div style="display: flex; justify-content: center;">
            <a href="admin/codes" style="background: #9965f4; color: #000; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 1rem; transition: background 0.2s;">Go to Access Codes</a>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('codeChangeModal');
        if (modal) {
            modal.addEventListener('click', function(e) { e.stopPropagation(); }, true);
            window.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); }
            }, true);
        }
    });
</script>
<?php endif; ?>

    <script src="public/js/admin_logs.js"></script>
</body>
</html>
