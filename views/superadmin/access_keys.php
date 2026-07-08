<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Access Keys Bank</title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
    <link rel="stylesheet" href="public/css/superadmin_access_keys.css">
</head>
<body>
    <div class="keys-container">
        <div class="keys-card">
            <a href="superadmin" class="btn btn-secondary" style="margin-bottom: 20px; display: inline-block;">&larr; Back to Dashboard</a>
            <h1>Management - Access Keys Bank</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    Access keys bank updated successfully!
                </div>
            <?php endif; ?>

            <form method="POST">
                <textarea name="access_keys" class="keys-textarea" placeholder="Paste your access keys here..."><?= htmlspecialchars($accessKeys) ?></textarea>
                <div class="actions-bottom">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
