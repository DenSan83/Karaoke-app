<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Access Keys Bank</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/public/css/admin.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/public/css/superadmin.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/public/css/superadmin_access_keys.css">
</head>
<body>
    <div class="keys-container">
        <div class="keys-card">
            <a href="<?= ($basePath ?? '') ?>/superadmin" class="btn btn-secondary" style="margin-bottom: 20px; display: inline-block;">&larr; Back to Dashboard</a>
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
