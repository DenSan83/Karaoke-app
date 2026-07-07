<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karaoke Admin</title>
    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="stylesheet" href="public/css/admin.css">
</head>
<body>

<nav class="navbar">
    <div class="logo"><?= htmlspecialchars($partyName) ?></div>
    <button id="hamburgerBtn" class="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="navbar-controls">
        <button id="playBtn" class="nav-btn play">Play</button>
        <button id="pauseBtn" class="nav-btn pause">Pause</button>
        <button id="restartBtn" class="nav-btn restart">Restart</button>
        <button id="nextBtn" class="nav-btn next">Next<span> Track</span></button>
    </div>
</nav>

<div class="layout">
    <aside class="sidebar">
        <div class="justify-left-sidebar">
            <button id="openAddModalBtn" class="sidebar-btn">
                <span class="icon">+</span> <span class="btn-text">Add</span>
            </button>
            <button id="reorderBtn" class="sidebar-btn reorder-btn">
                <span class="icon">⇄</span> <span class="btn-text">Reorder</span>
            </button>
        </div>
        <div class="sidebar-options-container">
            <a href="admin/requests" class="sidebar-btn requests-btn">
                <span class="icon">🎵</span> <span class="btn-text">Requests</span>
            </a>
            <div id="optionsSubmenu" class="sidebar-submenu hidden">
                <a href="admin/codes" class="submenu-item">
                    <span class="icon">🔑</span> <span class="btn-text">Access Codes</span>
                </a>
                <button id="pairScreenBtn" class="submenu-item">
                    <span class="icon">📺</span> <span class="btn-text">Pair to Screen</span>
                </button>
                <button id="wordFilterBtn" class="submenu-item">
                    <span class="icon">🔍</span> <span class="btn-text">Word filter</span>
                </button>
                <button id="listCleanBtn" class="submenu-item">
                    <span class="icon">📋</span> <span class="btn-text">List and clean</span>
                </button>
                <a href="logout" id="logoutLink" class="submenu-item logout-item">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                            <line x1="12" y1="2" x2="12" y2="12"></line>
                        </svg>
                    </span> 
                    <span class="btn-text">Logout</span>
                </a>
            </div>
            <button id="optionsBtn" class="sidebar-btn options-toggle-btn">
                <span class="icon">⚙️</span> <span class="btn-text">Options</span>
            </button>
        </div>
    </aside>

    <main class="container main-content">
        
        <div id="message"></div>

        <div class="playlist">
            <h2>Current Queue</h2>
            <ul id="playlist-items">
                <!-- Playlist items will be injected here -->
            </ul>
        </div>
    </main>
</div>

<!-- Add Video Modal -->
<div id="addVideoModal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Add to Queue</h2>
        <div class="add-video-form">
            <input type="text" id="userName" placeholder="User Name" class="form-input">
            <input type="text" id="videoUrl" placeholder="Paste YouTube URL here" class="form-input">
            <button id="addBtn" class="btn-primary">Add to Queue</button>
        </div>
    </div>
</div>

<!-- Pair to Screen Modal -->
<div id="pairScreenModal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal" id="closePairScreenModal">&times;</span>
        <h2>Pair to Screen</h2>
        <div class="add-video-form">
            <p style="margin:0 0 12px;color:#aaa;font-size:0.9rem;">Enter the 6-digit code displayed on the screen.</p>
            <input type="text" id="screenCodeInput" placeholder="e.g. 123456" maxlength="6" class="form-input" inputmode="numeric" pattern="\d{6}">
            <button id="pairScreenSubmitBtn" class="btn-primary">Pair Screen</button>
        </div>
    </div>
</div>

<!-- List and Clean Modal -->
<div id="listCleanModal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal" id="closeListCleanModal">&times;</span>
        <h2>List and clean</h2>
        <div class="list-clean-form">
            <div class="checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="downloadList" checked> Download list
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="cleanList"> Clean list
                </label>
            </div>
            <div class="modal-actions">
                <button id="executeBtn" class="btn-primary">Execute</button>
                <button id="cancelListCleanBtn" class="btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Word Filter Modal -->
<div id="wordFilterModal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal" id="closeWordFilterModal">&times;</span>
        <h2>Word Filter</h2>
        <div class="word-filter-form">
            <div class="form-group">
                <label for="mustHaveWords">Must have words</label>
                <textarea id="mustHaveWords" class="form-input" placeholder="e.g. karaoke, instrument, live"></textarea>
                <small style="color: #888; display: block; margin-top: 5px;">Separate words by comma (,)</small>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label for="mustNotHaveWords">Must not have words</label>
                <textarea id="mustNotHaveWords" class="form-input" placeholder="e.g. remix, cover, reaction"></textarea>
                <small style="color: #888; display: block; margin-top: 5px;">Separate words by comma (,)</small>
            </div>
            <div class="modal-actions">
                <button id="cancelWordFilterBtn" class="btn-secondary">Cancel</button>
                <button id="saveWordFilterBtn" class="btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_PATH = <?= json_encode($this->basePath ?? '') ?>;
    window.MUST_HAVE_WORDS = <?= json_encode($group['must_have_words'] ?? '') ?>;
    window.MUST_NOT_HAVE_WORDS = <?= json_encode($group['must_not_have_words'] ?? '') ?>;
</script>
<script src="public/js/admin.js"></script>

</body>
</html>
