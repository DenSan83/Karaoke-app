<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($partyName) ?> - Access Codes</title>
    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/admin_codes.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <a href="admin" class="back-link">← Back to Dashboard</a>
                <h1><?= htmlspecialchars($partyName) ?> - Guest Access Codes</h1>
            </div>
        </header>

        <main>
            <div class="session-master-toggle">
                <div class="toggle-info">
                    <h3>Guest Enrollment</h3>
                    <p id="session-status-text"><?= $allowNewSessions ? 'Currently allowing new guests' : 'New guests are blocked' ?></p>
                </div>
                <label class="switch">
                    <input type="checkbox" id="session-toggle" <?= $allowNewSessions ? 'checked' : '' ?>>
                    <span class="slider round"></span>
                </label>
            </div>

            <div class="codes-card">
                <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 15px;">Manage the active code for guests at the venue.</p>
                
                <div id="qrcode-section" class="qrcode-section hidden">
                    <div id="qrcode" class="qrcode-wrapper">
                        <!-- Loading spinner -->
                        <div id="qr-spinner" class="qr-spinner"></div>
                    </div>
                    <p class="qrcode-hint">Scan to join the party automatically</p>
                </div>

                <div class="add-form">
                    <input type="text" id="guest-code" class="new-code-input" placeholder="Enter code (e.g. PARTY2026)" value="<?= htmlspecialchars($guestCodes[0] ?? '') ?>">
                </div>

                <button id="save-btn" class="save-btn">Save Code</button>
                <div id="msg" class="msg"></div>
            </div>
        </main>
    </div>

    <script>
        window.currentCodes = <?php echo json_encode($guestCodes ?? []); ?>;
        window.allowNewSessions = <?= json_encode($allowNewSessions) ?>;
        window.groupId = <?= json_encode($_SESSION['group_id'] ?? null) ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="public/js/admin_codes.js"></script>
</body>
</html>
