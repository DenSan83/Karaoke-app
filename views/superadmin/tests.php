<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Tests</title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
    <link rel="stylesheet" href="public/css/superadmin_tests.css">
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
            <div class="container">
                <div class="header">
                    <h1>Tests</h1>
                </div>

                <?php
                // The interpreter that will actually run the suite. Shown up front because a
                // missing CLI binary is the one failure that stops everything before it starts.
                $binary = isset($php['binary']) ? $php['binary'] : null;
                $totalCases = 0;
                foreach ($tests as $test) {
                    $totalCases += $test['cases'];
                }
                ?>

                <?php if ($binary === null): ?>
                    <div class="tests-warning">
                        <strong>No PHP command-line interpreter was found.</strong>
                        The tests cannot run here. The site is served by the
                        <code><?= htmlspecialchars($php['sapi'] ?? '') ?></code> SAPI
                        (<code><?= htmlspecialchars($php['php_binary'] ?? '') ?></code>), which cannot
                        execute a script.
                    </div>
                <?php endif; ?>

                <div class="tests-bar">
                    <div class="tests-meta">
                        <?= count($tests) ?> test file<?= count($tests) === 1 ? '' : 's' ?>,
                        <?= (int)$totalCases ?> cases
                        <?php if ($binary !== null): ?>
                            &middot; <span class="tests-binary"><?= htmlspecialchars($binary) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($tests)): ?>
                        <a href="superadmin/tests/run" class="btn btn-primary">Run all tests</a>
                    <?php endif; ?>
                </div>

                <?php if (empty($tests)): ?>
                    <div class="tests-empty">
                        No runnable tests were found in the <code>tests/</code> folder.
                        A file is only listed here once it carries a <code>Testfile</code> marker
                        comment naming itself, for example
                        <code>/**Testfile: MyFeatureTest*/</code> on its second line.
                    </div>
                <?php else: ?>
                    <ul class="tests-list">
                        <?php foreach ($tests as $test): ?>
                            <li class="tests-row">
                                <div class="tests-row-main">
                                    <span class="tests-name"><?= htmlspecialchars($test['name']) ?></span>
                                    <span class="tests-count"><?= (int)$test['cases'] ?> cases</span>
                                    <?php if ($test['summary'] !== ''): ?>
                                        <span class="tests-summary"><?= htmlspecialchars($test['summary']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="tests-play"
                                   href="superadmin/tests/run?file=<?= urlencode($test['name']) ?>"
                                   title="Run <?= htmlspecialchars($test['name']) ?>"
                                   aria-label="Run <?= htmlspecialchars($test['name']) ?>">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                                        <path d="M8 5v14l11-7z"></path>
                                    </svg>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <p class="tests-note">
                    Tests run in a separate command-line process and never touch the live
                    database: anything database-backed uses a scratch schema whose name ends in
                    <code>_test</code>, and nothing is ever downloaded from YouTube.
                </p>
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
