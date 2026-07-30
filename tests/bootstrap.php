<?php
/**
 * Minimal test harness.
 *
 * The project has no Composer, no vendor/ and therefore no PHPUnit, so this is a
 * deliberately small dependency-free runner: register closures with test(), assert
 * with the assert_* helpers, bail out of an inapplicable case with skip_test().
 *
 * Run everything:      php tests/run.php
 * Run a subset:        php tests/run.php PhpCli
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("The test suite may only be run from the command line.\n");
}

define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Several classes require their dependencies with paths relative to the document
// root (e.g. require_once 'app/Models/Group.php'), so the tests must run from there.
chdir(PROJECT_ROOT);

class AssertionFailed extends Exception {}
class TestSkipped extends Exception {}

$GLOBALS['__tests'] = [];
$GLOBALS['__test_file'] = null;

/**
 * Register a test case. The closure body runs later, from run.php.
 */
function test($name, callable $fn) {
    $GLOBALS['__tests'][] = [
        'name' => $name,
        'fn' => $fn,
        'file' => basename((string)$GLOBALS['__test_file'])
    ];
}

function skip_test($reason) {
    throw new TestSkipped($reason);
}

function fail($message) {
    throw new AssertionFailed($message);
}

/* ---------------------------------------------------------------- assertions */

function describe_value($value) {
    if (is_string($value)) {
        return strlen($value) > 300 ? "'" . substr($value, 0, 300) . "...'" : "'" . $value . "'";
    }
    if (is_bool($value) || is_null($value) || is_numeric($value)) {
        return var_export($value, true);
    }
    return trim(preg_replace('/\s+/', ' ', var_export($value, true)));
}

function assert_true($condition, $message = '') {
    if (!$condition) {
        fail($message !== '' ? $message : 'Expected a truthy value, got ' . describe_value($condition));
    }
}

function assert_false($condition, $message = '') {
    if ($condition) {
        fail($message !== '' ? $message : 'Expected a falsy value, got ' . describe_value($condition));
    }
}

function assert_same($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        fail(($message !== '' ? $message . ' — ' : '')
            . 'expected ' . describe_value($expected) . ', got ' . describe_value($actual));
    }
}

function assert_not_same($unexpected, $actual, $message = '') {
    if ($unexpected === $actual) {
        fail(($message !== '' ? $message . ' — ' : '') . 'did not expect ' . describe_value($unexpected));
    }
}

function assert_null($actual, $message = '') {
    if ($actual !== null) {
        fail(($message !== '' ? $message . ' — ' : '') . 'expected null, got ' . describe_value($actual));
    }
}

function assert_contains($needle, $haystack, $message = '') {
    if (strpos((string)$haystack, (string)$needle) === false) {
        fail(($message !== '' ? $message . ' — ' : '')
            . describe_value($needle) . ' was not found in ' . describe_value($haystack));
    }
}

function assert_not_contains($needle, $haystack, $message = '') {
    if (strpos((string)$haystack, (string)$needle) !== false) {
        fail(($message !== '' ? $message . ' — ' : '')
            . describe_value($needle) . ' should not appear in ' . describe_value($haystack));
    }
}

function assert_matches($pattern, $subject, $message = '') {
    if (!preg_match($pattern, (string)$subject)) {
        fail(($message !== '' ? $message . ' — ' : '')
            . describe_value($subject) . ' does not match ' . $pattern);
    }
}

function assert_array_has_keys(array $keys, $array, $message = '') {
    if (!is_array($array)) {
        fail(($message !== '' ? $message . ' — ' : '') . 'expected an array, got ' . describe_value($array));
    }
    foreach ($keys as $key) {
        if (!array_key_exists($key, $array)) {
            fail(($message !== '' ? $message . ' — ' : '') . "missing key '$key' in " . describe_value($array));
        }
    }
}

/* ------------------------------------------------------------------- helpers */

