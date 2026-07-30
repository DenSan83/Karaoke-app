<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Tests</title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
    <link rel="stylesheet" href="public/css/superadmin_tests.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tests</h1>
            <a href="superadmin" class="btn btn-secondary">&larr; Back to Dashboard</a>
        </div>

        <?php
        // The interpreter that will actually run the suite. Shown up front because a
        // missing CLI binary is the one failure that stops everything before it starts.
        $binary = isset($php['binary']) ? $php['binary'] : null;
        $totalCases = 0;
        foreach ($tests as $test) {
            $totalCases += $test['cases'];
        }
        ?>

        <?php if ($binary === null): ?>
            <div class="tests-warning">
                <strong>No PHP command-line interpreter was found.</strong>
                The tests cannot run here. The site is served by the
                <code><?= htmlspecialchars($php['sapi']) ?></code> SAPI
                (<code><?= htmlspecialchars($php['php_binary']) ?></code>), which cannot
                execute a script.
            </div>
        <?php endif; ?>

        <div class="tests-bar">
            <div class="tests-meta">
                <?= count($tests) ?> test file<?= count($tests) === 1 ? '' : 's' ?>,
                <?= (int)$totalCases ?> cases
                <?php if ($binary !== null): ?>
                    &middot; <span class="tests-binary"><?= htmlspecialchars($binary) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($tests)): ?>
                <a href="superadmin/tests/run" class="btn btn-primary">Run all tests</a>
            <?php endif; ?>
        </div>

        <?php if (empty($tests)): ?>
            <div class="tests-empty">
                No runnable tests were found in the <code>tests/</code> folder.
                A file is only listed here once it carries a <code>Testfile</code> marker
                comment naming itself, for example
                <code>/**Testfile: MyFeatureTest*/</code> on its second line.
            </div>
        <?php else: ?>
            <ul class="tests-list">
                <?php foreach ($tests as $test): ?>
                    <li class="tests-row">
                        <div class="tests-row-main">
                            <span class="tests-name"><?= htmlspecialchars($test['name']) ?></span>
                            <span class="tests-count"><?= (int)$test['cases'] ?> cases</span>
                            <?php if ($test['summary'] !== ''): ?>
                                <span class="tests-summary"><?= htmlspecialchars($test['summary']) ?></span>
                            <?php endif; ?>
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

        <p class="tests-note">
            Tests run in a separate command-line process and never touch the live
            database: anything database-backed uses a scratch schema whose name ends in
            <code>_test</code>, and nothing is ever downloaded from YouTube.
        </p>
    </div>
</body>
</html>
