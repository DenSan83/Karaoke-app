<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Party Management</title>
    <link rel="stylesheet" href="public/css/admin.css">
    <style>
        :root {
            --primary-color: #bb86fc;
            --secondary-color: #03dac6;
            --error-color: #cf6679;
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
        }
        body { background-color: var(--bg-color); color: var(--text-color); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .superadmin-container { padding-top: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: var(--primary-color); margin: 0; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; transition: transform 0.2s, background-color 0.2s; }
        .btn:hover { transform: scale(1.02); }
        .btn-primary { background: var(--primary-color); color: #000; }
        .btn-primary:hover { background-color: #9965f4; }
        .btn-danger { background: var(--error-color); color: #000; }
        .btn-danger:hover { background-color: #d32f2f; color: #fff; }
        .btn-logout { background: rgba(207, 102, 121, 0.1); color: var(--error-color); border: 1px solid rgba(207, 102, 121, 0.2); }
        .btn-logout:hover { background-color: var(--error-color); color: #fff; }
        
        .party-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .party-card { background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border-left: 5px solid var(--primary-color); position: relative; }
        .party-card h3 { margin: 0 0 10px 0; color: var(--primary-color); }
        .party-card p { margin: 5px 0; color: var(--text-color); opacity: 0.8; font-size: 0.9em; }
        .pin-display { background: #2c2c2c; padding: 5px 10px; border-radius: 4px; border: 1px dashed #444; font-family: monospace; font-size: 1.2em; color: var(--secondary-color); display: inline-block; margin-top: 10px; }
        .party-actions { margin-top: 15px; display: flex; gap: 10px; }
        .party-actions button, .party-actions a { font-size: 0.8em; padding: 5px 10px; }

        /* Modal styling */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 2000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.8); 
            backdrop-filter: blur(3px);
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .modal.visible {
            opacity: 1;
        }
        .modal-content { background: #1e1e1e; color: #e0e0e0; width: 90%; max-width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .modal-content h2 { margin-top: 0; border-bottom: 1px solid #333; padding-bottom: 10px; color: var(--primary-color); }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--text-color); opacity: 0.8; }
        .form-group input[type="text"], .form-group input[type="datetime-local"] { width: 100%; padding: 10px; border: 1px solid #333; background: #2c2c2c; color: white; border-radius: 4px; box-sizing: border-box; outline: none; }
        .form-group input[type="text"]:focus, .form-group input[type="datetime-local"]:focus { border-color: var(--primary-color); }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin: 15px 0; }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SuperAdmin Office</div>
        <button id="hamburgerBtn" class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <button class="sidebar-btn" onclick="showCreateModal()">
                <span class="icon">+</span> <span class="btn-text">Create Party</span>
            </button>
            
            <div class="sidebar-options-container" style="margin-top: auto; width: 100%;">
                <a href="<?= ($basePath ?? '') ?>/logout" class="sidebar-btn logout-btn">
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
                    <h3><?= htmlspecialchars($group['name']) ?></h3>
                    <p>Party ID: <strong><?= $group['id'] ?></strong></p>
                    <p>Admin Username: <?= htmlspecialchars($group['admin_username']) ?></p>
                    <p>Duration: <?= ucfirst($group['duration_type']) ?></p>
                    <?php if ($group['duration_type'] === 'limited'): ?>
                        <p>From: <?= date('Y-m-d H:i', strtotime($group['valid_from'])) ?></p>
                        <p>To: <?= date('Y-m-d H:i', strtotime($group['valid_to'])) ?></p>
                    <?php endif; ?>
                    
                    <p>Admin PIN:</p>
                    <div class="pin-display"><?= $group['admin_pin'] ?></div>

                    <div class="party-actions">
                        <button class="btn btn-primary" onclick='showEditModal(<?= json_encode($group) ?>)'>Edit</button>
                        <button class="btn btn-danger" onclick="deleteGroup('<?= $group['id'] ?>')">Delete</button>
                        <a href="<?= ($basePath ?? '') ?>/admin?group_id=<?= $group['id'] ?>" class="btn btn-primary" style="background:var(--primary-color); color:#000;">Manage</a>
                        <a href="<?= ($basePath ?? '') ?>/screen/<?= $group['id'] ?>" target="_blank" class="btn btn-primary" style="background:var(--secondary-color); color:#000;">Screen</a>
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
                <div class="checkbox-group">
                    <input type="checkbox" id="unlimited" checked onchange="toggleDurationFields()">
                    <label for="unlimited">Unlimited duration</label>
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
                <div class="checkbox-group">
                    <input type="checkbox" id="edit_unlimited" onchange="toggleEditDurationFields()">
                    <label for="edit_unlimited">Unlimited duration</label>
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

    <script>
        // Hamburger Menu Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                hamburgerBtn.classList.toggle('active');
            });
        }

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

        function showEditModal(group) {
            document.getElementById('edit_old_id').value = group.id;
            document.getElementById('edit_id').value = group.id;
            document.getElementById('edit_name').value = group.name;
            document.getElementById('edit_admin_username').value = group.admin_username;
            document.getElementById('edit_admin_pin').value = group.admin_pin;
            document.getElementById('edit_unlimited').checked = group.duration_type === 'unlimited';
            
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
                duration_type: document.getElementById('unlimited').checked ? 'unlimited' : 'limited',
                valid_from: document.getElementById('valid_from').value,
                valid_to: document.getElementById('valid_to').value
            };

            fetch('<?= $basePath ?>/api/superadmin/create_group', {
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
                duration_type: document.getElementById('edit_unlimited').checked ? 'unlimited' : 'limited',
                valid_from: document.getElementById('edit_valid_from').value,
                valid_to: document.getElementById('edit_valid_to').value
            };

            fetch('<?= $basePath ?>/api/superadmin/update_group', {
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
            fetch('<?= $basePath ?>/api/superadmin/delete_group', {
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
            if (event.target == createModal) hideCreateModal();
            if (event.target == editModal) hideEditModal();
        }
    </script>
</body>
</html>
