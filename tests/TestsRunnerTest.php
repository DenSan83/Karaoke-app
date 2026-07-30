<?php
/**Testfile: TestsRunnerTest*/
/**
 * The superadmin test runner: /superadmin/tests, /superadmin/tests/run and the
 * NDJSON stream behind them.
 *
 * Nothing in here may start a run of its own. The controller spawns tests/run.php,
 * so a case that hit the stream endpoint would run the whole suite from inside the
 * suite. Everything below works on source text or on the private helpers reached
 * through reflection.
 */

require_once PROJECT_ROOT . '/app/Controllers/SuperAdminController.php';

/**
 * The controller without its constructor: that guard needs a session and a database,
 * and neither is involved in the parts under test.
 */
function tests_runner_controller() {
    static $instance = null;
    if ($instance === null) {
        $instance = (new ReflectionClass('SuperAdminController'))->newInstanceWithoutConstructor();
    }
    return $instance;
}

function tests_runner_call($method, array $arguments = []) {
    $reflected = new ReflectionMethod('SuperAdminController', $method);
    $reflected->setAccessible(true);
    return $reflected->invokeArgs(tests_runner_controller(), $arguments);
}

function tests_runner_source() {
    return read_project_file('app/Controllers/SuperAdminController.php');
}

/**
 * The controller with its comments stripped out.
 *
 * The comments in there explain why the runner avoids stream_select() and why it never
 * loads the test bootstrap, so an assertion run against the raw file would match the
 * explanation instead of the code.
 */
function tests_runner_code() {
    static $code = null;
    if ($code !== null) {
        return $code;
    }

    $code = '';
    foreach (token_get_all(tests_runner_source()) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            $code .= $token[1];
            continue;
        }
        $code .= $token;
    }

    return $code;
}

test('every test file declares a Testfile marker matching its name', function () {
    // This is what makes the listing self-maintaining: drop a new file in tests/ with
    // the marker and it appears, without anyone editing the controller.
    $files = glob(__DIR__ . DIRECTORY_SEPARATOR . '*Test.php') ?: [];
    assert_true(count($files) > 0, 'No test files were found at all');

    foreach ($files as $path) {
        $name = basename($path, '.php');
        $contents = file_get_contents($path);
        assert_matches(
            '/^\s*\/\*+\s*Testfile\s*:\s*' . preg_quote($name, '/') . '\s*\*+\//mi',
            $contents,
            basename($path) . ' has no "Testfile: ' . $name . '" marker, so the runner will not list it'
        );
    }
});

test('discovery returns the marked files and nothing else', function () {
    $found = tests_runner_call('discoverTestFiles');
    assert_true(is_array($found) && $found !== [], 'Discovery found no tests');

    $expected = [];
    foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . '*Test.php') ?: [] as $path) {
        $expected[] = basename($path, '.php');
    }
    sort($expected);

    $actual = array_keys($found);
    sort($actual);
    assert_same($expected, $actual, 'The listing does not match the marked files on disk');

    foreach ($found as $name => $test) {
        assert_array_has_keys(['name', 'file', 'path', 'cases', 'summary'], $test);
        assert_same($name, $test['name']);
        assert_true($test['cases'] > 0, "$name reported no test cases");
    }
});

test('unmarked files in the tests folder are never listed', function () {
    // bootstrap.php and run.php live alongside the tests and carry no marker. Listing
    // them would offer the reader a "test" that cannot be run.
    $found = tests_runner_call('discoverTestFiles');
    foreach (['bootstrap', 'run', '.htaccess', 'README'] as $name) {
        assert_true(!isset($found[$name]), "$name must not appear in the list of tests");
    }
});

test('the file parameter only accepts a discovered test', function () {
    // The endpoint runs code, so the parameter is matched against the discovered list
    // rather than sanitised. Anything not on that list is refused outright.
    $rejected = [
        '.htaccess',
        '../bootstrap',
        '../../index',
        'bootstrap',
        'run',
        'README',
        'SystemInfoTest.php.bak',
        'SystemInfoTest;rm -rf /',
        '*',
        '',
        '   ',
        'Test',
        'NoSuchTest'
    ];

    foreach ($rejected as $value) {
        assert_null(
            tests_runner_call('resolveTestFile', [$value]),
            "The runner accepted '$value' as a test file"
        );
    }
});

test('the file parameter accepts a real test by name or file name', function () {
    foreach (['SystemInfoTest', 'SystemInfoTest.php', 'systeminfotest'] as $value) {
        $resolved = tests_runner_call('resolveTestFile', [$value]);
        assert_true(is_array($resolved), "'$value' should resolve to a test");
        assert_same('SystemInfoTest', $resolved['name']);
        assert_same('SystemInfoTest.php', $resolved['file']);
    }
});

test('a resolved test always sits inside the tests folder', function () {
    $directory = realpath(__DIR__);
    foreach (tests_runner_call('discoverTestFiles') as $name => $test) {
        $path = realpath($test['path']);
        assert_true($path !== false, "$name resolved to a path that does not exist");
        assert_same(0, strpos($path, $directory . DIRECTORY_SEPARATOR), "$name resolved outside tests/");
    }
});