function project_path($relative) {
    return PROJECT_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function read_project_file($relative) {
    $path = project_path($relative);
    if (!is_file($path)) {
        fail("Expected file is missing: $relative");
    }
    return (string)file_get_contents($path);
}

/**
 * Call a private or protected method, so behaviour can be tested without widening
 * the production API just for the tests.
 */
function call_private($classOrObject, $method, array $args = []) {
    $class = is_object($classOrObject) ? get_class($classOrObject) : $classOrObject;
    $ref = new ReflectionMethod($class, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs(is_object($classOrObject) ? $classOrObject : null, $args);
}

function set_private_static($class, $property, $value) {
    $ref = new ReflectionProperty($class, $property);
    $ref->setAccessible(true);
    $ref->setValue(null, $value);
}

/**
 * Pull a single top-level function out of a script and define a copy of it here.
 *
 * download_worker.php and migrate_json_to_mysql.php both run work at include time
 * (the migration even auto-runs under the CLI SAPI), so they must never be
 * require'd from a test. Tokenising and eval'ing one function is the safe way to
 * exercise it. If either file is restructured this throws rather than silently
 * passing, which is the intended behaviour: the test then needs updating.
 */
function load_function_copy($relativeFile, $name) {
    if (function_exists($name)) {
        return;
    }

    $source = read_project_file($relativeFile);
    $tokens = token_get_all($source);

    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        // Next meaningful token must be the name we are after.
        $j = $i + 1;
        while ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) {
            continue;
        }

        // Copy from 'function' up to the brace that closes the body.
        $code = '';
        $depth = 0;
        $started = false;
        for ($k = $i; $k < count($tokens); $k++) {
            $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            $code .= $text;
            if ($text === '{') {
                $depth++;
                $started = true;
            } elseif ($text === '}') {
                $depth--;
                if ($started && $depth === 0) {
                    eval($code);
                    return;
                }
            }
        }
    }

    fail("Could not extract function $name() from $relativeFile");
}

/**
 * Run a PHP CLI script in a child process.
 *
 * PHP_BINARY is trustworthy here because the suite itself runs under the CLI SAPI.
 * Extension-loading warnings from php.ini are stripped so a misconfigured local
 * install cannot break assertions about what a script printed.
 */
function run_php(array $arguments, $timeoutSeconds = 60) {
    $command = escapeshellarg(PHP_BINARY);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, PROJECT_ROOT);
    if (!is_resource($process)) {
        fail("Could not start a child PHP process: $command");
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $stdout = strip_startup_warnings($stdout);
    $stderr = strip_startup_warnings($stderr);

    return [
        'code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'output' => trim($stdout . "\n" . $stderr)
    ];
}

/**
 * Drop PHP's extension-loading complaints, which are an artefact of the local
 * php.ini rather than anything the script under test produced.
 */
function strip_startup_warnings($text) {
    $kept = [];
    foreach (preg_split('/\r?\n/', (string)$text) as $line) {
        if (preg_match('/^(Failed loading|Warning: Failed loading Zend extension)/', $line)) {
            continue;
        }
        $kept[] = $line;
    }
    return implode("\n", $kept);
}

/* ------------------------------------------------- built-in web server helper */

/**
 * Start PHP's built-in server so a guard can be exercised under a non-CLI SAPI.
 *
 * Returns null when the server cannot be started or never becomes reachable, which
 * lets the caller skip instead of hanging.
 */
function start_web_server($port, $attempts = 20) {
    $command = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . (int)$port . ' -t ' . escapeshellarg(PROJECT_ROOT);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    // bypass_shell means the handle refers to php.exe itself rather than a cmd.exe
    // wrapper, so it can actually be terminated later.
    $options = DIRECTORY_SEPARATOR === '\\' ? ['bypass_shell' => true] : [];

    $process = @proc_open($command, $descriptors, $pipes, PROJECT_ROOT, null, $options);
    if (!is_resource($process)) {
        return null;
    }

    $status = proc_get_status($process);
    $server = [
        'process' => $process,
        'pipes' => $pipes,
        'pid' => $status['pid'] ?? null,
        'port' => (int)$port
    ];

    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        usleep(150000);
        if (http_get_body("http://127.0.0.1:$port/") !== false) {
            return $server;
        }
    }

    stop_web_server($server);
    return null;
}

