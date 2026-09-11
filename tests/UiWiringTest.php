<?php
/**Testfile: UiWiringTest*/
/**
 * The wiring between the new backend and the interface: routes, controller methods,
 * the retry button in the admin queue and the Informations modal in the superadmin
 * sidebar.
 *
 * There is no JavaScript test runner in this project, so the front-end cases assert
 * against the source. They are deliberately narrow — each one pins a single wire
 * that, if cut, breaks silently in the browser rather than failing loudly.
 */

test('the retry and system-info routes are registered', function () {
    $source = read_project_file('index.php');

    assert_contains("case 'api/retry_download':", $source, 'The retry route is missing');
    assert_contains("case 'superadmin/system_info':", $source, 'The system-info route is missing');

    // Each route must reach the right controller method.
    assert_matches(
        "/case 'api\/retry_download':.*?ApiController\(\).*?retryDownload\(\)/s",
        $source,
        'api/retry_download is not wired to ApiController::retryDownload'
    );
    assert_matches(
        "/case 'superadmin\/system_info':.*?SuperAdminController\(\).*?systemInfo\(\)/s",
        $source,
        'superadmin/system_info is not wired to SuperAdminController::systemInfo'
    );
});

test('the controller methods the routes call exist and are public', function () {
    require_once PROJECT_ROOT . '/app/Controllers/SuperAdminController.php';
    require_once PROJECT_ROOT . '/app/Controllers/ApiController.php';

    foreach ([['SuperAdminController', 'systemInfo'], ['ApiController', 'retryDownload']] as $target) {
        list($class, $method) = $target;
        assert_true(method_exists($class, $method), "$class::$method is missing");

        $reflection = new ReflectionMethod($class, $method);
        assert_true($reflection->isPublic(), "$class::$method must be public to be routable");
    }
});

test('the system-info endpoint is behind the superadmin guard', function () {
    $source = read_project_file('app/Controllers/SuperAdminController.php');

    assert_matches(
        '/!isset\(\$_SESSION\[.user.\]\).*?!\$_SESSION\[.is_superadmin.\]/s',
        $source,
        'The superadmin guard has been weakened'
    );
});

test('the retry endpoint checks auth before touching the queue', function () {
    $source = read_project_file('app/Controllers/ApiController.php');

    $start = strpos($source, 'public function retryDownload()');
    assert_true($start !== false, 'retryDownload is missing');
    $body = substr($source, $start, 900);

    assert_contains('$this->checkAuth()', $body, 'retryDownload no longer checks auth');

    $authPosition = strpos($body, '$this->checkAuth()');
    $workPosition = strpos($body, '$this->playlistModel->retryDownload(');
    assert_true($workPosition !== false, 'retryDownload does not reach the playlist model');
    assert_true(
        $authPosition < $workPosition,
        'The auth check must come before the retry is performed'
    );
    assert_contains("'videoId is required'", $body, 'A missing videoId should be rejected with 400');
});

/* -------------------------------------------------------- admin queue (retry) */

test('the queue renders a failed download instead of an endless hourglass', function () {
    $source = read_project_file('public/js/admin.js');

    assert_contains('video.download_failed', $source, 'The failed state is not rendered');
    assert_contains('failed-badge', $source, 'The failed badge class is missing');
    assert_contains('retry-icon', $source, 'The retry button is missing');
    assert_contains('Retry download', $source, 'The retry button lost its tooltip');
});

test('the queue distinguishes adaptive stream downloads', function () {
    $source = read_project_file('public/js/admin.js');

    assert_contains("data.status === 'adaptive'", $source);
    assert_contains("' ⏳⏳'", $source);
});

test('the saved badge reads local_path, not the old local_file key', function () {
    $source = read_project_file('public/js/admin.js');

    // getAll() sends local_path. Reading local_file meant the badge never appeared.
    assert_contains('video.local_path', $source, 'The saved badge no longer reads local_path');
    assert_not_contains('video.local_file', $source, 'The stale local_file key is back');
});

test('the retry button posts to the retry endpoint and refreshes the queue', function () {
    $source = read_project_file('public/js/admin.js');

    $start = strpos($source, 'function retryDownload(');
    assert_true($start !== false, 'The retryDownload function is missing');
    $body = substr($source, $start, 900);

    assert_contains('api/retry_download', $body, 'retryDownload posts to the wrong endpoint');
    assert_contains('fetchPlaylist()', $body, 'The queue is not refreshed after a retry');
});

test('the failed badge and retry button are styled', function () {
    $source = read_project_file('public/css/admin.css');

    assert_contains('.failed-badge', $source);
    assert_contains('.retry-icon', $source);
});

/* ------------------------------------------------ superadmin Informations modal */

