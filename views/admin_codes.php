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
                    <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 20px;">Generate a secure unique join link for a specific remote guest.</p>
                    
                    <div class="distant-form">
                        <div class="input-group">
                            <label style="color: var(--primary-color); display: block; margin-bottom: 8px;">Guest Name</label>
                            <input type="text" id="distant-name" class="new-code-input" style="width: 100%; margin-bottom: 15px;" placeholder="Full Name">
                        </div>
                        <div class="input-group">
                            <label style="color: var(--primary-color); display: block; margin-bottom: 8px;">Guest Email</label>
                            <input type="email" id="distant-email" class="new-code-input" style="width: 100%; margin-bottom: 20px;" placeholder="email@example.com">
                        </div>
                        <button id="generate-distant-btn" class="save-btn" style="margin-bottom: 25px;">Download Distant QR Code (PNG)</button>
                    </div>
                </div>

                <div id="hotel" class="tab-content">
                    <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 20px;">Generate a random 6-digit code for hotel guests. These appear in the 'In-person' list.</p>
                    
                    <div id="hotel-gen-section" style="text-align: center; margin-bottom: 25px;">
                        <button id="generate-hotel-btn" class="save-btn" style="max-width: 300px;">Generate New Hotel Code</button>
                    </div>

                    <div id="hotel-qrcode-section" class="qrcode-section hidden">
                        <div id="hotel-qrcode" class="qrcode-wrapper"></div>
                        <p id="hotel-code-display" style="margin-top: 15px; font-size: 1.5rem; font-family: monospace; letter-spacing: 4px; color: var(--secondary-color); font-weight: bold;"></p>
                        <p class="qrcode-hint">Scan to skip code entry</p>
                        <button id="download-hotel-qr-btn" class="back-link" style="margin-top: 15px;">Download QR Image (PNG)</button>
                    </div>
                </div>

                <div id="in-person" class="tab-content active">
                    <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 15px;">Manage active codes for guests at the venue.</p>
                    
                    <div id="qrcode-section" class="qrcode-section hidden">
                        <div id="qrcode" class="qrcode-wrapper"></div>
                        <p class="qrcode-hint">Scan to join the party automatically</p>
                    </div>

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
        window.hotelCode = <?= json_encode($hotelCode) ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="../public/js/admin_codes.js"></script>
</body>
</html>
