<?php
/**
 * Test runner.
 *
 *   php tests/run.php                  run every *Test.php in this folder
 *   php tests/run.php Playlist         run every file whose name contains "Playlist"
 *   php tests/run.php UiWiringTest     run exactly that file
 *
 * Exits 0 when everything passed or was skipped, 1 on the first failure, so it can
 * be wired into a pre-commit hook or CI step later.
 */

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? null;
$files = glob(__DIR__ . DIRECTORY_SEPARATOR . '*Test.php') ?: [];
sort($files);

// An exact file name wins over the substring match, so asking for "SystemInfoTest"
// can never also pull in a future "MySystemInfoTest". The superadmin runner relies on
// this to guarantee that one play button runs one file.
if ($filter !== null) {
    $wanted = strtolower(basename($filter, '.php'));
    foreach ($files as $file) {
        if (strtolower(basename($file, '.php')) === $wanted) {
            $files = [$file];
            $filter = null;
            break;
        }
    }
}

foreach ($files as $file) {
    if ($filter !== null && stripos(basename($file), $filter) === false) {
        continue;
    }
    $GLOBALS['__test_file'] = $file;
    require_once $file;
}
$GLOBALS['__test_file'] = null;

$passed = 0;
$failed = [];
$skipped = [];
$currentFile = null;

foreach ($GLOBALS['__tests'] as $case) {
    if ($case['file'] !== $currentFile) {
        $currentFile = $case['file'];
        echo "\n" . $currentFile . "\n";
    }

    try {
        ($case['fn'])();
        $passed++;
        echo "  [pass] {$case['name']}\n";
    } catch (TestSkipped $e) {
        $skipped[] = $case['name'];
        echo "  [skip] {$case['name']} — {$e->getMessage()}\n";
    } catch (AssertionFailed $e) {
        $failed[] = ['case' => $case, 'error' => $e->getMessage()];
        echo "  [FAIL] {$case['name']}\n         {$e->getMessage()}\n";
    } catch (Throwable $e) {
        $failed[] = [
            'case' => $case,
            'error' => get_class($e) . ': ' . $e->getMessage()
                . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        ];
        echo "  [FAIL] {$case['name']}\n         " . end($failed)['error'] . "\n";
    } finally {
        cleanup_tracked_paths();
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
printf(
    "%d passed, %d failed, %d skipped (%d total)\n",
    $passed,
    count($failed),
    count($skipped),
    $passed + count($failed) + count($skipped)
);

if ($failed) {
    echo "\nFailures:\n";
    foreach ($failed as $failure) {
        echo "  - {$failure['case']['file']}: {$failure['case']['name']}\n    {$failure['error']}\n";
    }
}

exit($failed ? 1 : 0);
