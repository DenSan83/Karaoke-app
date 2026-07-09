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
            const urlParams = new URLSearchParams(window.location.search);
            const groupId = urlParams.get('g');
            if (!code) return;

            try {
                const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/verify-code';
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code, group_id: groupId })
                });
                const data = await res.json();

                if (data.success) {
                    if (data.rejoining || data.autoLogin) {
                        window.location.href = (typeof BASE_PATH !== 'undefined' && BASE_PATH ? BASE_PATH + '/' : '') + 'guest';
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

            const fingerprint = {
                ua: navigator.userAgent,
                lang: navigator.language,
                screen: `${window.screen.width}x${window.screen.height}`,
                tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
                mem: navigator.deviceMemory || 'unknown'
            };

            try {
                const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/add-guest';
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, fingerprint })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = (typeof BASE_PATH !== 'undefined' && BASE_PATH ? BASE_PATH + '/' : '') + 'guest';
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

    // Contact button bell increment and visit log
    const contactBtn = document.querySelector('.contact-btn');
    if (contactBtn) {
        contactBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const href = contactBtn.getAttribute('href');
            const basePath = typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '';
            const bellUrl = basePath + 'api/superadmin/bell/increment';
            
            // Log the visit first
            logVisit('Contact me button (homepage)').finally(() => {
                fetch(bellUrl)
                    .then(() => {
                        window.location.href = href;
                    })
                    .catch(err => {
                        console.error('Error incrementing bell:', err);
                        window.location.href = href;
                    });
            });
        });
    }

    async function logVisit(pageName) {
        const basePath = typeof BASE_PATH !== 'undefined' ? BASE_PATH : '';
        const apiUrl = (basePath.endsWith('/') ? basePath : basePath + '/') + 'api/log-visit';
        
        const technicalData = {
            browser: getBrowser(),
            device: getDevice(),
            language: navigator.language,
            fingerprint: {
                ua: navigator.userAgent,
                screen: `${window.screen.width}x${window.screen.height}`,
                tz: Intl.DateTimeFormat().resolvedOptions().timeZone
            }
        };

        try {
            await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ page: pageName, technicalData })
            });
        } catch (e) {
            console.error('Failed to log visit', e);
        }
    }

    function getBrowser() {
        const ua = navigator.userAgent;
        if (ua.includes('MSIE') || ua.includes('Trident')) return 'Internet Explorer';
        if (ua.includes('Firefox')) return 'Firefox';
        if (ua.includes('Chrome')) return 'Chrome';
        if (ua.includes('Safari')) return 'Safari';
        if (ua.includes('Opera') || ua.includes('OPR')) return 'Opera';
        return 'Unknown';
    }

    function getDevice() {
        const ua = navigator.userAgent;
        if (/mobile/i.test(ua)) return 'Mobile';
        if (/tablet/i.test(ua)) return 'Tablet';
        return 'Desktop';
    }

    // Check for session ended message
    if (urlParams.has('session_ended')) {
        const modal = document.getElementById('goodbye-modal');
        const okBtn = document.getElementById('goodbye-ok');
        if (modal) {
            modal.classList.add('active');
            if (okBtn) {
                okBtn.addEventListener('click', () => {
                    modal.classList.remove('active');
                    // Clean up URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
        }
    }
});
