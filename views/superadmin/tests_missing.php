<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - No such test</title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
    <link rel="stylesheet" href="public/css/superadmin_tests.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>No such test exists.</h1>
            <a href="superadmin/tests" class="btn btn-secondary">&larr; Back to Tests</a>
        </div>

        <div class="tests-warning">
            <strong>No such test exists.</strong>
            <code><?= htmlspecialchars($requested) ?></code> is not a runnable test, so
            nothing was executed.
        </div>

        <?php if (!empty($tests)): ?>
            <p class="tests-note">The tests that do exist:</p>
            <ul class="tests-list">
                <?php foreach ($tests as $test): ?>
                    <li class="tests-row">
                        <div class="tests-row-main">
                            <span class="tests-name"><?= htmlspecialchars($test['name']) ?></span>
                            <span class="tests-count"><?= (int)$test['cases'] ?> cases</span>
                        </div>
                        <a class="tests-play"
                           href="superadmin/tests/run?file=<?= urlencode($test['name']) ?>"
                           title="Run <?= htmlspecialchars($test['name']) ?>"
                           aria-label="Run <?= htmlspecialchars($test['name']) ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                                <path d="M8 5v14l11-7z"></path>
                            </svg>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>
