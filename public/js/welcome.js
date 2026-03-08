document.addEventListener('DOMContentLoaded', () => {
    // Auto-fill code from URL if present
    const urlParams = new URLSearchParams(window.location.search);
    let codeParam = urlParams.get('c');

    // Robust check: if ?c is missing, check if first key exists with no value (e.g. ?CODE)
    // or if there is an empty key (=CODE)
    if (!codeParam) {
        for (const [key, value] of urlParams.entries()) {
            if (key && !value) { codeParam = key; break; } // ?CODE
            if (!key && value) { codeParam = value; break; } // ?=CODE
        }
    }

    const inviteInput = document.getElementById('invite-code');

    if (codeParam && inviteInput) {
        inviteInput.value = codeParam;
        // Trigger verification automatically if it's a direct QR scan link
        setTimeout(() => {
            const verifyBtn = document.getElementById('verify-btn');
            if (verifyBtn && inviteInput.value.trim()) verifyBtn.click();
        }, 300);
    }
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const nameInput = document.getElementById('guest-name');
    const error1 = document.getElementById('error-1');
    const error2 = document.getElementById('error-2');

    const verifyBtn = document.getElementById('verify-btn');
    const startBtn = document.getElementById('start-btn');

    if (verifyBtn) {
        verifyBtn.addEventListener('click', async () => {
            const code = inviteInput.value.trim();
            if (!code) return;

            try {
                const res = await fetch('api/verify-code', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });
                const data = await res.json();

                if (data.success) {
                    if (data.rejoining || data.autoLogin) {
                        window.location.href = 'guest-dashboard';
                    } else {
                        if (data.distantName) {
                            nameInput.value = data.distantName;
                        }
                        step1.classList.remove('active');
                        step2.classList.add('active');
                        nameInput.focus();
                    }
                } else {
                    error1.textContent = data.error || 'Invalid code';
                }
            } catch (err) {
                error1.textContent = 'Connection error. Try again.';
            }
        });
    }

    if (startBtn) {
        startBtn.addEventListener('click', async () => {
            const name = nameInput.value.trim();
            if (!name) return;

            try {
                const res = await fetch('api/add-guest', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = 'guest-dashboard';
                } else {
                    error2.textContent = data.error || 'Failed to join';
                }
            } catch (err) {
                error2.textContent = 'Connection error. Try again.';
            }
        });
    }

    // Enter key support
    if (inviteInput) {
        inviteInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') verifyBtn.click();
        });
    }
    if (nameInput) {
        nameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') startBtn.click();
        });
    }
});
