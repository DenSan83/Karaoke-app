<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Party Management</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($basePath ?? '') ?>/public/media/karaoke_logo.png">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/public/css/admin.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/public/css/superadmin.css">
    <style>
        .input-with-button {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-with-button input {
            padding-right: 100px !important;
        }
        .input-with-button .btn-generate {
            position: absolute;
            right: 5px;
            padding: 5px 10px;
            font-size: 12px;
            background: var(--primary-color);
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            height: 32px;
            width: 90px;
            margin-bottom: 10px;
        }
        .btn-generate:hover {
            background-color: #9965f4;
        }
        .btn-generate svg {
            width: 14px;
            height: 14px;
        }
    </style>
</head>
<body>
    <div class="bell-container" id="bellBtn" onclick="handleBellClick()">
        <svg class="bell-icon" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <div id="bellCounter" class="bell-counter">0</div>
    </div>
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
            <button class="sidebar-btn" onclick="showCreateModal()">
                <span class="icon">+</span> <span class="btn-text">Create Party</span>
            </button>
            
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
                    <a href="#" class="dropdown-info" onclick="event.preventDefault(); showInfoModal();">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        System Informations
                    </a>
                </div>
            </div>
            
            <div class="sidebar-options-container" style="margin-top: auto; width: 100%;">
                <a href="<?= htmlspecialchars($basePath ?? '') ?>/logout" class="sidebar-btn logout-btn">
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
            <div class="superadmin-container">
                <div class="header">
                    <h1>Party Management</h1>
                </div>

                <div class="party-grid" id="groups-list">
            <?php foreach (($groups ?? []) as $group): ?>
                <div class="party-card">
                    <div class="kebab-menu">
                        <button class="kebab-btn" onclick="toggleKebab(event, '<?= $group['id'] ?>')">⋮</button>
                        <div id="dropdown-<?= $group['id'] ?>" class="kebab-dropdown">
                            <a href="<?= htmlspecialchars($basePath ?? '') ?>/admin?group_id=<?= $group['id'] ?>">Manage</a>
                            <button onclick='showEditModal(<?= json_encode($group) ?>)'>Edit</button>
                            <a href="<?= htmlspecialchars($basePath ?? '') ?>/superadmin/clients?group_id=<?= $group['id'] ?>">Clients</a>
                            <button class="delete-option" onclick="deleteGroup('<?= $group['id'] ?>')">Delete</button>
                        </div>
                    </div>
                    <h3><?= htmlspecialchars($group['name']) ?></h3>
                    <p>Party ID: <strong><?= $group['id'] ?></strong></p>
                    <p>Admin Username: <?= htmlspecialchars($group['admin_username']) ?></p>
                    <p>Duration: <?= ucfirst($group['duration_type']) ?></p>
                    <p>Allow fallback: <strong><?= !empty($group['allow_fallback']) ? 'Yes' : 'No' ?></strong></p>

                    <?php if ($group['duration_type'] === 'limited'): ?>
                        <p>From: <?= date('Y-m-d H:i', strtotime($group['valid_from'])) ?></p>
                        <p>To: <?= date('Y-m-d H:i', strtotime($group['valid_to'])) ?></p>
                    <?php endif; ?>
                    
                    <p>
                        Admin PIN:
                        <span class="pin-display"><?= $group['admin_pin'] ?></span>
                    </p>
                    <p>
                        Access Code:
                        <span class="pin-display"><?= htmlspecialchars($group['access_code'] ?? 'None') ?></span>
                    </p>

                    <div class="party-actions">
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($groups ?? [])): ?>
                <p style="grid-column: 1/-1; text-align: center; color: #888; padding: 40px;">No parties created yet.</p>
            <?php endif; ?>
        </div>
    </div>
    </main>
