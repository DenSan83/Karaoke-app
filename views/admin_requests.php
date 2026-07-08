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

<?php if (isset($showCodeChangeModal) && $showCodeChangeModal): ?>
<!-- Code Change Mandatory Modal -->
<div id="codeChangeModal" class="modal visible">
    <div class="modal-content" style="text-align: center; padding: 40px 20px; background: #1a1a1a; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); max-width: 500px; margin: 0 auto; position: relative; top: 50%; transform: translateY(-50%);">
        <h2 style="color: #9965f4; margin-bottom: 20px;">Access Code Updated</h2>
        <p style="font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6; color: #eee;">The guest access code for this party has been updated.<br>Please review the new code and inform your guests if necessary.</p>
        <div style="display: flex; justify-content: center;">
            <a href="admin/codes" style="background: #9965f4; color: #000; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 1rem; transition: background 0.2s;">Go to Access Codes</a>
        </div>
    </div>
</div>
<style>
    #codeChangeModal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 10000;
        display: block;
    }
</style>
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

<script src="public/js/admin_requests.js"></script>
</body>
</html>
