<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Karaoke Party</title>
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/welcome.css">
</head>
<body>
    <div class="welcome-page">
        <div class="welcome-card">
            <div class="welcome-logo">Karaoke Party</div>
            <p class="welcome-subtitle">Join the queue and sing your heart out!</p>

            <!-- Step 1: Choice/Code -->
            <div id="step-1" class="step active">
                <div class="qr-placeholder">
                    Scan the DJ's QR Code<br>
                    <small>(Coming soon)</small>
                </div>
                <div class="choice-divider">OR</div>
                <div class="input-group">
                    <label>Enter Invite Code</label>
                    <input type="text" id="invite-code" class="welcome-input" placeholder="••••••••" maxlength="8">
                </div>
                <button id="verify-btn" class="welcome-btn">Enter Party</button>
                <div id="error-1" class="error-message"></div>
            </div>

            <!-- Step 2: Name Entry -->
            <div id="step-2" class="step">
                <div class="input-group">
                    <label>What's your name?</label>
                    <input type="text" id="guest-name" class="welcome-input name-input" placeholder="Your stage name..." maxlength="20">
                </div>
                <button id="start-btn" class="welcome-btn">Start Requesting</button>
                <div id="error-2" class="error-message"></div>
            </div>
        </div>
    </div>

    <script src="public/js/welcome.js"></script>
</body>
</html>
