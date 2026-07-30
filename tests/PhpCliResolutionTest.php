<?php
/**
 * SystemCheck::resolvePhpCli() and friends.
 *
 * This is the code that fixed the permanent-hourglass bug: the app used to hand
 * download_worker.php to PHP_BINARY, which under PHP-FPM is /usr/sbin/php-fpm8.3.
 * FPM accepts a script argument and silently ignores it, so the worker never ran.
 * The tests below lock in the two properties that matter: a resolved binary must
 * really execute a script, and anything that looks like FPM/CGI must be rejected.
 */

require_once PROJECT_ROOT . '/app/Services/SystemCheck.php';

/**
 * resolvePhpCli() memoises its answer for the request, so tests that care about the
 * probing itself clear the cache first.
 */
function reset_php_cli_cache() {
    set_private_static('SystemCheck', 'phpCliResolution', null);
}

test('resolvePhpCli returns a binary that can actually execute a script', function () {
    reset_php_cli_cache();
    $resolution = SystemCheck::resolvePhpCli();

    assert_true(!empty($resolution['binary']), 'No PHP CLI binary was resolved at all');

    // The exact failure from production: php-fpm accepted the script argument and
    // ignored it, so asserting the script's own output is the only real proof.
    $token = 'php_cli_ok_' . getmypid();
    $script = track_path(project_path('temp/cli_probe_' . getmypid() . '.php'));
    if (!is_dir(dirname($script))) {
        @mkdir(dirname($script), 0775, true);
    }
    file_put_contents($script, "<?php echo '$token';");

    $output = [];
    $code = null;
    exec(escapeshellarg($resolution['binary']) . ' ' . escapeshellarg($script) . ' 2>&1', $output, $code);

    assert_same(0, $code, 'The resolved binary did not run the script cleanly');
    assert_contains($token, implode("\n", $output), 'The resolved binary ignored the script argument');
});

test('the resolved binary reports the cli SAPI', function () {
    reset_php_cli_cache();
    $resolution = SystemCheck::resolvePhpCli();
    if (empty($resolution['binary'])) {
        fail('No PHP CLI binary was resolved');
    }

    $output = [];
    exec(escapeshellarg($resolution['binary']) . ' -r ' . escapeshellarg('echo PHP_SAPI;') . ' 2>&1', $output);

    assert_contains('cli', implode('', $output), 'The resolved binary is not the CLI SAPI');
});

test('verifyPhpCli rejects a binary that is not a PHP interpreter', function () {
    $fake = track_path(project_path('temp/php-fpm8.3'));
    if (!is_dir(dirname($fake))) {
        @mkdir(dirname($fake), 0775, true);
    }
    file_put_contents($fake, "not an interpreter\n");

    $result = call_private('SystemCheck', 'verifyPhpCli', [$fake]);

    assert_array_has_keys(['ok', 'detail'], $result);
    assert_false($result['ok'], 'An FPM-named non-interpreter was accepted as a CLI binary');
});

test('verifyPhpCli rejects a path that does not exist', function () {
    $missing = project_path('temp/definitely_not_here_' . getmypid());
    $result = call_private('SystemCheck', 'verifyPhpCli', [$missing]);

    assert_false($result['ok']);
    assert_same('not found', $result['detail']);
});

test('verifyPhpCli accepts the interpreter running this suite', function () {
    // PHP_BINARY is trustworthy here precisely because the suite runs under CLI.
    $result = call_private('SystemCheck', 'verifyPhpCli', [PHP_BINARY]);
    assert_true($result['ok'], 'The current CLI binary was rejected: ' . $result['detail']);
});

test('PHP_BINARY is only offered as a candidate while running under the cli SAPI', function () {
    $candidates = call_private('SystemCheck', 'getPhpCliCandidates');

    // The suite itself is CLI, so the constant is legitimately trusted and first.
    assert_same(
        str_replace(['/', '\\'], DIRECTORY_SEPARATOR, PHP_BINARY),
        $candidates[0],
        'Under the CLI SAPI the running binary should be tried first'
    );

    // Guard against the check being dropped: the source must gate it on PHP_SAPI,
    // which is a compile-time constant and cannot be varied from inside a test.
    $source = read_project_file('app/Services/SystemCheck.php');
    assert_matches(
        '/if\s*\(\s*PHP_SAPI\s*===\s*\'cli\'\s*&&\s*PHP_BINARY\s*\)/',
        $source,
        'PHP_BINARY must stay gated behind a PHP_SAPI === cli check'
    );
});

