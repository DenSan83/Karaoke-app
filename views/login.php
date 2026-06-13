<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Party Admin</title>
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/login.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">Party Admin</div>
            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="username" class="login-input" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label>Password / Pin</label>
                    <input type="password" id="password" class="login-input" placeholder="Enter password" required>
                </div>
                <button type="submit" class="login-btn">Sign In</button>
                <div id="login-message"></div>
            </form>
        </div>
    </div>

    <script src="public/js/login.js"></script>
</body>
</html>