</div>

    <div id="createModal" class="modal">
        <div class="modal-content">
            <h2>Create New Party</h2>
            <form id="createForm">
                <div class="form-group">
                    <label>Party Name</label>
                    <input type="text" id="name" placeholder="e.g. Wedding Hall A" required>
                </div>
                <div class="form-group">
                    <label>Admin Username</label>
                    <input type="text" id="admin_username" placeholder="Unique username for this party" required>
                </div>
                <div class="form-group">
                    <label>Access Code</label>
                    <div class="input-with-button">
                        <input type="text" id="access_code" placeholder="Code for guests to join" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()">
                        <button type="button" class="btn-generate" onclick="generateCode('access_code')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg>
                            Generate
                        </button>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="unlimited" checked onchange="toggleDurationFields()">
                    <label for="unlimited">Unlimited duration</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="allow_fallback">
                    <label for="allow_fallback">Allow fallback</label>
                </div>
                <div id="durationFields" style="display:none;">
                    <div class="form-group">
                        <label>Valid From</label>
                        <input type="datetime-local" id="valid_from">
                    </div>
                    <div class="form-group">
                        <label>Valid To</label>
                        <input type="datetime-local" id="valid_to">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-logout" onclick="hideCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Party</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit Party</h2>
            <form id="editForm">
                <input type="hidden" id="edit_old_id">
                <div class="form-group">
                    <label>Party ID</label>
                    <input type="text" id="edit_id" required>
                </div>
                <div class="form-group">
                    <label>Party Name</label>
                    <input type="text" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Admin Username</label>
                    <input type="text" id="edit_admin_username" required>
                </div>
                <div class="form-group">
                    <label>Admin PIN</label>
                    <input type="text" id="edit_admin_pin" required>
                </div>
                <div class="form-group">
                    <label>Access Code</label>
                    <div class="input-with-button">
                        <input type="text" id="edit_access_code" placeholder="Code for guests to join" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()">
                        <button type="button" class="btn-generate" onclick="generateCode('edit_access_code')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg>
                            Generate
                        </button>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="edit_unlimited" onchange="toggleEditDurationFields()">
                    <label for="edit_unlimited">Unlimited duration</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="edit_allow_fallback">
                    <label for="edit_allow_fallback">Allow fallback</label>
                </div>
                <div id="editDurationFields" style="display:none;">
                    <div class="form-group">
                        <label>Valid From</label>
                        <input type="datetime-local" id="edit_valid_from">
                    </div>
                    <div class="form-group">
                        <label>Valid To</label>
                        <input type="datetime-local" id="edit_valid_to">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-logout" onclick="hideEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="infoModal" class="modal">
        <div class="modal-content">
            <h2>System Informations</h2>
            <div id="infoBody" class="system-info">
                <p class="info-loading">Loading…</p>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-logout" onclick="hideInfoModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Management Dropdown Toggle
        function toggleManagement(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('managementDropdown');
            const content = dropdown.querySelector('.dropdown-content');
            const isShowing = content.classList.contains('show');
            
            // Close other dropdowns if any (though there's currently only one)
            content.classList.toggle('show');
            dropdown.classList.toggle('active');
        }

        // Hamburger Menu Toggle
        function toggleKebab(event, id) {
            event.stopPropagation();
            const dropdown = document.getElementById(`dropdown-${id}`);
            const allDropdowns = document.querySelectorAll('.kebab-dropdown');
            
            allDropdowns.forEach(d => {
                if (d !== dropdown) d.classList.remove('show');
            });
            
            dropdown.classList.toggle('show');
        }

        // Close dropdowns when clicking elsewhere
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.kebab-menu')) {
                document.querySelectorAll('.kebab-dropdown').forEach(d => d.classList.remove('show'));
            }
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

        const BASE_PATH = '<?= $basePath ?? '' ?>';

        async function fetchBellCount() {
            try {
                const response = await fetch(`${BASE_PATH}/api/superadmin/bell/count`);
                const data = await response.json();
                if (data.success) {
                    updateBellDisplay(data.count);
                }
            } catch (e) { console.error('Error fetching bell count:', e); }
        }

        function updateBellDisplay(count) {
            const counter = document.getElementById('bellCounter');
            if (count > 0) {
                counter.textContent = count;
                counter.style.display = 'flex';
            } else {
                counter.style.display = 'none';
                counter.textContent = '0';
            }
        }

        async function handleBellClick() {
            // Always Reset
            const counter = document.getElementById('bellCounter');
            if (counter.style.display === 'none') return;
            
            try {
                const response = await fetch(`${BASE_PATH}/api/superadmin/bell/reset`);
                const data = await response.json();
                if (data.success) {
                    updateBellDisplay(0);
                }
            } catch (e) { console.error('Error resetting bell:', e); }
        }

        async function generateCode(inputId) {
            try {
                const response = await fetch(`${BASE_PATH}/api/superadmin/get_access_keys`);
                const data = await response.json();
                
                if (!data.success || !data.keys || data.keys.length === 0) {
                    alert('Access Keys Bank is empty. Please add some keys first.');
                    return;
                }
                
                const randomKey = data.keys[Math.floor(Math.random() * data.keys.length)];
                
                const now = new Date();
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const yearFull = String(now.getFullYear());
                const yearShort = yearFull.slice(-2);
                const hour = String(now.getHours()).padStart(2, '0');
                
                const timeComponents = [day, month, yearFull, yearShort, hour];
                const randomTime = timeComponents[Math.floor(Math.random() * timeComponents.length)];
                
                const finalCode = (randomKey + randomTime).toUpperCase();
                document.getElementById(inputId).value = finalCode;
            } catch (e) {
                console.error('Error generating code:', e);
                alert('Failed to generate code.');
            }
        }

        // Initial fetch
        fetchBellCount();
        // Poll every 30 seconds
        setInterval(fetchBellCount, 30000);

        function showCreateModal() {
            const modal = document.getElementById('createModal');
            modal.style.display = 'flex';
            modal.classList.add('visible');
        }
        function hideCreateModal() {
            const modal = document.getElementById('createModal');
            modal.classList.remove('visible');
            setTimeout(() => {
                if (!modal.classList.contains('visible')) {
                    modal.style.display = 'none';
                }
            }, 300);
        }

        function showInfoModal() {
            const modal = document.getElementById('infoModal');
            modal.style.display = 'flex';
            modal.classList.add('visible');
            loadSystemInfo();
        }
        function hideInfoModal() {
            const modal = document.getElementById('infoModal');
            modal.classList.remove('visible');
            setTimeout(() => {
                if (!modal.classList.contains('visible')) {
                    modal.style.display = 'none';
                }
            }, 300);
        }

        function loadSystemInfo() {
            const body = document.getElementById('infoBody');
            body.innerHTML = '<p class="info-loading">Loading…</p>';
            fetch('<?= htmlspecialchars($basePath ?? '') ?>/superadmin/system_info')
                .then(r => r.json())
                .then(data => renderSystemInfo(data))
                .catch(() => {
                    body.innerHTML = '';
                    const p = document.createElement('p');
                    p.className = 'info-notice';
                    p.textContent = 'Could not read the system information.';
                    body.appendChild(p);
                });
        }

        // Values come from yt-dlp / PHP output, so every cell is written with
        // textContent rather than innerHTML.
        function renderSystemInfo(data) {
            const body = document.getElementById('infoBody');
            body.innerHTML = '';

            function group(title) {
                const wrap = document.createElement('div');
                wrap.className = 'info-group';
                const h = document.createElement('h3');
                h.textContent = title;
                wrap.appendChild(h);
                body.appendChild(wrap);
                return wrap;
            }
            function row(wrap, label, value, state) {
                const line = document.createElement('div');
                line.className = 'info-row';
                const l = document.createElement('span');
                l.className = 'info-label';
                l.textContent = label;
                const v = document.createElement('span');
                v.className = 'info-value' + (state ? ' ' + state : '');
                v.textContent = (value === null || value === undefined || value === '') ? '—' : value;
                line.appendChild(l);
                line.appendChild(v);
                wrap.appendChild(line);
                return line;
            }
            function notice(wrap, text, state) {
                const p = document.createElement('p');
                p.className = 'info-notice' + (state ? ' ' + state : '');
                p.textContent = text;
                wrap.appendChild(p);
            }

            const yt = data.yt_dlp || {};
            const g1 = group('yt-dlp');
            row(g1, 'Version', yt.version, yt.version ? 'ok' : 'bad');
            row(g1, 'Source', yt.source);
            row(g1, 'Path', yt.path);
            row(g1, 'File date', yt.updated);
            if (yt.error) notice(g1, yt.error, 'bad');
            if (yt.notice) notice(g1, yt.notice);

            const php = data.php || {};
            const web = php.web || {};
            const g2 = group('PHP (web server)');
            row(g2, 'Version', web.version, 'ok');
            row(g2, 'SAPI', web.sapi);
            row(g2, 'Binary', web.binary);

            const cli = php.cli || {};
            const g3 = group('PHP (background worker)');
            row(g3, 'Version', cli.version, cli.version ? 'ok' : 'bad');
            row(g3, 'Binary', cli.binary, cli.binary ? null : 'bad');
            if (cli.error) notice(g3, cli.error, 'bad');

            const g4 = group('Server');
            row(g4, 'OS', data.os);
            row(g4, 'Server time', data.server_time);
        }

        function showEditModal(group) {
            document.getElementById('edit_old_id').value = group.id;
            document.getElementById('edit_id').value = group.id;
            document.getElementById('edit_name').value = group.name;
            document.getElementById('edit_admin_username').value = group.admin_username;
            document.getElementById('edit_admin_pin').value = group.admin_pin;
            document.getElementById('edit_access_code').value = group.access_code || '';
            document.getElementById('edit_unlimited').checked = group.duration_type === 'unlimited';
            document.getElementById('edit_allow_fallback').checked = !!parseInt(group.allow_fallback);

            if (group.valid_from) {
                document.getElementById('edit_valid_from').value = group.valid_from.replace(' ', 'T').slice(0, 16);
            }
            if (group.valid_to) {
                document.getElementById('edit_valid_to').value = group.valid_to.replace(' ', 'T').slice(0, 16);
            }
            
            toggleEditDurationFields();
            
            const modal = document.getElementById('editModal');
            modal.style.display = 'flex';
            modal.classList.add('visible');
        }

        function hideEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('visible');
            setTimeout(() => {
                if (!modal.classList.contains('visible')) {
                    modal.style.display = 'none';
                }
            }, 300);
        }

        function toggleDurationFields() {
            const unlimited = document.getElementById('unlimited').checked;
            document.getElementById('durationFields').style.display = unlimited ? 'none' : 'block';
            if (!unlimited) {
                // Set default dates if empty
                const from = document.getElementById('valid_from');
                const to = document.getElementById('valid_to');
                if (!from.value) {
                    const now = new Date();
                    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                    from.value = now.toISOString().slice(0, 16);
                }
                if (!to.value) {
                    const later = new Date();
                    later.setHours(later.getHours() + 4);
                    later.setMinutes(later.getMinutes() - later.getTimezoneOffset());
                    to.value = later.toISOString().slice(0, 16);
                }
            }
        }

        function toggleEditDurationFields() {
            const unlimited = document.getElementById('edit_unlimited').checked;
            document.getElementById('editDurationFields').style.display = unlimited ? 'none' : 'block';
        }

        document.getElementById('createForm').onsubmit = function(e) {
            e.preventDefault();
            const data = {
                name: document.getElementById('name').value,
                admin_username: document.getElementById('admin_username').value,
                access_code: document.getElementById('access_code').value,
                duration_type: document.getElementById('unlimited').checked ? 'unlimited' : 'limited',
                valid_from: document.getElementById('valid_from').value,
                valid_to: document.getElementById('valid_to').value,
                allow_fallback: document.getElementById('allow_fallback').checked ? 1 : 0
            };

            fetch('<?= htmlspecialchars($basePath ?? '') ?>/api/superadmin/create_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => {
                return r.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned an invalid response');
                    }
                });
            }).then(res => {
                if (res.success) location.reload();
                else alert(res.message || 'Error creating party');
            }).catch(err => {
                console.error('Error:', err);
                alert(err.message || 'Connection error');
            });
        };

        document.getElementById('editForm').onsubmit = function(e) {
            e.preventDefault();
            const oldId = document.getElementById('edit_old_id').value;
            const data = {
                id: oldId,
                new_id: document.getElementById('edit_id').value,
                name: document.getElementById('edit_name').value,
                admin_username: document.getElementById('edit_admin_username').value,
                admin_pin: document.getElementById('edit_admin_pin').value,
                access_code: document.getElementById('edit_access_code').value,
                duration_type: document.getElementById('edit_unlimited').checked ? 'unlimited' : 'limited',
                valid_from: document.getElementById('edit_valid_from').value,
                valid_to: document.getElementById('edit_valid_to').value,
                allow_fallback: document.getElementById('edit_allow_fallback').checked ? 1 : 0
            };

            fetch('<?= htmlspecialchars($basePath ?? '') ?>/api/superadmin/update_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json()).then(res => {
                if (res.success) location.reload();
                else alert(res.message || 'Error updating party');
            }).catch(err => {
                console.error('Error:', err);
                alert(err.message || 'Connection error');
            });
        };

        function deleteGroup(id) {
            if (!confirm('Are you sure you want to delete this party? All associated data (playlist, guests, logs) will be PERMANENTLY lost.')) return;
            fetch('<?= htmlspecialchars($basePath ?? '') ?>/api/superadmin/delete_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            }).then(r => r.json()).then(res => {
                if (res.success) location.reload();
                else alert(res.message || 'Error deleting party');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const editModal = document.getElementById('editModal');
            const infoModal = document.getElementById('infoModal');
            if (event.target == createModal) hideCreateModal();
            if (event.target == editModal) hideEditModal();
            if (event.target == infoModal) hideInfoModal();
        }
    </script>
</body>
</html>