test('the candidate list keeps fallbacks beyond PHP_BINARY', function () {
    $candidates = call_private('SystemCheck', 'getPhpCliCandidates');

    assert_true(count($candidates) > 1, 'A single candidate leaves no fallback when it is unusable');
    assert_contains('php', end($candidates), 'The last resort should be a bare php command from PATH');

    // PHP_BINDIR points at the CLI directory even when the running SAPI does not.
    $bindir = rtrim(PHP_BINDIR, '/\\');
    $fromBindir = array_filter($candidates, function ($candidate) use ($bindir) {
        return $bindir !== '' && strpos($candidate, $bindir . DIRECTORY_SEPARATOR) === 0;
    });
    assert_true(!empty($fromBindir), 'No candidate was derived from PHP_BINDIR');
});

test('an FPM binary name yields its stripped sibling as a candidate', function () {
    // Mirrors the /usr/sbin/php-fpm8.3 -> /usr/sbin/php8.3 rule the fix relies on.
    $source = read_project_file('app/Services/SystemCheck.php');
    assert_contains(
        "str_replace(['-fpm', '-cgi'], '', \$base)",
        $source,
        'The FPM/CGI sibling derivation is missing from the candidate builder'
    );
});

test('candidates are de-duplicated and use the platform separator', function () {
    $candidates = call_private('SystemCheck', 'getPhpCliCandidates');

    assert_same(count($candidates), count(array_unique($candidates)), 'Duplicate candidates would be probed twice');

    $wrongSeparator = DIRECTORY_SEPARATOR === '/' ? '\\' : '/';
    foreach ($candidates as $candidate) {
        assert_not_contains($wrongSeparator, $candidate, 'Candidate uses the wrong directory separator');
    }
});

test('Windows fallback interpreters are ordered newest version first', function () {
    if (DIRECTORY_SEPARATOR !== '\\') {
        skip_test('Windows-only ordering rule');
    }

    $candidates = call_private('SystemCheck', 'getPhpCliCandidates');
    $wampVersions = [];
    foreach ($candidates as $candidate) {
        if (preg_match('/wamp64.bin.php.php([\d.]+).php\.exe$/i', $candidate, $match)) {
            $wampVersions[] = $match[1];
        }
    }

    if (count($wampVersions) < 3) {
        skip_test('only one WAMP PHP version installed, nothing to order');
    }

    // The first entry is the exact version serving the site; the glob fallbacks
    // that follow must descend, so the worker never gets an older major than the web.
    $fallbacks = array_slice($wampVersions, 1);
    $sorted = $fallbacks;
    usort($sorted, function ($a, $b) {
        return version_compare($b, $a);
    });

    assert_same($sorted, $fallbacks, 'Fallback PHP versions are not ordered newest first');
});

test('resolution is cached for the rest of the request', function () {
    reset_php_cli_cache();
    $first = SystemCheck::resolvePhpCli();
    $second = SystemCheck::resolvePhpCli();

    assert_same($first, $second, 'resolvePhpCli should not re-probe within one request');
});

test('resolution carries the diagnostics the spawn log needs', function () {
    reset_php_cli_cache();
    $resolution = SystemCheck::resolvePhpCli();

    assert_array_has_keys(['binary', 'attempts', 'sapi', 'php_binary'], $resolution);
    assert_same(PHP_SAPI, $resolution['sapi']);
    assert_same(PHP_BINARY, $resolution['php_binary']);
    assert_true(is_array($resolution['attempts']) && count($resolution['attempts']) > 0);

    foreach ($resolution['attempts'] as $attempt) {
        assert_array_has_keys(['candidate', 'result'], $attempt);
    }
});

test('the interpreter probe cannot hang on an interactive candidate', function () {
    // An early version of verifyPhpCli() executed cmd.exe, which opened a shell and
    // waited for input, hanging the web request. stdin must stay redirected.
    $source = read_project_file('app/Services/SystemCheck.php');
    $nullDevices = preg_match_all("/'NUL'\s*:\s*'\/dev\/null'/", $source);

    assert_true($nullDevices >= 1, 'The candidate probe no longer redirects stdin from the null device');
    assert_matches(
        "/escapeshellarg\(\\\$candidate\)\s*\.\s*' -r '.*?' < '/s",
        $source,
        'verifyPhpCli must keep redirecting stdin when probing a candidate'
    );
});