test('the Informations entry sits inside the Management dropdown', function () {
    $source = read_project_file('views/superadmin/groups.php');

    $start = strpos($source, '<div class="dropdown-content">');
    assert_true($start !== false, 'The Management dropdown could not be found');
    $end = strpos($source, '</div>', $start);
    $dropdown = substr($source, $start, $end - $start);

    assert_contains('Informations', $dropdown, 'The Informations entry is not inside the dropdown');
    assert_contains('showInfoModal()', $dropdown, 'The Informations entry does not open the modal');
});

test('the Informations modal and its handlers exist', function () {
    $source = read_project_file('views/superadmin/groups.php');

    assert_contains('id="infoModal"', $source, 'The modal markup is missing');
    assert_contains('function showInfoModal()', $source);
    assert_contains('function hideInfoModal()', $source);
    assert_contains('function loadSystemInfo()', $source);
    assert_contains('function renderSystemInfo(', $source);

    // Clicking the backdrop must close it, like the other two modals.
    $start = strpos($source, 'window.onclick = function(event)');
    assert_true($start !== false, 'The outside-click handler is missing');
    assert_contains('hideInfoModal()', substr($source, $start, 500), 'The modal cannot be closed by clicking outside');
});

test('the modal fetches the system-info endpoint', function () {
    $source = read_project_file('views/superadmin/groups.php');

    $start = strpos($source, 'function loadSystemInfo()');
    $body = substr($source, $start, 700);

    assert_contains('/superadmin/system_info', $body, 'The modal calls the wrong endpoint');
    assert_contains('basePath', $body, 'The endpoint URL must respect the base path');
});

test('the modal writes runtime values as text, never as markup', function () {
    $source = read_project_file('views/superadmin/groups.php');

    $start = strpos($source, 'function renderSystemInfo(');
    assert_true($start !== false, 'renderSystemInfo is missing');
    $end = strpos($source, "\n        function showEditModal", $start);
    $body = substr($source, $start, $end - $start);

    // yt-dlp error text and file paths are arbitrary output from another program;
    // they are placed with textContent so they can never be parsed as HTML.
    assert_contains('textContent', $body);

    // Clearing the container with innerHTML = '' is fine; assigning anything else to
    // it is not, so every occurrence must be the empty-string form.
    $allAssignments = preg_match_all('/innerHTML\s*=/', $body);
    $clearingOnly = preg_match_all("/innerHTML\s*=\s*''\s*;/", $body);
    assert_same(
        $allAssignments,
        $clearingOnly,
        'renderSystemInfo may only assign innerHTML to clear the container'
    );
});

test('the modal has styling for its rows and states', function () {
    $source = read_project_file('public/css/superadmin.css');

    foreach (['.system-info', '.info-group', '.info-row', '.info-label', '.info-value', '.info-notice'] as $selector) {
        assert_contains($selector, $source, "Missing style for $selector");
    }
    assert_contains('.dropdown-info', $source, 'The dropdown entry loses its icon alignment without this rule');
});

/* ------------------------------------------------------------------ test folder */

test('the tests folder is not reachable over HTTP', function () {
    // The front-controller rewrite only fires for paths that are not real files, so
    // every script in here would otherwise be executable from a browser.
    $htaccess = read_project_file('tests/.htaccess');

    assert_contains('Require all denied', $htaccess);
});

test('sensitive files in the document root are denied', function () {
    // Same trap as the tests folder: these all exist on disk, so Apache serves or runs
    // them directly and the front controller never sees the request. .env is the one
    // that hurts — Apache does not know the extension and hands over the credentials
    // as plain text.
    $htaccess = read_project_file('.htaccess');

    $start = strpos($htaccess, '<FilesMatch');
    assert_true($start !== false, 'The deny block is gone from .htaccess');
    $end = strpos($htaccess, '</FilesMatch>', $start);
    $block = substr($htaccess, $start, $end - $start);

    assert_contains('Require all denied', $block);

    $pattern = null;
    if (preg_match('/<FilesMatch\s+"([^"]+)"/', $block, $matches)) {
        $pattern = '/' . str_replace('/', '\/', $matches[1]) . '/';
    }
    assert_true($pattern !== null, 'The FilesMatch pattern could not be read');

    foreach (['.htaccess', '.env', 'debug_invidious.php', 'debug_video_details.php',
              'migrate_json_to_mysql.php', 'composer.json', 'README.md'] as $name) {
        assert_matches($pattern, $name, "$name is reachable over HTTP");
    }

    // The front controller and the assets it serves must stay reachable.
    foreach (['index.php', 'admin.js', 'admin.css'] as $name) {
        assert_true(preg_match($pattern, $name) === 0, "$name must not be denied");
    }
});

test('the runner refuses to execute outside the command line', function () {
    $source = read_project_file('tests/run.php');
    $bootstrap = read_project_file('tests/bootstrap.php');

    assert_contains("PHP_SAPI !== 'cli'", $bootstrap, 'The CLI-only guard is missing from bootstrap.php');
    assert_contains('bootstrap.php', $source, 'run.php must load bootstrap.php, which carries the guard');
});
