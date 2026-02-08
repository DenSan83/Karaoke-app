<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup - Karaoke Admin</title>
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/installing.css">
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🎤 Karaoke Admin Setup</h1>
            <p>yt-dlp is required to download and manage karaoke videos</p>
        </div>

        <div id="options-view" class="install-content">
            <h2>Choose Installation Method</h2>
            
            <div class="install-options">
                <!-- Option 1: Manual -->
                <div class="install-option" data-method="manual">
                    <div class="option-header">
                        <span class="option-icon">📝</span>
                        <h3>Manual Installation</h3>
                        <span class="option-badge recommended">Recommended</span>
                    </div>
                    <p class="option-description">Run commands via SSH. Most secure approach.</p>
                    <div class="option-pros-cons">
                        <div class="pros">
                            <strong>✓ Pros:</strong>
                            <ul>
                                <li>Most secure</li>
                                <li>No elevated permissions needed</li>
                                <li>System-wide installation</li>
                            </ul>
                        </div>
                        <div class="cons">
                            <strong>✗ Cons:</strong>
                            <ul>
                                <li>Requires SSH access</li>
                                <li>Manual steps required</li>
                            </ul>
                        </div>
                    </div>
                    <button class="install-btn" onclick="selectMethod('manual')">Select</button>
                </div>

                <!-- Option 2: Project Directory -->
                <div class="install-option" data-method="project">
                    <div class="option-header">
                        <span class="option-icon">📦</span>
                        <h3>Automated (Project Directory)</h3>
                    </div>
                    <p class="option-description">Fully automated installation to project folder.</p>
                    <div class="option-pros-cons">
                        <div class="pros">
                            <strong>✓ Pros:</strong>
                            <ul>
                                <li>Fully automated</li>
                                <li>No sudo required</li>
                                <li>One-click installation</li>
                            </ul>
                        </div>
                        <div class="cons">
                            <strong>✗ Cons:</strong>
                            <ul>
                                <li>Not in system PATH</li>
                                <li>Project-specific only</li>
                            </ul>
                        </div>
                    </div>
                    <button class="install-btn" onclick="selectMethod('project')">Select</button>
                </div>

                <!-- Option 3: System-wide -->
                <div class="install-option" data-method="system">
                    <div class="option-header">
                        <span class="option-icon">🔧</span>
                        <h3>Automated (System-wide)</h3>
                        <span class="option-badge warning">Advanced</span>
                    </div>
                    <p class="option-description">Automated system-wide installation using sudo.</p>
                    <div class="option-pros-cons">
                        <div class="pros">
                            <strong>✓ Pros:</strong>
                            <ul>
                                <li>System-wide availability</li>
                                <li>Automated process</li>
                            </ul>
                        </div>
                        <div class="cons">
                            <strong>✗ Cons:</strong>
                            <ul>
                                <li>Requires sudo configuration</li>
                                <li>Security considerations</li>
                                <li>Manual sudoers setup needed first</li>
                            </ul>
                        </div>
                    </div>
                    <button class="install-btn" onclick="selectMethod('system')">Select</button>
                </div>
            </div>
        </div>

        <!-- Installation Progress View -->
        <div id="progress-view" class="install-content hidden">
            <button class="back-btn" onclick="showOptions()">← Back to Options</button>
            
            <div id="manual-instructions" class="hidden">
                <h2>Manual Installation Instructions</h2>
                <div class="instructions-box">
                    <ol id="instruction-steps"></ol>
                </div>
                <button class="install-btn" onclick="testInstallation()">Test Installation</button>
            </div>

            <div id="auto-install" class="hidden">
                <h2 id="install-title">Installing yt-dlp...</h2>
                <div class="progress-indicator">
                    <div class="spinner"></div>
                    <p id="progress-message">Downloading from GitHub...</p>
                </div>
            </div>

            <div id="install-log" class="log-box hidden"></div>
            
            <div id="install-result" class="hidden">
                <div id="success-message" class="result-message success hidden">
                    <span class="result-icon">✅</span>
                    <h3>Installation Successful!</h3>
                    <p id="version-info"></p>
                    <button class="install-btn" onclick="redirectToApp()">Continue to App</button>
                </div>
                <div id="error-message" class="result-message error hidden">
                    <span class="result-icon">❌</span>
                    <h3>Installation Failed</h3>
                    <p id="error-info"></p>
                    <button class="install-btn" onclick="showOptions()">Try Another Method</button>
                </div>
            </div>
        </div>
    </div>

    <script src="public/js/installing.js"></script>
</body>
</html>
