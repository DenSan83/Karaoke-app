<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Edit Contact</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/public/css/admin.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/public/css/superadmin.css">
</head>
<body>
    <div class="contact-container">
        <div class="contact-card">
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
