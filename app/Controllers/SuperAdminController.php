<?php

require_once 'app/Models/Group.php';

class SuperAdminController {
    private $groupModel;

    public function __construct($bypassAuth = false) {
        if (!$bypassAuth) {
            if (!isset($_SESSION['user']) || !isset($_SESSION['is_superadmin']) || !$_SESSION['is_superadmin']) {
                header('Location: login');
                exit;
            }
        }
        $this->groupModel = new Group();
    }

    public function index() {
        // Log connection for superadmin if not already logged in this session
        if (!isset($_SESSION['superadmin_logged'])) {
            require_once 'app/Models/ClientLog.php';
            $clientLog = new ClientLog();
            $identity = $_SESSION['user'] ?? 'superadmin';
            $clientLog->logConnection('system', 'superadmin', $identity);
            $_SESSION['superadmin_logged'] = true;
        }

        global $basePath;
        require_once 'app/Models/Settings.php';
        $groups = $this->groupModel->getAll();
        // Ensure $groups is always an array for the view
        if (!is_array($groups)) $groups = [];

        // Enrich groups with access code from settings if table column is empty
        foreach ($groups as &$group) {
            if (empty($group['access_code'])) {
                $settings = new Settings($group['id']);
                $guestCodes = $settings->get('guest_codes');
                if ($guestCodes === null) {
                    $legacyCode = $settings->get('guest_code');
                    if ($legacyCode) {
                        $group['access_code'] = $legacyCode;
                    }
                } elseif (is_array($guestCodes) && !empty($guestCodes)) {
                    $group['access_code'] = $guestCodes[0];
                }
            }
        }
        unset($group);

        $data = ['basePath' => $basePath];
        extract($data);
        require_once 'views/superadmin/groups.php';
    }

    public function contact() {
        global $basePath;
        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['contact_email'] ?? '';
            $settings->set('contact_email', $email);
            $success = true;
        }

