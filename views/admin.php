<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karaoke Admin</title>
    <link rel="stylesheet" href="public/css/admin.css">
</head>
<body>

<nav class="navbar">
    <div class="logo">Party Admin</div>
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
        <button id="openAddModalBtn" class="sidebar-btn">
            <span class="icon">+</span> Add
        </button>
        <button id="reorderBtn" class="sidebar-btn reorder-btn">
            <span class="icon">⇄</span> <span class="btn-text">Reorder</span>
        </button>
        <div class="sidebar-options-container">
            <div id="optionsSubmenu" class="sidebar-submenu hidden">
                <a href="admin/requests" class="submenu-item">
                    <span class="icon">🎵</span> <span class="btn-text">Requests</span>
                </a>
                <a href="admin/codes" class="submenu-item">
                    <span class="icon">🔑</span> <span class="btn-text">Access Codes</span>
                </a>
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

<script src="public/js/admin.js"></script>

</body>
</html>
