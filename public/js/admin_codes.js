let initialCodes = [];
let qr = null;

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
    let baseUrl = window.location.origin + window.location.pathname.split('/admin')[0];
    // Fix for environments where origin might be empty or problematic
    if (!window.location.origin) {
        baseUrl = window.location.protocol + "//" + window.location.host + window.location.pathname.split('/admin')[0];
    }
    const groupId = window.groupId || '';
    const joinUrl = `${baseUrl}/?c=${encodeURIComponent(firstCode)}&g=${encodeURIComponent(groupId)}`;
    console.log("Generating QR for:", joinUrl);

    try {
        container.innerHTML = ''; // Ensure container is empty
        
        // Use a slight delay to ensure the DOM is ready and library is definitely loaded
        setTimeout(() => {
            if (typeof QRCode === 'undefined') {
                console.error("QRCode library not loaded");
                return;
            }
            
            // Set fixed size for generation to ensure library has enough space
            const size = 256;
            
            const qrcode = new QRCode(container, {
                text: joinUrl,
                width: size,
                height: size,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
            
            // The library might be slow in creating the img from canvas
            // We'll check periodically for a few times
            let attempts = 0;
            const checkImg = setInterval(() => {
                const img = container.querySelector('img');
                const canvas = container.querySelector('canvas');
                
                if (img && img.src && img.src !== location.href) {
                    // Success!
                    img.style.display = 'block';
                    img.style.margin = '0 auto';
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    if (canvas) canvas.style.display = 'none';
                    
                    // On some mobile browsers, the image might need an explicit trigger to redraw
                    img.style.opacity = '0.99';
                    setTimeout(() => { img.style.opacity = '1'; }, 10);
                    
                    clearInterval(checkImg);
                } else if (attempts > 20) {
                    // Fallback: if image never appears, show the canvas
                    if (canvas) {
                        canvas.style.display = 'block';
                        canvas.style.margin = '0 auto';
                        canvas.style.maxWidth = '100%';
                        canvas.style.height = 'auto';
                        canvas.style.background = '#fff';
                        canvas.style.padding = '5px';
                    }
                    clearInterval(checkImg);
                }
                attempts++;
            }, 100);
        }, 50);
    } catch (e) {
        console.error("QR Generation Error:", e);
    }
}

function removeCode(index) {
    window.currentCodes.splice(index, 1);
    renderCodes();
}

function checkChanges() {
    const saveBtn = document.getElementById('save-btn');
    if (!saveBtn) return;

    const hasChanges = JSON.stringify([...window.currentCodes].sort()) !== JSON.stringify([...initialCodes].sort());
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
    const sessionToggle = document.getElementById('session-toggle');
    const sessionStatusText = document.getElementById('session-status-text');

    if (addBtn) addBtn.addEventListener('click', addCode);
    if (newInput) {
        newInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') addCode();
        });
    }

    if (sessionToggle) {
        sessionToggle.addEventListener('change', async () => {
            const isAllowed = sessionToggle.checked;
            sessionToggle.disabled = true;

            try {
                const res = await fetch('api/toggle_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ allow: isAllowed })
                });
                const data = await res.json();
                if (data.success) {
                    if (sessionStatusText) {
                        sessionStatusText.textContent = isAllowed ? 'Currently allowing new guests' : 'New guests are blocked';
                    }
                } else {
                    alert(data.error || 'Failed to update session');
                    sessionToggle.checked = !isAllowed;
                }
            } catch (err) {
                console.error(err);
                alert('Connection error');
                sessionToggle.checked = !isAllowed;
            } finally {
                sessionToggle.disabled = false;
            }
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
                const res = await fetch('api/update_code', {
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
