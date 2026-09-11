<?php
/**Testfile: DownloadWorkerTest*/
/**
 * download_worker.php.
 *
 * The worker is a script with side effects at include time, so it is exercised in a
 * child process. None of these tests may pass it a genuinely valid video id: that
 * would start a real download from YouTube.
 */

test('the worker refuses to run without a video id', function () {
    $result = run_php([project_path('download_worker.php')]);

    assert_contains('Usage: php download_worker.php', $result['output']);
});

test('the worker rejects video ids that are unsafe on a command line', function () {
    // The id arrives from a guest-supplied URL and ends up in an exec() string, so
    // anything outside the YouTube id alphabet has to be refused outright.
    $unsafe = [
        'abc',                        // too short
        str_repeat('a', 21),          // too long
        'abc; rm -rf /',              // command chaining
        'abc$(whoami)',               // command substitution
        'abc`id`',                    // backticks
        '../../etc/passwd',           // traversal
        'abc def',                    // whitespace
        'abc"quote',                  // quote breakout
        "abc'quote",
        'abc|tee'
    ];

    foreach ($unsafe as $videoId) {
        $result = run_php([project_path('download_worker.php'), $videoId]);
        assert_contains(
            'Invalid video id.',
            $result['output'],
            "The worker accepted an unsafe video id: '$videoId'"
        );
    }
});

test('the worker cannot be executed over the web', function () {
    // Apache is told to deny it, but the script carries its own guard so a
    // misconfigured vhost cannot turn it into a remote download trigger.
    $source = read_project_file('download_worker.php');

    assert_matches(
        "/if\s*\(\s*PHP_SAPI\s*!==\s*'cli'\s*\)/",
        $source,
        'The CLI-only guard has been removed from download_worker.php'
    );
    assert_contains('http_response_code(403)', $source);

    // Prove the guard actually fires under a non-CLI SAPI.
    $port = 8419;
    $server = start_web_server($port);
    if ($server === null) {
        skip_test("could not start a built-in web server on port $port");
    }

    try {
        $body = http_get_body("http://127.0.0.1:$port/download_worker.php");
        assert_true($body !== false, 'The worker URL could not be requested');
        assert_contains('must be run from the command line', $body);
    } finally {
        stop_web_server($server);
    }
});

test('the worker writes a starting marker before touching the database', function () {
    // That overwrite of the caller's "spawning" marker is the only evidence that the
    // interpreter really launched, which is what reconcileDownload() keys off.
    $source = read_project_file('download_worker.php');

    $markerPosition = strpos($source, "'status' => 'starting'");
    $databasePosition = strpos($source, "require_once __DIR__ . '/app/Services/Database.php'");

    assert_true($markerPosition !== false, 'The starting marker is gone');
    assert_true($databasePosition !== false, 'The database include is gone');
    assert_true(
        $markerPosition < $databasePosition,
        'The starting marker must be written before any database work, or a DB failure looks like a dead worker'
    );
});

test('summariseYtDlpError prefers the real error over the version warning', function () {
    load_function_copy('download_worker.php', 'summariseYtDlpError');

    // Exactly what yt-dlp printed in production: the useful line is last.
    $stderr = "WARNING: You are using an outdated version of yt-dlp; your version is older than 90 days\n"
            . "ERROR: [youtube] ZZZZZZZZZZZ: Video unavailable\n";

    assert_same('ERROR: [youtube] ZZZZZZZZZZZ: Video unavailable', summariseYtDlpError($stderr));
});

test('summariseYtDlpError falls back to the last meaningful line', function () {
    load_function_copy('download_worker.php', 'summariseYtDlpError');

    $stderr = "some progress noise\n\nsomething odd happened\n\n";
    assert_same('something odd happened', summariseYtDlpError($stderr));
});

test('summariseYtDlpError handles empty and whitespace-only output', function () {
    load_function_copy('download_worker.php', 'summariseYtDlpError');

    assert_same('', summariseYtDlpError(''));
    assert_same('', summariseYtDlpError("\n \r\n\t\n"));
    assert_same('', summariseYtDlpError(null));
});

test('summariseYtDlpError copes with Windows line endings', function () {
    load_function_copy('download_worker.php', 'summariseYtDlpError');

    $stderr = "WARNING: old version\r\nERROR: [youtube] unavailable\r\n";
    assert_same('ERROR: [youtube] unavailable', summariseYtDlpError($stderr));
});

test('the worker reports every outcome to the activity log', function () {
    // "It failed silently" was the original bug. Each terminal path must log.
    $source = read_project_file('download_worker.php');

    foreach (["'started'", "'success'", "'failed'", "'aborted'"] as $status) {
        assert_contains($status, $source, "The worker no longer logs the $status outcome");
    }
    assert_contains('register_shutdown_function', $source, 'A worker that dies mid-run would log nothing');
    assert_contains('markFailed', $source, 'A failure must be written back to the queue row');
});

test('the worker quotes every value it puts on the command line', function () {
    $source = read_project_file('download_worker.php');

    assert_contains('escapeshellarg', $source, 'The yt-dlp command line is no longer escaped');
    // realpath keeps a relative ../.. path from breaking the quoted form on Windows.
    assert_contains('realpath', $source);
});

test('the worker falls back to separate video and audio formats', function () {
    $source = read_project_file('download_worker.php');

    assert_contains(
        'best[ext=mp4]/best/bestvideo[ext=mp4]+bestaudio[ext=m4a]/bestvideo+bestaudio',
        $source,
        'Videos without a pre-merged format would fail instead of using adaptive streams'
    );
    assert_contains('--merge-output-format mp4', $source, 'Adaptive streams must be merged into the expected MP4 file');
});
