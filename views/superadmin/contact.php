<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Edit Contact</title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">SuperAdmin</div>
        <button id="hamburgerBtn" class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <div class="layout superadmin-layout">
        <aside class="sidebar" id="sidebar">
            <a href="superadmin" class="sidebar-btn">
                <span class="icon">&larr;</span> <span class="btn-text">Back to Dashboard</span>
            </a>

            <div class="dropdown" style="width: 100%; margin-top: 10px;" id="managementDropdown">
                <button class="sidebar-btn btn-management" style="width: 100%; text-align: left;" onclick="toggleManagement(event)">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </span> 
                    <span class="btn-text">Management</span>
                </button>
                <div class="dropdown-content">
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/contact">Edit Contact</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/access_keys">Access keys bank</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/logs">See logs</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/clients">See clients</a>
                    <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/tests">Tests</a>
                </div>
            </div>

            <div class="sidebar-options-container" style="margin-top: auto; width: 100%;">
                <a href="logout" class="sidebar-btn logout-btn">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                            <line x1="12" y1="2" x2="12" y2="12"></line>
                        </svg>
                    </span>
                    <span class="btn-text">Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="contact-container">
                <div class="contact-card">
                    <h1>Management - Contact Email</h1>
                    
                    <?php if (isset($success) && $success): ?>
                        <div class="alert alert-success">
                            Contact email updated successfully!
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="contact_email">"Contact me" email address</label>
                            <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($contactEmail ?? '') ?>" required>
                            <p style="font-size: 0.8em; opacity: 0.6; margin-top: 5px;">This email will be used in the mailto link on the welcome page.</p>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
        function toggleManagement(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('managementDropdown');
            const content = dropdown.querySelector('.dropdown-content');
            content.classList.toggle('show');
            dropdown.classList.toggle('active');
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#managementDropdown')) {
                const mgmtDropdown = document.getElementById('managementDropdown');
                if (mgmtDropdown) {
                    mgmtDropdown.classList.remove('active');
                    const content = mgmtDropdown.querySelector('.dropdown-content');
                    if (content) content.classList.remove('show');
                }
            }
        });

        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                hamburgerBtn.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
