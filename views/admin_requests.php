<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests Management - <?= htmlspecialchars($partyName) ?></title>
    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/admin_requests.css">
</head>
<body>

<nav class="navbar">
    <div class="logo"><?= htmlspecialchars($partyName) ?></div>
    <div class="navbar-controls">
        <a href="admin" class="nav-btn next nav-back-to-queue">Back to Queue</a>
    </div>
</nav>

<div class="layout">
    <aside class="sidebar">
        <a href="admin" class="sidebar-btn queue">
            <span class="icon">📋</span> <span class="btn-text">Queue</span>
        </a>
        <a href="logout" class="sidebar-btn logout-btn">
            <span class="icon">⏻</span> <span class="btn-text">Logout</span>
        </a>
    </aside>

    <main class="container main-content">
        <div class="requests-card">
            <div class="header-with-action">
                <h1>Requests</h1>
            </div>
            
            <p style="color: #888; margin-bottom: 30px;">Overview of all active guests and their requests.</p>

            <div id="guests-container">
                <!-- Guests and songs will be loaded here -->
                <p class="no-requests">Loading requests...</p>
            </div>
        </div>
    </main>
</div>

<script src="public/js/admin_requests.js"></script>
</body>
</html>
