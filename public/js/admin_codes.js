let initialCode = '';
let qrGenerationTimeout = null;

function updateQRCode() {
    const section = document.getElementById('qrcode-section');
    const container = document.getElementById('qrcode');
    const input = document.getElementById('guest-code');
    const qrPlaceholder = document.getElementById('qrcode-placeholder');
    const qrHint = document.querySelector('.qrcode-hint');
    if (!section || !container || !input) return;

    const code = input.value.trim();

    if (!code) {
        section.classList.add('hidden');
        return;
    }

    section.classList.remove('hidden');

    // Ensure QR is visible and placeholder is hidden when updating
    container.classList.remove('hidden');
    if (qrPlaceholder) qrPlaceholder.classList.add('hidden');
    if (qrHint) qrHint.classList.remove('hidden');

    // Cancel any pending generation
    if (qrGenerationTimeout) {
        clearTimeout(qrGenerationTimeout);
    }

    // Create base URL (strip /admin/codes)
    let baseUrl = window.location.origin + window.location.pathname.split('/admin')[0];
    // Fix for environments where origin might be empty or problematic
    if (!window.location.origin) {
        baseUrl = window.location.protocol + "//" + window.location.host + window.location.pathname.split('/admin')[0];
    }
    const groupId = window.groupId || '';
    const joinUrl = `${baseUrl}/?c=${encodeURIComponent(code)}&g=${encodeURIComponent(groupId)}`;
    console.log("Generating QR for:", joinUrl);

    try {
        // Clear the container completely before generating new QR
        container.innerHTML = '';
        
        // Re-add the spinner
        const newSpinner = document.createElement('div');
        newSpinner.id = 'qr-spinner';
        newSpinner.className = 'qr-spinner';
        container.appendChild(newSpinner);
        
        // Use a slight delay to ensure the DOM is ready and library is definitely loaded
        qrGenerationTimeout = setTimeout(() => {
            if (typeof QRCode === 'undefined') {
                console.error("QRCode library not loaded");
                return;
            }
            
            // Double check container is still empty (except for spinner) to avoid duplicates from race conditions
            // We clear it again just in case another call slipped through
            const elements = container.querySelectorAll('canvas, img');
            elements.forEach(el => el.remove());
            
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
                const imgs = container.querySelectorAll('img');
                const canvases = container.querySelectorAll('canvas');
                
                // If we somehow got multiple, keep only the latest ones
                if (imgs.length > 1 || canvases.length > 1) {
                    for (let i = 0; i < imgs.length - 1; i++) imgs[i].remove();
                    for (let i = 0; i < canvases.length - 1; i++) canvases[i].remove();
                }

                const img = container.querySelector('img');
                const canvas = container.querySelector('canvas');
                
                if (img && img.src && img.src !== location.href && img.src.startsWith('data:image')) {
                    // Success!
                    img.style.display = 'block';
                    img.style.margin = '0 auto';
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    if (canvas) canvas.style.display = 'none';
                    const spinner = container.querySelector('#qr-spinner');
                    if (spinner) spinner.classList.add('hidden');
                    
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
                    const spinner = container.querySelector('#qr-spinner');
                    if (spinner) spinner.classList.add('hidden');
                    clearInterval(checkImg);
                }
                attempts++;
            }, 100);
        }, 50);
    } catch (e) {
        console.error("QR Generation Error:", e);
    }
}

function checkChanges() {
    const saveBtn = document.getElementById('save-btn');
    const input = document.getElementById('guest-code');
    const qrWrapper = document.getElementById('qrcode');
    const qrPlaceholder = document.getElementById('qrcode-placeholder');
    const qrHint = document.querySelector('.qrcode-hint');

    if (!saveBtn || !input) return;

    const hasChanges = input.value.trim() !== initialCode;
    saveBtn.disabled = !hasChanges;

    // Toggle QR visibility based on changes
    if (hasChanges) {
        if (qrWrapper) qrWrapper.classList.add('hidden');
        if (qrPlaceholder) qrPlaceholder.classList.remove('hidden');
        if (qrHint) qrHint.classList.add('hidden');
    } else {
        if (qrWrapper) qrWrapper.classList.remove('hidden');
        if (qrPlaceholder) qrPlaceholder.classList.add('hidden');
        if (qrHint) qrHint.classList.remove('hidden');
        
        // Re-generate if we're back to initial code but it wasn't displayed
        if (input.value.trim() !== '') {
            // Only update if not already there or if we just toggled back
            updateQRCode();
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
    const guestCodeInput = document.getElementById('guest-code');
    const saveBtn = document.getElementById('save-btn');
    const sessionToggle = document.getElementById('session-toggle');
    const sessionStatusText = document.getElementById('session-status-text');

    // Save initial state
    if (guestCodeInput) {
        initialCode = guestCodeInput.value.trim();
        guestCodeInput.addEventListener('input', () => {
            checkChanges();
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
            const code = guestCodeInput.value.trim();

            if (!code) {
                if (!confirm('Are you sure you want to save an EMPTY code? No one will be able to join.')) return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            msg.textContent = '';

            try {
                const res = await fetch('api/update_code', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ codes: [code] })
                });
                const data = await res.json();

                if (data.success) {
                    msg.textContent = 'Code saved successfully!';
                    msg.className = 'msg success';

                    // Update initial state after successful save
                    initialCode = code;
                    updateQRCode(); // Update QR with the new saved code
                } else {
                    msg.textContent = data.error || 'Failed to save code';
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
                saveBtn.textContent = 'Save Code';
                checkChanges(); 
            }
        });
    }

    const generateBtn = document.getElementById('generate-btn');
    if (generateBtn && guestCodeInput) {
        generateBtn.addEventListener('click', async () => {
            try {
                generateBtn.disabled = true;
                const originalContent = generateBtn.innerHTML;
                generateBtn.textContent = '...';

                // We need basePath here. admin_codes.php has <base href="<?= htmlspecialchars($basePath) ?>/">
                // So we can use relative path or absolute if we can find it.
                // In groups.php it used BASE_PATH global.
                // Let's try to get it from the base tag if needed, but 'api/superadmin/get_access_keys' should work if base is set.
                
                const response = await fetch('api/superadmin/get_access_keys');
                const data = await response.json();
                
                if (!data.success || !data.keys || data.keys.length === 0) {
                    alert('Access Keys Bank is empty. Please contact SuperAdmin.');
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
                guestCodeInput.value = finalCode;
                
                // Trigger events
                checkChanges();
                
                generateBtn.innerHTML = originalContent;
            } catch (e) {
                console.error('Error generating code:', e);
                alert('Failed to generate code.');
            } finally {
                generateBtn.disabled = false;
            }
        });
    }

    // Initial Render
    updateQRCode();
    checkChanges();
});
