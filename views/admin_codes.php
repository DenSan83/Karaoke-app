<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Access Codes</title>
    <link rel="stylesheet" href="../public/css/admin.css">
    <link rel="stylesheet" href="../public/css/admin_codes.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <a href="../admin" class="back-link">← Back to Dashboard</a>
                <h1>Guest Access Codes</h1>
            </div>
        </header>

        <main>
            <div class="codes-card">
                <div class="tabs">
                    <button class="tab-btn" onclick="openTab('distant')">Distant</button>
                    <button class="tab-btn" onclick="openTab('hotel')">Hotel</button>
                    <button class="tab-btn active" onclick="openTab('in-person')">In person</button>
                </div>

                <div id="distant" class="tab-content">
                    <div class="placeholder-text">Remote access codes configuration coming soon...</div>
                </div>

                <div id="hotel" class="tab-content">
                    <div class="placeholder-text">Hotel room integration settings coming soon...</div>
                </div>

                <div id="in-person" class="tab-content active">
                    <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 15px;">Manage active codes for guests at the venue.</p>
                    
                    <ul id="code-list" class="code-list">
                        <!-- Items injected via JS -->
                    </ul>

                    <div class="add-form">
                        <input type="text" id="new-code" class="new-code-input" placeholder="Enter new code (e.g. PARTY2026)">
                        <button id="add-code-btn" class="add-btn" title="Add Code">+</button>
                    </div>

                    <button id="save-btn" class="save-btn">Save In-Person Codes</button>
                    <div id="msg" class="msg"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.currentCodes = <?php echo json_encode($guestCodes ?? []); ?>;
    </script>
    <script src="../public/js/admin_codes.js"></script>
</body>
</html>
