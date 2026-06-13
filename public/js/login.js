document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const messageDiv = document.getElementById('login-message');

    try {
        const response = await fetch('auth', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const data = await response.json();

        if (data.success) {
            window.location.href = data.redirect || 'admin';
        } else {
            messageDiv.textContent = data.message;
            messageDiv.className = 'error-msg';
        }
    } catch (err) {
        messageDiv.textContent = 'An error occurred. Please try again.';
        messageDiv.className = 'error-msg';
    }
});
