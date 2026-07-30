<?php
/**
 * The data behind the superadmin "Informations" modal:
 * SystemCheck::getYtDlpInfo(), describeYtDlpSource(), getPhpInfo(), and the
 * SuperAdminController::systemInfo() endpoint that serves them as JSON.
 */

require_once PROJECT_ROOT . '/app/Services/SystemCheck.php';

test('getYtDlpInfo returns the full shape the modal renders', function () {
    $info = SystemCheck::getYtDlpInfo();

    assert_array_has_keys(['path', 'source', 'version', 'updated', 'notice', 'error'], $info);
    assert_true(is_string($info['path']) && $info['path'] !== '', 'yt-dlp path must always be reported');
    assert_true(is_string($info['source']) && $info['source'] !== '', 'yt-dlp source must always be described');
});

test('the reported yt-dlp version is the bare version, not warning noise', function () {
    $info = SystemCheck::getYtDlpInfo();

    if ($info['error'] !== null) {
        skip_test('yt-dlp could not be probed here: ' . $info['error']);
    }

    // yt-dlp leads its stderr with "your version is older than 90 days" on an old
    // install. That noise used to crowd out the real value, so the version must be
    // the bare date-style build number and nothing else.
    assert_matches('/^\d{4}\.\d{2}\.\d{2}[\w.\-]*$/', $info['version'], 'yt-dlp version was not parsed cleanly');
    assert_not_contains("\n", $info['version'], 'The version must be a single line');
    assert_not_contains('older than', $info['version']);
    assert_not_contains('WARNING', strtoupper($info['version']));
});

test('an out-of-date yt-dlp surfaces as a notice rather than an error', function () {
    $info = SystemCheck::getYtDlpInfo();

    if ($info['notice'] === null) {
        skip_test('this yt-dlp does not report itself as out of date');
    }

    assert_contains('out of date', $info['notice']);
    assert_null($info['error'], 'An out-of-date warning must not be reported as a failure');
    assert_true(!empty($info['version']), 'The version must still be read from an out-of-date binary');
});

test('the yt-dlp source names where the binary came from', function () {
    $info = SystemCheck::getYtDlpInfo();

    if (DIRECTORY_SEPARATOR === '\\') {
        assert_same('Bundled yt-dlp.exe', $info['source']);
    } else {
        assert_matches(
            '/^(Project bin\/yt-dlp|System install \(.+\)|Resolved through PATH)$/',
            $info['source'],
            'Unrecognised yt-dlp source description'
        );
    }
});

test('describeYtDlpSource recognises the documented system install paths', function () {
    if (DIRECTORY_SEPARATOR === '\\') {
        skip_test('the Windows branch returns before the system-path lookup');
    }

    $source = call_private('SystemCheck', 'describeYtDlpSource', ['/usr/local/bin/yt-dlp']);
    assert_same('System install (/usr/local/bin/yt-dlp)', $source);

    // Anything unrecognised must still be described rather than left blank.
    assert_same('Resolved through PATH', call_private('SystemCheck', 'describeYtDlpSource', ['yt-dlp']));
});

test('a missing yt-dlp file date is reported as null, never as a bad date', function () {
    $info = SystemCheck::getYtDlpInfo();

    if ($info['updated'] === null) {
        return;
    }
    assert_matches('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $info['updated'], 'Unexpected file-date format');
});

test('getPhpInfo reports the SAPI actually serving the request', function () {
    $info = SystemCheck::getPhpInfo();

    assert_array_has_keys(['web', 'cli'], $info);
    assert_array_has_keys(['version', 'sapi', 'binary'], $info['web']);
    assert_same(PHP_VERSION, $info['web']['version']);
    assert_same(PHP_SAPI, $info['web']['sapi']);
    assert_same(PHP_BINARY, $info['web']['binary']);
});

test('getPhpInfo reports the interpreter that will run the worker', function () {
    $info = SystemCheck::getPhpInfo();

    assert_array_has_keys(['binary', 'version', 'error'], $info['cli']);

    if (empty($info['cli']['binary'])) {
        // A box with no usable CLI binary cannot download anything, and the modal
        // exists precisely to say so out loud.
        assert_contains('background downloads cannot start', (string)$info['cli']['error']);
        return;
    }

    assert_null($info['cli']['error'], 'A resolved CLI binary should not also report an error');
    assert_matches('/^\d+\.\d+\.\d+/', (string)$info['cli']['version'], 'CLI version was not parsed');
});

test('the worker interpreter is not an older PHP line than the web server', function () {
    $info = SystemCheck::getPhpInfo();

    if (empty($info['cli']['version'])) {
        skip_test('no CLI version to compare');
    }

    // This is the invariant the newest-first candidate ordering exists to hold: a
    // worker running an older major/minor than the site would fail in ways the
    // admin could never guess from the queue.
    $web = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    preg_match('/^(\d+\.\d+)/', $info['cli']['version'], $match);

    assert_true(
        version_compare($match[1], $web, '>='),
        "The worker would run PHP {$match[1]} while the site runs PHP $web"
    );
});

test('systemInfo returns the modal payload as JSON', function () {
    // The controller reaches the Group model in its constructor, so this needs a
    // database. bypassAuth is the constructor's own escape hatch.
    test_db_reset();
    require_once PROJECT_ROOT . '/app/Controllers/SuperAdminController.php';

    $controller = new SuperAdminController(true);

    // The runner has already written to stdout, so the endpoint's Content-Type call
    // raises "headers already sent". Suppressing it keeps the captured buffer to
    // exactly what the endpoint itself emits.
    ob_start();
    @$controller->systemInfo();
    $body = ob_get_clean();

    $payload = json_decode($body, true);
    assert_true(is_array($payload), 'systemInfo did not emit valid JSON: ' . $body);
    assert_array_has_keys(['yt_dlp', 'php', 'os', 'server_time'], $payload);
    assert_array_has_keys(['path', 'source', 'version'], $payload['yt_dlp']);
    assert_array_has_keys(['web', 'cli'], $payload['php']);
    assert_matches('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $payload['server_time']);
    assert_true(is_string($payload['os']) && $payload['os'] !== '');
});

test('systemInfo is refused without a superadmin session', function () {
    // The guard ends the process, so it has to be exercised in a child.
    $script = track_path(project_path('temp/auth_probe_' . getmypid() . '.php'));
    if (!is_dir(dirname($script))) {
        @mkdir(dirname($script), 0775, true);
    }
    file_put_contents($script, <<<'PHP'
<?php
$_SESSION = [];
require_once 'app/Controllers/SuperAdminController.php';
$controller = new SuperAdminController();
$controller->systemInfo();
echo 'REACHED_ENDPOINT';
PHP
    );

    $result = run_php([$script]);

    assert_not_contains('REACHED_ENDPOINT', $result['output'], 'The auth guard let an anonymous caller through');
    assert_not_contains('yt_dlp', $result['output'], 'System information leaked without a session');
});