        $contactEmail = $settings->get('contact_email', 'contact@devdensan.com');
        $data = [
            'basePath' => $basePath,
            'contactEmail' => $contactEmail,
            'success' => $success ?? null
        ];
        extract($data);
        require_once 'views/superadmin/contact.php';
    }

    public function accessKeys() {
        global $basePath;
        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $keys = $_POST['access_keys'] ?? '';
            $settings->set('access_keys_bank', $keys);
            $success = true;
        }

        $accessKeys = $settings->get('access_keys_bank', '');
        $data = [
            'basePath' => $basePath,
            'accessKeys' => $accessKeys,
            'success' => $success ?? null
        ];
        extract($data);
        require_once 'views/superadmin/access_keys.php';
    }

    public function logs() {
        global $basePath;
        require_once 'app/Models/SystemLog.php';
        require_once 'app/Models/VisitLog.php';
        $db = Database::getInstance();
        
        // We want to see ALL logs from activity_logs table across all groups
        $sql = "SELECT * FROM `activity_logs` ORDER BY timestamp DESC LIMIT 1000";
        $logs = $db->fetchAll($sql);

        $visitLog = new VisitLog();
        $visits = $visitLog->getVisits(1000);
        
        $data = ['basePath' => $basePath, 'logs' => $logs, 'visits' => $visits];
        extract($data);
        require_once 'views/superadmin/logs.php';
    }

    /**
     * Runtime versions behind the "Informations" modal: which yt-dlp is executed and
     * which PHP builds serve the site and run the background worker.
     */
    public function systemInfo() {
        header('Content-Type: application/json');
        require_once 'app/Services/SystemCheck.php';

        echo json_encode([
            'yt_dlp' => SystemCheck::getYtDlpInfo(),
            'php' => SystemCheck::getPhpInfo(),
            'os' => PHP_OS_FAMILY . ' — ' . php_uname('s') . ' ' . php_uname('r'),
            'server_time' => date('Y-m-d H:i:s')
        ]);
    }

    public function deleteVisitLog() {
        $visitId = $_POST['visit_id'] ?? null;
        if ($visitId) {
            require_once 'app/Models/VisitLog.php';
            $visitLog = new VisitLog();
            $visitLog->deleteVisit($visitId);
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'superadmin/logs'));
        exit;
    }

    public function clients() {
        global $basePath;
        require_once 'app/Models/ClientLog.php';
        require_once 'app/Models/Group.php';
        
        $clientLog = new ClientLog();
        $groupModel = new Group();

        $groupId = $_GET['group_id'] ?? null;
        if ($groupId) {
            $group = $groupModel->getById($groupId);
            $clients = $clientLog->getConnectionsByGroup($groupId);
        } else {
            $group = ['name' => 'All Parties'];
            $clients = $clientLog->getAllClients();
        }
        
        $data = [
            'basePath' => $basePath,
            'group' => $group,
            'clients' => $clients,
            'groupId' => $groupId
        ];
        extract($data);
        require_once 'views/superadmin/clients.php';
    }

    public function clearClients() {
        $groupId = $_POST['group_id'] ?? null;
        if (!$groupId) {
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }

        require_once 'app/Models/ClientLog.php';
        $clientLog = new ClientLog();
        $clientLog->clearConnectionsByGroup($groupId);

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    public function deleteClient() {
        $clientId = $_POST['client_id'] ?? null;
        $groupId = $_POST['group_id'] ?? null;
        
        if ($clientId && $groupId) {
            require_once 'app/Models/ClientLog.php';
            $clientLog = new ClientLog();
            $clientLog->deleteClient($clientId, $groupId);
        }
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    public function banClient() {
        $clientId = $_POST['client_id'] ?? null;
        
        if ($clientId) {
            require_once 'app/Models/ClientLog.php';
            $clientLog = new ClientLog();
            $clientLog->banClient($clientId);
        }
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    public function unbanClient() {
        $clientId = $_POST['client_id'] ?? null;
        
        if ($clientId) {
            require_once 'app/Models/ClientLog.php';
            $clientLog = new ClientLog();
            $clientLog->unbanClient($clientId);
        }
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    public function createGroup() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            return;
        }

        $name = $data['name'] ?? '';
        $adminUsername = $data['admin_username'] ?? '';
        $durationType = $data['duration_type'] ?? 'unlimited';
        $validFrom = !empty($data['valid_from']) ? $data['valid_from'] : null;
        $validTo = !empty($data['valid_to']) ? $data['valid_to'] : null;
        $allowFallback = !empty($data['allow_fallback']) ? 1 : 0;
        $accessCode = !empty($data['access_code']) ? strtoupper($data['access_code']) : null;

        if (empty($name) || empty($adminUsername)) {
            echo json_encode(['success' => false, 'message' => 'Name and Admin Username are required']);
            return;
        }

        try {
            $newGroup = $this->groupModel->create($name, $adminUsername, $durationType, $validFrom, $validTo, $allowFallback, '', '', $accessCode);
            
            if ($newGroup) {
                $id = $newGroup['id'];

                // Log group creation
                require_once 'app/Models/SystemLog.php';
                $sysLog = new SystemLog($id);
                $sysLog->log('group_created', [
                    'name' => $name,
                    'admin_username' => $adminUsername,
                    'access_code' => $accessCode
                ]);

                // Sync access_code to Settings if provided
                if ($accessCode) {
                    require_once 'app/Models/Settings.php';
                    $settings = new Settings($id);
                    $settings->update(function($current) use ($accessCode) {
                        $current['guest_codes'] = [$accessCode];
                        unset($current['guest_code']);
                        return $current;
                    });
                }
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create group']);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function deleteGroup() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            return;
        }

        $id = $data['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            return;
        }

        try {
            $success = $this->groupModel->delete($id);
            echo json_encode(['success' => $success]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function updateGroup() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if ($data === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            return;
        }

        $id = $data['id'] ?? '';
        $newId = $data['new_id'] ?? $id;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            return;
        }

        try {
            // Handle ID change if necessary
            if ($newId !== $id) {
                // Validate new ID is unique
                $existing = $this->groupModel->getById($newId);
                if ($existing) {
                    echo json_encode(['success' => false, 'message' => 'New Party ID already exists']);
                    return;
                }

                // ID will be updated in the database by $this->groupModel->update($id, $data)
                // since $data['id'] is now set to $newId below
                $data['id'] = $newId;

                // Update associated database tables
                $tablesToUpdate = ['activity_logs', 'guests', 'player_status', 'playlist', 'settings'];
                foreach ($tablesToUpdate as $table) {
                    $this->groupModel->updateRelatedTable($table, $id, $newId);
                }
            }
            unset($data['new_id']);

            if (isset($data['valid_from']) && $data['valid_from'] === '') $data['valid_from'] = null;
            if (isset($data['valid_to']) && $data['valid_to'] === '') $data['valid_to'] = null;
            if (isset($data['access_code'])) {
                if ($data['access_code'] === '') {
                    $data['access_code'] = null;
                } else {
                    $data['access_code'] = strtoupper($data['access_code']);
                }
            }

            // Filter data to only include valid columns for the groups table
            $validColumns = [
                'id', 'name', 'admin_username', 'admin_pin', 'access_code', 
                'duration_type', 'valid_from', 'valid_to', 'allow_fallback', 
                'must_have_words', 'must_not_have_words'
            ];
            $filteredData = array_intersect_key($data, array_flip($validColumns));

            $success = $this->groupModel->update($id, $filteredData);
            
            // Log access code change if applicable
            if ($success && isset($data['access_code'])) {
                require_once 'app/Models/SystemLog.php';
                $sysLog = new SystemLog($data['id']); // Use updated ID if it changed
                $sysLog->log('access_code_changed', [
                    'new_code' => $data['access_code'],
                    'changed_by' => 'superadmin'
                ]);
            }

            // Sync access_code to Settings if updated
            if ($success && isset($data['access_code'])) {
                require_once 'app/Models/Settings.php';
                $settings = new Settings($data['id']); // Use updated ID if it changed
                $newCode = $data['access_code'];
                
                if ($newCode) {
                    $settings->update(function($current) use ($newCode) {
                        $current['guest_codes'] = [$newCode];
                        unset($current['guest_code']);
                        return $current;
                    });
                } else {
                    $settings->update(function($current) {
                        $current['guest_codes'] = [];
                        unset($current['guest_code']);
                        return $current;
                    });
                }
            }

            echo json_encode(['success' => $success]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function getBellCount() {
        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        $count = (int)$settings->get('bell_counter', 0);
        echo json_encode(['success' => true, 'count' => $count]);
    }

    public function incrementBellCount() {
        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        $count = (int)$settings->get('bell_counter', 0);
        $count++;
        $settings->set('bell_counter', $count);
        echo json_encode(['success' => true, 'count' => $count]);
    }

    public function resetBellCount() {
        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        $settings->set('bell_counter', 0);
        echo json_encode(['success' => true]);
    }

    public function getAccessKeys() {
        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        $rawKeys = $settings->get('access_keys_bank', '');
        
        $lines = explode("\n", $rawKeys);
        $cleanKeys = [];
        foreach ($lines as $line) {
            // Remove comments
            if (strpos($line, '#') !== false) {
                $line = substr($line, 0, strpos($line, '#'));
            }
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $cleanKeys[] = $trimmed;
            }
        }
        
        echo json_encode(['success' => true, 'keys' => $cleanKeys]);
    }

    /* ------------------------------------------------------------- test runner -- */

    /**
     * A runnable test is identified by a marker comment near the top of the file: a doc
     * comment reading "Testfile: UiWiringTest", which tests/UiWiringTest.php carries on
     * its second line.
     *
     * Discovery keys off that marker rather than off the file name, so the folder can
     * hold helpers (bootstrap.php, run.php) without them being offered as tests, and a
     * newly dropped file appears in the list the moment it carries the marker.
     */
    const TESTFILE_MARKER = '/^\s*\/\*+\s*Testfile\s*:\s*([A-Za-z0-9_]+)\s*\*+\//mi';

    /**
     * Every runnable test file, keyed by the name its marker declares.
     *
     * This list is the whitelist used to validate ?file=: anything not in it does not
     * exist as far as the runner is concerned.
     *
     * @return array name => {name, file, path, cases, summary}
     */
    private function discoverTestFiles() {
        $directory = realpath(__DIR__ . '/../../tests');
        if ($directory === false) {
            return [];
        }

        $found = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
            $contents = @file_get_contents($path);
            if ($contents === false || !preg_match(self::TESTFILE_MARKER, $contents, $matches)) {
                continue;
            }

            // The declared name has to agree with the file name, because the name is
            // what gets handed to the runner as its filter. A mismatch would run a
            // different file than the one the button says.
            $name = $matches[1];
            if (strcasecmp($name, basename($path, '.php')) !== 0) {
                continue;
            }

            $found[$name] = [
                'name' => $name,
                'file' => basename($path),
                'path' => $path,
                'cases' => preg_match_all('/^test\(/m', $contents),
                'summary' => $this->readTestSummary($contents)
            ];
        }

        ksort($found);
        return $found;
    }

    /**
     * First sentence of the file's docblock, used as the one-line description in the
     * list. Purely cosmetic: an undocumented file still runs.
     */
    private function readTestSummary($contents) {
        if (!preg_match('/\/\*\*\s*\n(.*?)\*\//s', $contents, $matches)) {
            return '';
        }

        $text = preg_replace('/^\s*\*\s?/m', '', $matches[1]);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '';
        }

        // Prefer a whole first sentence; otherwise trim on a word boundary.
        if (preg_match('/^(.{20,150}?\.)(\s|$)/', $text, $sentence)) {
            return $sentence[1];
        }
        if (mb_strlen($text) > 150) {
            $text = mb_substr($text, 0, 150);
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace > 60) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            return $text . '…';
        }
        return $text;
    }

    /**
     * Resolve a ?file= parameter against the discovered tests.
     *
     * @return array|null the test entry, or null when no such test exists
     */
    private function resolveTestFile($requested) {
        if (!is_string($requested) || trim($requested) === '') {
            return null;
        }

        // basename() strips any path the caller tried to smuggle in; the lookup below
        // is what actually authorises the choice.
        $name = basename(trim($requested), '.php');
        foreach ($this->discoverTestFiles() as $key => $test) {
            if (strcasecmp($key, $name) === 0) {
                return $test;
            }
        }
        return null;
    }

    /**
     * The list of runnable tests, each with a play button.
     */
    public function tests() {
        global $basePath;
        require_once 'app/Services/SystemCheck.php';

        $tests = $this->discoverTestFiles();
        $php = SystemCheck::resolvePhpCli();

        $data = ['basePath' => $basePath, 'tests' => $tests, 'php' => $php];
        extract($data);
        require_once 'views/superadmin/tests.php';
    }

    /**
     * The terminal-style page. It only renders a shell; the output arrives from
     * testsStream() so results can appear while the suite is still running.
     */
    public function testsRun() {
        global $basePath;
        require_once 'app/Services/SystemCheck.php';

        $requested = isset($_GET['file']) ? $_GET['file'] : null;
        $selected = null;

        if ($requested !== null && trim($requested) !== '') {
            $selected = $this->resolveTestFile($requested);
            if ($selected === null) {
                http_response_code(404);
                $data = [
                    'basePath' => $basePath,
                    'requested' => (string)$requested,
                    'tests' => $this->discoverTestFiles()
                ];
                extract($data);
                require_once 'views/superadmin/tests_missing.php';
                return;
            }
        }

        $php = SystemCheck::resolvePhpCli();
        $data = ['basePath' => $basePath, 'selected' => $selected, 'php' => $php];
        extract($data);
        require_once 'views/superadmin/tests_run.php';
    }

    /**
     * Run the suite in a child process and stream its output as newline-delimited
     * JSON, one object per line.
     *
     * The tests refuse to run outside the CLI SAPI on purpose, so this never includes
     * them: it resolves a real CLI interpreter with SystemCheck::resolvePhpCli() — the
     * same function the download worker depends on — and spawns tests/run.php.
     *
     * EventSource is deliberately not used on the other end: it reconnects when a
     * stream ends, which would silently start the whole suite over again.
     */
    public function testsStream() {
        require_once 'app/Services/SystemCheck.php';

        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no'); // keeps a reverse proxy from buffering the run

        // Nothing may sit between an echo and the socket, or the output stops being
        // live and arrives in one lump at the end.
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @ob_implicit_flush(true);
        @set_time_limit(0);
        ignore_user_abort(false);

        $requested = isset($_GET['file']) ? $_GET['file'] : null;
        $filter = null;
        if ($requested !== null && trim($requested) !== '') {
            $test = $this->resolveTestFile($requested);
            if ($test === null) {
                $this->streamEvent(['type' => 'error', 'text' => 'No such test exists.']);
                $this->streamEvent(['type' => 'done', 'code' => 2]);
                return;
            }
            $filter = $test['name'];
        }

        $projectRoot = realpath(__DIR__ . '/../..');
        $runner = $projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run.php';
        if (!is_file($runner)) {
            $this->streamEvent(['type' => 'error', 'text' => 'tests/run.php is missing.']);
            $this->streamEvent(['type' => 'done', 'code' => 2]);
            return;
        }

        $php = SystemCheck::resolvePhpCli();
        if (empty($php['binary'])) {
            $this->streamEvent([
                'type' => 'error',
                'text' => 'No PHP command-line interpreter could be found, so the tests cannot be run.'
            ]);
            $this->streamEvent([
                'type' => 'error',
                'text' => 'Running SAPI: ' . $php['sapi'] . ' — PHP_BINARY: ' . $php['php_binary']
            ]);
            foreach ($php['attempts'] as $attempt) {
                $this->streamEvent([
                    'type' => 'stderr',
                    'text' => '  tried ' . $attempt['candidate'] . ' — ' . $attempt['result']
                ]);
            }
            $this->streamEvent(['type' => 'done', 'code' => 1]);
            return;
        }

        // Two runs at once would share the scratch database and trip over each other,
        // producing failures that have nothing to do with the code. flock releases
        // itself when the process ends, so there is no stale lock to clear.
        $lock = @fopen($projectRoot . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'tests.lock', 'c');
        if ($lock && !@flock($lock, LOCK_EX | LOCK_NB)) {
            $this->streamEvent([
                'type' => 'error',
                'text' => 'A test run is already in progress. Wait for it to finish and try again.'
            ]);
            $this->streamEvent(['type' => 'done', 'code' => 2]);
            return;
        }

        $this->streamEvent([
            'type' => 'start',
            'target' => $filter === null ? 'all tests' : $filter,
            'binary' => $php['binary']
        ]);

        $this->streamTestProcess($php['binary'], $runner, $filter, $projectRoot);

        if ($lock) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /**
     * Spawn the runner and forward its output line by line.
     */
    private function streamTestProcess($binary, $runner, $filter, $projectRoot) {
        $parts = [$binary, $runner];
        if ($filter !== null) {
            $parts[] = $filter;
        }

        // The array form leaves the quoting to PHP, and bypass_shell means the handle
        // refers to the interpreter itself rather than a cmd.exe wrapper — without it
        // the child survives being terminated on Windows.
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $command = PHP_VERSION_ID >= 70400
            ? $parts
            : implode(' ', array_map('escapeshellarg', $parts));
        $options = $isWindows ? ['bypass_shell' => true] : [];

        // stdin comes from the null device: the runner never reads it, and a child that
        // did would otherwise hang this request forever.
        //
        // Output is captured to files rather than pipes, for two reasons that both bite
        // on a long run. stream_select() does not work on Windows anonymous pipes — it
        // supports sockets only — so a pipe reader silently starves partway through
        // while the runner keeps going. And a pipe only reports end-of-file once every
        // process holding its write end is gone; the suite starts a built-in web server
        // of its own, which inherits that handle, so EOF could arrive long after the
        // runner finished. Polling a file has neither problem.
        $stem = $projectRoot . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR
            . 'tests_run_' . getmypid() . '_' . mt_rand(1000, 9999);
        $paths = [1 => $stem . '.out', 2 => $stem . '.err'];

        $descriptors = [
            0 => ['file', $isWindows ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', $paths[1], 'w'],
            2 => ['file', $paths[2], 'w']
        ];

        $pipes = [];
        $process = @proc_open($command, $descriptors, $pipes, $projectRoot, null, $options);
        if (!is_resource($process)) {
            $this->streamEvent([
                'type' => 'error',
                'text' => 'The test runner could not be started (proc_open failed or is disabled).'
            ]);
            $this->streamEvent(['type' => 'done', 'code' => 1]);
            @unlink($paths[1]);
            @unlink($paths[2]);
            return;
        }

        // Closing the tab aborts this script; without this the suite would keep
        // running on the server with nobody reading it. The scratch files go last,
        // because Windows refuses to unlink one the runner still has open.
        $killed = false;
        register_shutdown_function(function () use (&$process, &$killed, $paths) {
            if (!$killed && is_resource($process)) {
                $killed = true;
                self::terminateProcess($process);
            }
            @unlink($paths[1]);
            @unlink($paths[2]);
        });

        $handles = [1 => null, 2 => null];
        $offsets = [1 => 0, 2 => 0];
        $buffers = [1 => '', 2 => ''];

        // Forward every complete line written since the last look. $flush also releases
        // a trailing fragment, which is what the runner leaves behind if it dies
        // mid-line.
        $drain = function ($flush) use ($paths, &$handles, &$offsets, &$buffers) {
            foreach ($paths as $key => $path) {
                if (!is_resource($handles[$key])) {
                    $handles[$key] = @fopen($path, 'rb');
                    if (!is_resource($handles[$key])) {
                        continue;
                    }
                }

                // Seeking also clears the end-of-file flag the previous read set, so the
                // handle keeps seeing bytes appended after it caught up.
                @fseek($handles[$key], $offsets[$key]);
                $chunk = @stream_get_contents($handles[$key]);
                if ($chunk === false) {
                    $chunk = '';
                }
                $offsets[$key] += strlen($chunk);
                $buffers[$key] .= $chunk;

                $type = $key === 2 ? 'stderr' : 'line';
                while (($position = strpos($buffers[$key], "\n")) !== false) {
                    $line = rtrim(substr($buffers[$key], 0, $position), "\r");
                    $buffers[$key] = substr($buffers[$key], $position + 1);
                    $this->streamEvent(['type' => $type, 'text' => $line]);
                }

                if ($flush && $buffers[$key] !== '') {
                    $this->streamEvent(['type' => $type, 'text' => rtrim($buffers[$key], "\r")]);
                    $buffers[$key] = '';
                }
            }
        };

        $completed = false;
        $exitCode = -1;
        $polls = 0;

        while (true) {
            $status = @proc_get_status($process);
            $running = is_array($status) && !empty($status['running']);

            $drain(false);

            if (!$running) {
                // Anything written in the last moments still has to go out.
                $drain(true);
                $completed = true;
                if (is_array($status) && isset($status['exitcode'])) {
                    $exitCode = (int)$status['exitcode'];
                }
                break;
            }

            if (connection_aborted()) {
                break;
            }

            // A quiet stretch still needs the odd write: it is what lets
            // connection_aborted() notice a closed tab, and it keeps any proxy in
            // front of us from timing the response out.
            if (++$polls % 80 === 0) {
                $this->streamEvent(['type' => 'ping']);
            }

            usleep(120000);
        }

        foreach ($handles as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (!$completed) {
            // Nobody is listening any more. The shutdown handler kills the tree and
            // clears the scratch files.
            return;
        }

        $killed = true;
        // proc_get_status already reaped the child, so its exit code is the real one —
        // proc_close returns -1 once that has happened.
        @proc_close($process);
        @unlink($paths[1]);
        @unlink($paths[2]);
        $this->streamEvent(['type' => 'done', 'code' => $exitCode]);
    }

    /**
     * Kill a child process and everything it started.
     *
     * proc_terminate only reaches the immediate child, which on Windows is not enough:
     * proc_close would then block forever waiting on the tree.
     */
    private static function terminateProcess($process) {
        $status = @proc_get_status($process);
        if (DIRECTORY_SEPARATOR === '\\' && !empty($status['pid'])) {
            @exec('taskkill /F /T /PID ' . (int)$status['pid'] . ' 2>&1', $output, $code);
        } else {
            @proc_terminate($process, defined('SIGKILL') ? SIGKILL : 9);
        }
        @proc_close($process);
    }

    /**
     * One newline-delimited JSON object, pushed out immediately.
     */
    private function streamEvent(array $event) {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            // Test output can carry anything, including bytes that are not valid UTF-8.
            // Substituting keeps one bad line from killing the whole stream.
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        echo json_encode($event, $flags) . "\n";
        flush();
    }
}