test('the controller never loads the suite into the web request', function () {
    // tests/bootstrap.php refuses to run outside the CLI SAPI, and that guard is the
    // reason the runner has to spawn a real command-line child.
    $code = tests_runner_code();
    assert_true(
        preg_match('/\b(require|include)(_once)?\b[^;]*bootstrap/i', $code) === 0,
        'The controller pulls the test bootstrap into the web request'
    );
    assert_contains('SystemCheck::resolvePhpCli()', $code);
    assert_contains("'tests' . DIRECTORY_SEPARATOR . 'run.php'", $code);
});

test('the runner child is spawned so that it can be killed', function () {
    // The array form leaves quoting to PHP; bypass_shell makes the handle point at the
    // interpreter instead of a cmd.exe wrapper, without which the kill cannot land.
    $code = tests_runner_code();
    assert_contains('$parts = [$binary, $runner]', $code);
    assert_contains("'bypass_shell' => true", $code);
    assert_contains('register_shutdown_function', $code);
    assert_contains('taskkill /F /T /PID', $code);
});

test('the runner captures output to files rather than pipes', function () {
    // stream_select() does not work on Windows anonymous pipes, so a pipe reader stops
    // receiving partway through a long run while the suite keeps going. A pipe also
    // only reports end-of-file once every process holding its write end is gone, and
    // the suite starts a web server that inherits it.
    $code = tests_runner_code();
    assert_not_contains('stream_select', $code, 'The runner is back on stream_select, which starves on Windows pipes');
    assert_contains("1 => ['file', \$paths[1], 'w']", $code);
    assert_contains("2 => ['file', \$paths[2], 'w']", $code);
    assert_contains('proc_get_status', $code);
});

test('two runs at once are refused', function () {
    // A second run would share the scratch database with the first and produce
    // failures that have nothing to do with the code.
    $code = tests_runner_code();
    assert_contains('LOCK_EX | LOCK_NB', $code);
    assert_contains('A test run is already in progress', $code);
});

test('an unknown file is reported and nothing is spawned', function () {
    $code = tests_runner_code();
    assert_contains('No such test exists.', $code);
    assert_contains('views/superadmin/tests_missing.php', $code);
    assert_contains('http_response_code(404)', $code);

    $view = read_project_file('views/superadmin/tests_missing.php');
    assert_contains('No such test exists.', $view);
});

test('the terminal writes runner output as text, never as markup', function () {
    // Output is arbitrary text from another program: assertion messages, file paths,
    // whatever a failing test printed.
    $view = read_project_file('views/superadmin/tests_run.php');
    assert_contains('textContent', $view);
    assert_not_contains('innerHTML', $view, 'The terminal builds markup out of test output');
    assert_not_contains('document.write', $view);

    // EventSource reconnects when a stream ends, which would silently start the whole
    // suite again, so the terminal reads the response body itself.
    assert_not_contains('new EventSource', $view);
    assert_contains('getReader()', $view);
});

test('the play buttons point at the single-file route', function () {
    $view = read_project_file('views/superadmin/tests.php');
    assert_contains('superadmin/tests/run?file=', $view);
    assert_contains('urlencode($test[\'name\'])', $view);
    assert_contains('superadmin/tests/run', $view);
});

test('the three routes are registered and wired to the controller', function () {
    $index = read_project_file('index.php');
    $routes = [
        "case 'superadmin/tests':" => '$controller->tests();',
        "case 'superadmin/tests/run':" => '$controller->testsRun();',
        "case 'superadmin/tests/stream':" => '$controller->testsStream();'
    ];

    foreach ($routes as $case => $call) {
        assert_contains($case, $index);
        $position = strpos($index, $case);
        $window = substr($index, $position, 200);
        assert_contains($call, $window, "$case is not wired to $call");
    }

    foreach (['tests', 'testsRun', 'testsStream'] as $method) {
        $reflected = new ReflectionMethod('SuperAdminController', $method);
        assert_true($reflected->isPublic(), "$method must be reachable from the router");
    }
});

test('the test pages stay reachable when yt-dlp is missing', function () {
    // A broken yt-dlp is exactly what you would run the tests to diagnose, so the
    // redirect to the installer must not swallow these routes.
    $index = read_project_file('index.php');
    assert_contains("strpos(\$route, 'superadmin/tests') !== 0", $index);
});

test('the test pages are refused without a superadmin session', function () {
    // The guard ends the process, so it has to be exercised in a child.
    $script = track_path(project_path('temp/tests_auth_probe_' . getmypid() . '.php'));
    if (!is_dir(dirname($script))) {
        @mkdir(dirname($script), 0775, true);
    }
    file_put_contents($script, <<<'PHP'
<?php
$_SESSION = [];
require_once 'app/Controllers/SuperAdminController.php';
$controller = new SuperAdminController();
$controller->tests();
echo 'REACHED_ENDPOINT';
PHP
    );

    $result = run_php([$script]);

    assert_not_contains('REACHED_ENDPOINT', $result['output'], 'The auth guard let an anonymous caller through');
    assert_not_contains('tests-play', $result['output'], 'The list of tests was rendered without a session');
});
