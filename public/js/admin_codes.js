let initialCodes = [];
let qr = null;

// Tab switching
function openTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    document.getElementById(tabId).classList.add('active');
    // Find button that calls this function
    const btns = document.querySelectorAll('.tab-btn');
    if (tabId === 'distant') btns[0].classList.add('active');
    if (tabId === 'hotel') btns[1].classList.add('active');
    if (tabId === 'in-person') btns[2].classList.add('active');
}

// Render List
function renderCodes() {
    const list = document.getElementById('code-list');
    if (!list) return;

    list.innerHTML = '';

    if (window.currentCodes.length === 0) {
        list.innerHTML = '<li style="color: #666; text-align: center; padding: 10px;">No active codes</li>';
    } else {
        window.currentCodes.forEach((code, index) => {
            const li = document.createElement('li');
            li.className = 'code-item';
            li.innerHTML = `
                <span class="code-display">${escapeHtml(code)}</span>
                <button class="remove-btn" title="Remove Code" onclick="removeCode(${index})">🗑</button>
            `;
            list.appendChild(li);
        });
    }

    checkChanges();
    updateQRCode();
}

function updateQRCode() {
    const section = document.getElementById('qrcode-section');
    const container = document.getElementById('qrcode');
    if (!section || !container) return;

    if (window.currentCodes.length === 0) {
        section.classList.add('hidden');
        return;
    }

    section.classList.remove('hidden');
    const firstCode = window.currentCodes[0];

    // Create base URL (strip /admin/codes)
    const baseUrl = window.location.origin + window.location.pathname.split('/admin')[0];
    const joinUrl = `${baseUrl}/?c=${encodeURIComponent(firstCode)}`;

    if (!qr) {
        qr = new QRCode(container, {
            text: joinUrl,
            width: 160,
            height: 160,
            colorDark: "#ffffff",
            colorLight: "#1e1e1e",
            correctLevel: QRCode.CorrectLevel.H
        });
    } else {
        qr.clear();
        qr.makeCode(joinUrl);
    }
}

function checkChanges() {
    const saveBtn = document.getElementById('save-btn');
    if (!saveBtn) return;

    const hasChanges = JSON.stringify(window.currentCodes.sort()) !== JSON.stringify(initialCodes.sort());
    saveBtn.disabled = !hasChanges;
}

function addCode() {
    const input = document.getElementById('new-code');
    const code = input.value.trim();
    if (code) {
        // Determine if duplicate
        if (!window.currentCodes.includes(code)) {
            window.currentCodes.push(code);
            renderCodes();
            input.value = '';
        } else {
            alert('Code already exists in the list.');
        }
    }
}

function removeCode(index) {
    window.currentCodes.splice(index, 1);
    renderCodes();
}

// Escape helper
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    // Save initial state
    initialCodes = [...window.currentCodes];

    // Event Listeners
    const addBtn = document.getElementById('add-code-btn');
    const newInput = document.getElementById('new-code');
    const saveBtn = document.getElementById('save-btn');

    if (addBtn) addBtn.addEventListener('click', addCode);
    if (newInput) {
        newInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') addCode();
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', async () => {
            const msg = document.getElementById('msg');

            if (window.currentCodes.length === 0) {
                if (!confirm('Are you sure you want to save an EMPTY list? No one will be able to join.')) return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            msg.textContent = '';

            try {
                const res = await fetch('../api/update_code', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ codes: window.currentCodes })
                });
                const data = await res.json();

                if (data.success) {
                    msg.textContent = 'Codes saved successfully!';
                    msg.className = 'msg success';

                    // Update initial state after successful save
                    initialCodes = [...window.currentCodes];
                } else {
                    msg.textContent = data.error || 'Failed to save codes';
                    msg.className = 'msg error';
                }

                // Vanish after 5 seconds
                setTimeout(() => {
                    msg.textContent = '';
                    msg.className = 'msg';
                }, 5000);
            } catch (err) {
                console.error(err);
                msg.textContent = 'Network error occurred';
                msg.className = 'msg error';
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save In-Person Codes';
                checkChanges(); // Re-verify in case of failure or state update
            }
        });
    }

    // Initial Render
    renderCodes();
});