function http_get_body($url, $timeoutSeconds = 2) {
    return @file_get_contents($url, false, stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => $timeoutSeconds]
    ]));
}

/**
 * Shut the server down.
 *
 * On Windows proc_terminate() can leave the interpreter alive, and proc_close()
 * then blocks forever waiting on it, so the process tree is killed outright.
 */
function stop_web_server($server) {
    if (!$server) {
        return;
    }

    foreach ($server['pipes'] as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    if (DIRECTORY_SEPARATOR === '\\' && !empty($server['pid'])) {
        @exec('taskkill /F /T /PID ' . (int)$server['pid'] . ' 2>&1');
    } else {
        proc_terminate($server['process']);
    }

    proc_close($server['process']);
}

/* ---------------------------------------------------- temporary file tracking */

$GLOBALS['__test_paths'] = [];

/**
 * Register a path for deletion once the current test finishes, so a failing
 * assertion cannot leave scratch files behind in temp/ or public/media/videos/.
 */
function track_path($path) {
    $GLOBALS['__test_paths'][] = $path;
    return $path;
}

function cleanup_tracked_paths() {
    foreach (array_reverse($GLOBALS['__test_paths']) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $GLOBALS['__test_paths'] = [];
}

/**
 * A video id that matches Playlist::VIDEO_ID_PATTERN but cannot collide with a
 * real YouTube id already in the queue.
 */
function test_video_id($suffix = '') {
    return 'zzTest' . ($suffix !== '' ? $suffix : 'Aa') . '01';
}

/**
 * Write a worker progress file.
 *
 * reconcileDownload() takes the newest of the file's mtime and its ts field as the
 * last sign of life, so a file that is meant to look stale has to be backdated on
 * disk as well as in its payload.
 */
function write_progress_file($videoId, array $payload, $ageSeconds = 0) {
    $path = project_path('temp/progress_' . $videoId . '.json');
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, json_encode($payload));

    if ($ageSeconds > 0) {
        touch($path, time() - $ageSeconds);
        clearstatcache(true, $path);
    }

    return track_path($path);
}

function write_fake_video($videoId, $contents = 'not a real mp4') {
    $path = project_path('public/media/videos/' . $videoId . '.mp4');
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, $contents);
    return track_path($path);
}

/* --------------------------------------------------------------- database ---- */

/**
 * Tests that need a database run against a scratch schema, never the real one.
 * The name is asserted to end in _test before any DDL is issued.
 */
define('TEST_DB_SUFFIX', '_test');

function test_db_credentials() {
    $env = [];
    $envFile = project_path('.env');
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $env[trim($parts[0])] = trim($parts[1]);
            }
        }
    }

    return [
        'host' => $env['DB_HOST'] ?? 'localhost',
        'port' => $env['DB_PORT'] ?? '3306',
        'name' => ($env['DB_NAME'] ?? 'karaoke_admin') . TEST_DB_SUFFIX,
        'user' => $env['DB_USER'] ?? 'root',
        'pass' => $env['DB_PASS'] ?? ''
    ];
}

/**
 * Connect to (and if needed create) the scratch database, then make
 * Database::getInstance() hand it out so the models under test use it.
 *
 * Skips the calling test when no MySQL server is reachable, which keeps the
 * suite usable on a machine that only has PHP.
 */
