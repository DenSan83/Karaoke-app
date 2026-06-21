<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Party Time</title>
    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/welcome.css">
</head>
<body>
    <div class="welcome-page">
        <div class="welcome-card">
            <div class="welcome-logo">Party Time</div>
            <p class="welcome-subtitle">Join the queue and sing your heart out!</p>

            <!-- Step 1: Choice/Code -->
            <div id="step-1" class="step active">
                <div class="input-group">
                    <label>Enter Invite Code</label>
                    <?php 
                        $inviteCode = $_GET['c'] ?? '';
                        if (!$inviteCode && !empty($_GET)) {
                            // Support ?=CODE
                            if (isset($_GET['']) && $_GET[''] !== '') { 
                                $inviteCode = $_GET['']; 
                            }
                            // Support ?CODE (CODE is the first key with no value)
                            else { 
                                $firstKey = key($_GET); 
                                // Ignore 'r' which is the internal routing parameter from .htaccess
                                if ($firstKey !== 'r' && $_GET[$firstKey] === '') { 
                                    $inviteCode = $firstKey; 
                                } 
                            }
                        }
                    ?>
                    <input type="text" id="invite-code" class="welcome-input" placeholder="Enter code..." 
                           value="<?php echo htmlspecialchars($inviteCode); ?>">
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
        <div class="contact-card">
            <p class="contact-text">Want to animate your party? <a href="mailto:contact@devdensan.com?subject=Karaoke%20app%20request" class="contact-btn">Contact me</a> for more information</p>
        </div>
    </div>

    <!-- Goodbye Modal -->
    <div id="goodbye-modal" class="guest-modal-overlay">
        <div class="guest-modal-content">
            <h2>See you soon!</h2>
            <p class="guest-modal-text">We're not accepting any more requests.<br>Thanks for joining the party!</p>
            <div class="guest-modal-actions">
                <button id="goodbye-ok" class="guest-modal-btn">Close</button>
            </div>
        </div>
    </div>

    <script>
        const BASE_PATH = <?= json_encode($this->basePath ?? '') ?>;
    </script>
    <script src="public/js/welcome.js"></script>
</body>
</html>
