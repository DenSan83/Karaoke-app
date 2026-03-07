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
        return;
    }

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
                } else {
                    msg.textContent = data.error || 'Failed to save codes';
                    msg.className = 'msg error';
                }
            } catch (err) {
                console.error(err);
                msg.textContent = 'Network error occurred';
                msg.className = 'msg error';
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save In-Person Codes';
            }
        });
    }

    // Initial Render
    renderCodes();
});