function test_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $credentials = test_db_credentials();
    if (substr($credentials['name'], -strlen(TEST_DB_SUFFIX)) !== TEST_DB_SUFFIX) {
        fail('Refusing to run database tests: scratch database name must end in ' . TEST_DB_SUFFIX);
    }

    try {
        $dsn = "mysql:host={$credentials['host']};port={$credentials['port']};charset=utf8mb4";
        $connection = new PDO($dsn, $credentials['user'], $credentials['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $connection->exec("CREATE DATABASE IF NOT EXISTS `{$credentials['name']}` CHARACTER SET utf8mb4");
        $connection->exec("USE `{$credentials['name']}`");
    } catch (PDOException $e) {
        // Either no server at all, or a user without rights to create the scratch
        // schema — which is the likely case on a production host. Both only mean the
        // database-backed cases cannot run here, so they skip rather than fail.
        skip_test('no scratch database available (' . $e->getMessage() . ')');
    }

    test_db_create_tables($connection);
    test_db_inject($connection);

    $pdo = $connection;
    return $pdo;
}

/**
 * The tables the download-state tests touch, with the same definitions the
 * migration creates. Kept narrow on purpose: this is not a copy of the schema.
 */
function test_db_create_tables(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `playlist` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id VARCHAR(10) NOT NULL DEFAULT 'default',
        video_id VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        user VARCHAR(255) NOT NULL,
        added_at INT NOT NULL,
        downloading BOOLEAN DEFAULT FALSE,
        download_failed TINYINT(1) NOT NULL DEFAULT 0,
        download_error VARCHAR(255) NULL,
        local_path VARCHAR(255) NULL,
        sort_order INT DEFAULT 0,
        INDEX (group_id),
        UNIQUE KEY (group_id, video_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
        id VARCHAR(50) PRIMARY KEY,
        group_id VARCHAR(10) NOT NULL DEFAULT 'default',
        type VARCHAR(50) NOT NULL,
        timestamp INT NOT NULL,
        data LONGTEXT,
        INDEX (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `groups` (
        id VARCHAR(10) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        admin_username VARCHAR(255) NOT NULL,
        admin_pin VARCHAR(255) NOT NULL,
        access_code VARCHAR(255) NULL,
        duration_type VARCHAR(20) NULL,
        valid_from INT NULL,
        valid_to INT NULL,
        created_at INT NULL,
        allow_fallback TINYINT(1) NOT NULL DEFAULT 0,
        must_have_words TEXT NULL,
        must_not_have_words TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Point the Database singleton at the scratch connection without calling its
 * constructor, which would read .env and open the production database.
 */
function test_db_inject(PDO $pdo) {
    require_once project_path('app/Services/Database.php');

    $class = new ReflectionClass('Database');
    $instance = $class->newInstanceWithoutConstructor();

    $pdoProperty = $class->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdoProperty->setValue($instance, $pdo);

    $singleton = $class->getProperty('instance');
    $singleton->setAccessible(true);
    $singleton->setValue(null, $instance);
}

function test_db_reset() {
    $pdo = test_db();
    $pdo->exec('DELETE FROM `playlist`');
    $pdo->exec('DELETE FROM `activity_logs`');
    return $pdo;
}

/**
 * Insert a queue row directly, bypassing add() so nothing is downloaded.
 */
function insert_playlist_row(array $overrides = []) {
    $row = array_merge([
        'group_id' => 'default',
        'video_id' => test_video_id(),
        'title' => 'Test track',
        'user' => 'tester',
        'added_at' => time(),
        'downloading' => 1,
        'download_failed' => 0,
        'download_error' => null,
        'local_path' => null,
        'sort_order' => 0
    ], $overrides);

    test_db()->prepare(
        'INSERT INTO `playlist` (group_id, video_id, title, user, added_at, downloading, download_failed, download_error, local_path, sort_order)
         VALUES (:group_id, :video_id, :title, :user, :added_at, :downloading, :download_failed, :download_error, :local_path, :sort_order)'
    )->execute($row);

    return $row;
}

function fetch_playlist_row($videoId, $groupId = 'default') {
    $statement = test_db()->prepare(
        'SELECT * FROM `playlist` WHERE group_id = ? AND video_id = ? LIMIT 1'
    );
    $statement->execute([$groupId, $videoId]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function fetch_activity_logs($type = null) {
    $pdo = test_db();
    if ($type === null) {
        return $pdo->query('SELECT * FROM `activity_logs` ORDER BY timestamp ASC')->fetchAll();
    }
    $statement = $pdo->prepare('SELECT * FROM `activity_logs` WHERE type = ? ORDER BY timestamp ASC');
    $statement->execute([$type]);
    return $statement->fetchAll();
}
