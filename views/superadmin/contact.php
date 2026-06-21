<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Edit Contact</title>
    <link rel="stylesheet" href="<?= ($basePath ?? '') ?>/public/css/admin.css">
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
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .card { background: var(--card-bg); padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        h1 { color: var(--primary-color); margin-top: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; opacity: 0.8; }
        input[type="email"] { width: 100%; padding: 12px; border: 1px solid #333; background: #2c2c2c; color: white; border-radius: 4px; box-sizing: border-box; outline: none; font-size: 16px; }
        input[type="email"]:focus { border-color: var(--primary-color); }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; transition: background-color 0.2s; font-size: 14px; }
        .btn-primary { background: var(--primary-color); color: #000; }
        .btn-primary:hover { background-color: #9965f4; }
        .btn-secondary { background: #333; color: #fff; margin-right: 10px; }
        .btn-secondary:hover { background-color: #444; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: rgba(3, 218, 198, 0.1); color: var(--secondary-color); border: 1px solid var(--secondary-color); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <a href="<?= ($basePath ?? '') ?>/superadmin" class="btn btn-secondary" style="margin-bottom: 20px; display: inline-block;">&larr; Back to Dashboard</a>
            <h1>Management - Contact Email</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    Contact email updated successfully!
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="contact_email">"Contact me" email address</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($contactEmail) ?>" required>
                    <p style="font-size: 0.8em; opacity: 0.6; margin-top: 5px;">This email will be used in the mailto link on the welcome page.</p>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>
