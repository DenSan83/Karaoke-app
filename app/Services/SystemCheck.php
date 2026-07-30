<?php

class SystemCheck {
    private static $projectBinPath = __DIR__ . '/../../bin/yt-dlp';
    private static $systemPaths = ['/usr/local/bin/yt-dlp', '/usr/bin/yt-dlp'];

    /** Cached result of resolvePhpCli() so we probe at most once per request. */
    private static $phpCliResolution = null;

    /**
     * Check if yt-dlp is available (system PATH or project bin)
     */
    public static function checkYtDlp() {
        // Check project bin first
        if (file_exists(self::$projectBinPath) && is_executable(self::$projectBinPath)) {
            return true;
        }

        // Check system paths
        foreach (self::$systemPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return true;
            }
        }

        // Try command in PATH
        if (self::isWindows()) {
            $ytDlpExe = __DIR__ . '/../../yt-dlp.exe';
            return file_exists($ytDlpExe);
        } else {
            if (!function_exists('exec') || self::isExecDisabled()) {
                return false;
            }
            exec('which yt-dlp 2>/dev/null', $output, $returnCode);
            return $returnCode === 0;
        }
    }

    /**
     * Check if exec is disabled in php.ini
     */
    private static function isExecDisabled() {
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        return in_array('exec', array_map('trim', $disabledFunctions));
    }

    /**
     * Check if database is initialized and has all required tables and columns
     */
    public static function checkDatabase() {
        try {
            require_once __DIR__ . '/Database.php';
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
        $tables = [
                'groups' => ['id', 'name', 'admin_username', 'admin_pin', 'access_code', 'duration_type', 'valid_from', 'valid_to', 'created_at', 'allow_fallback', 'must_have_words', 'must_not_have_words'],
                'settings' => ['group_id', 'setting_key', 'setting_value', 'updated_at'],
                'guests' => ['id', 'group_id', 'name', 'songs', 'notifications', 'added_at'],
                'playlist' => ['id', 'group_id', 'video_id', 'title', 'user', 'added_at', 'downloading', 'download_failed', 'download_error', 'local_path', 'sort_order'],
                'activity_logs' => ['id', 'group_id', 'type', 'timestamp', 'data'],
                'player_status' => ['group_id', 'command', 'payload', 'command_timestamp', 'current_index', 'state', 'state_timestamp', 'last_updated'],
                'screens' => ['secret_id', 'public_code', 'group_id', 'created_at', 'paired_at'],
                'client_connections' => ['id', 'group_id', 'client_id', 'type', 'identity', 'data', 'created_at', 'last_activity', 'is_online', 'is_banned', 'banned_at']
            ];

            foreach ($tables as $table => $columns) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() === 0) {
                        return false;
                    }
                    
                    // Also check columns
                    $existingColumns = [];
                    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` ");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $existingColumns[] = $row['Field'];
                    }
                    
                    foreach ($columns as $column) {
                        if (!in_array($column, $existingColumns)) {
                            return false;
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Database check failed for table $table: " . $e->getMessage());
                    return false;
                }
            }
            return true;
        } catch (PDOException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Fix database schema by adding missing columns or tables
     */
    public static function fixSchema() {
        try {
            require_once __DIR__ . '/Database.php';
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Re-run migration script which has CREATE TABLE IF NOT EXISTS
            // but we also need to add missing columns.
            if (!isset($GLOBALS['RUN_MIGRATION'])) {
                $GLOBALS['RUN_MIGRATION'] = true;
                require_once __DIR__ . '/../../migrate_json_to_mysql.php';
                if (function_exists('runMigration')) {
                    runMigration();
                }
            }

            // Explicitly check and add missing columns for 'groups' table as requested
            $groupColumns = [
                'access_code' => "ALTER TABLE `groups` ADD COLUMN access_code VARCHAR(255) NULL AFTER admin_pin",
                'allow_fallback' => "ALTER TABLE `groups` ADD COLUMN allow_fallback TINYINT(1) NOT NULL DEFAULT 0",
                'must_have_words' => "ALTER TABLE `groups` ADD COLUMN must_have_words TEXT NULL",
                'must_not_have_words' => "ALTER TABLE `groups` ADD COLUMN must_not_have_words TEXT NULL"
            ];

            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `groups` ");
                $existingColumns = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingColumns[] = $row['Field'];
                }

                foreach ($groupColumns as $col => $sql) {
                    if (!in_array($col, $existingColumns)) {
                        $pdo->exec($sql);
                    }
                }
            } catch (PDOException $e) {
                error_log("Failed to update groups schema: " . $e->getMessage());
            }

            return true;
        } catch (Exception $e) {
            error_log("Failed to fix schema: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the correct yt-dlp path based on what's available
     */
    public static function getYtDlpPath() {
        if (self::isWindows()) {
            return __DIR__ . '/../../yt-dlp.exe';
        }

        // Check project bin first
        if (file_exists(self::$projectBinPath) && is_executable(self::$projectBinPath)) {
            return self::$projectBinPath;
        }

        // Check system paths
        foreach (self::$systemPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Default to command in PATH
        return 'yt-dlp';
    }

    /**
     * Find a PHP binary able to run a CLI script (used to spawn download_worker.php).
     *
     * PHP_BINARY must NOT be trusted outside the CLI SAPI: under PHP-FPM it points at
     * the FPM daemon (e.g. /usr/sbin/php-fpm8.3) and under mod_php at the web server
     * itself. Both accept a script path as an argument and then silently ignore it, so
     * a spawned worker looks launched and never runs.
     *
     * @return array {binary: string|null, attempts: array, sapi: string, php_binary: string}
     */
    public static function resolvePhpCli() {
        if (self::$phpCliResolution !== null) {
            return self::$phpCliResolution;
        }

        $attempts = [];
        $binary = null;

        foreach (self::getPhpCliCandidates() as $candidate) {
            $check = self::verifyPhpCli($candidate);
            $attempts[] = ['candidate' => $candidate, 'result' => $check['detail']];
            if ($check['ok']) {
                $binary = $candidate;
                break;
            }
        }

        self::$phpCliResolution = [
            'binary' => $binary,
            'attempts' => $attempts,
            'sapi' => PHP_SAPI,
            'php_binary' => PHP_BINARY
        ];

        return self::$phpCliResolution;
    }

    /**
     * Build the candidate list for resolvePhpCli(), best guess first.
     */
    private static function getPhpCliCandidates() {
        $isWindows = self::isWindows();
        $exe = $isWindows ? 'php.exe' : 'php';
        $versioned = 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ($isWindows ? '.exe' : '');
        $candidates = [];

        // Only trustworthy when we are already the CLI SAPI (i.e. the worker itself).
        if (PHP_SAPI === 'cli' && PHP_BINARY) {
            $candidates[] = PHP_BINARY;
        }

        // PHP_BINDIR is a compile-time constant pointing at the CLI directory
        // (/usr/bin on Debian/Ubuntu) even when the running SAPI lives in /usr/sbin.
        if (defined('PHP_BINDIR') && PHP_BINDIR) {
            $bindir = rtrim(PHP_BINDIR, '/\\');
            $candidates[] = $bindir . DIRECTORY_SEPARATOR . $versioned;
            $candidates[] = $bindir . DIRECTORY_SEPARATOR . $exe;
        }

        // Sibling of the running SAPI binary: /usr/sbin/php-fpm8.3 -> /usr/sbin/php8.3
        if (PHP_BINARY) {
            $dir = rtrim(dirname(PHP_BINARY), '/\\');
            $base = basename(PHP_BINARY);
            $stripped = str_replace(['-fpm', '-cgi'], '', $base);
            if ($stripped !== $base) {
                $candidates[] = $dir . DIRECTORY_SEPARATOR . $stripped;
            }
            $candidates[] = $dir . DIRECTORY_SEPARATOR . $exe;
        }

        if ($isWindows) {
            // WAMP keeps php.exe beside the version Apache is serving.
            $candidates[] = 'C:/wamp64/bin/php/php' . PHP_VERSION . '/php.exe';

            // Other installed versions as a fallback, newest first, so we never
            // hand the worker an older major version than the one serving the site.
            $installed = glob('C:/wamp64/bin/php/php*/php.exe') ?: [];
            usort($installed, function ($a, $b) {
                return version_compare(
                    basename(dirname($b)),
                    basename(dirname($a))
                );
            });
            foreach ($installed as $path) {
                $candidates[] = $path;
            }
        } else {
            $candidates[] = '/usr/local/bin/' . $versioned;
            $candidates[] = '/usr/local/bin/php';
            $candidates[] = '/usr/bin/' . $versioned;
            $candidates[] = '/usr/bin/php';
        }

        // Last resort: whatever the web user's PATH resolves.
        $candidates[] = 'php';

        $unique = [];
        foreach ($candidates as $candidate) {
            $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
            if ($candidate !== '' && !in_array($candidate, $unique, true)) {
                $unique[] = $candidate;
            }
        }

        return $unique;
    }

    /**
     * Confirm a candidate is a real CLI interpreter, not php-fpm or php-cgi.
     *
     * @return array {ok: bool, detail: string}
     */
    private static function verifyPhpCli($candidate) {
        $isPath = strpbrk($candidate, '/\\') !== false;

        if ($isPath && !file_exists($candidate)) {
            return ['ok' => false, 'detail' => 'not found'];
        }

        if (!function_exists('exec') || self::isExecDisabled()) {
            // Cannot probe. Accept a plausible absolute path, never a bare command
            // and never something that is obviously the FPM or CGI binary.
            $base = basename($candidate);
            $plausible = $isPath && stripos($base, 'fpm') === false && stripos($base, 'cgi') === false;
            return [
                'ok' => $plausible,
                'detail' => $plausible ? 'assumed usable (exec disabled)' : 'unverifiable (exec disabled)'
            ];
        }

        // stdin is redirected from the null device on purpose: probing a candidate that
        // turns out to be an interactive program (a shell, say) would otherwise block
        // the web request forever waiting for input.
        $nullDevice = self::isWindows() ? 'NUL' : '/dev/null';
        $output = [];
        $code = null;
        @exec(
            escapeshellarg($candidate) . ' -r ' . escapeshellarg('echo PHP_SAPI;')
            . ' < ' . $nullDevice . ' 2>&1',
            $output,
            $code
        );
        $text = trim(implode(' ', $output));

        if ($code !== 0) {
            return ['ok' => false, 'detail' => 'exit ' . var_export($code, true) . ': ' . substr($text, 0, 120)];
        }
        if (stripos($text, 'cli') === false) {
            return ['ok' => false, 'detail' => 'not the CLI SAPI: ' . substr($text, 0, 120)];
        }

        return ['ok' => true, 'detail' => 'ok'];
    }

    /**
     * Install yt-dlp to project directory
     */
    public static function installToProject() {
        $binDir = __DIR__ . '/../../bin';
        
        // Create bin directory if it doesn't exist
        if (!is_dir($binDir)) {
            if (!mkdir($binDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create bin directory'];
            }
        }

        // Download yt-dlp
        $url = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp';
        $ytDlpContent = @file_get_contents($url);
        
        if ($ytDlpContent === false) {
            return ['success' => false, 'error' => 'Failed to download yt-dlp from GitHub'];
        }

        // Write to file
        if (file_put_contents(self::$projectBinPath, $ytDlpContent) === false) {
            return ['success' => false, 'error' => 'Failed to write yt-dlp to bin directory'];
        }

        // Make executable
        if (!chmod(self::$projectBinPath, 0755)) {
            return ['success' => false, 'error' => 'Failed to set executable permissions'];
        }

        // Test installation
        $testResult = self::testInstallation();
        if (!$testResult['success']) {
            return $testResult;
        }

        return ['success' => true, 'message' => 'yt-dlp installed successfully to project directory'];
    }

    /**
     * Install yt-dlp system-wide (requires sudo)
     */
    public static function installToSystem() {
        $url = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp';
        $targetPath = '/usr/local/bin/yt-dlp';

        // Download to temp file first
        $tempFile = sys_get_temp_dir() . '/yt-dlp-download';
        $ytDlpContent = @file_get_contents($url);
        
        if ($ytDlpContent === false) {
            return ['success' => false, 'error' => 'Failed to download yt-dlp from GitHub'];
        }

        file_put_contents($tempFile, $ytDlpContent);

        // Try to move with sudo
        exec("sudo mv $tempFile $targetPath 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => 'Failed to move yt-dlp to /usr/local/bin. Error: ' . implode("\n", $output)];
        }

        // Set permissions with sudo
        exec("sudo chmod 755 $targetPath 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => 'Failed to set permissions. Error: ' . implode("\n", $output)];
        }

        // Test installation
        $testResult = self::testInstallation();
        if (!$testResult['success']) {
            return $testResult;
        }

        return ['success' => true, 'message' => 'yt-dlp installed successfully to /usr/local/bin'];
    }

    /**
     * Test if yt-dlp works
     */
    public static function testInstallation() {
        $ytDlpPath = self::getYtDlpPath();
        
        exec("$ytDlpPath --version 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            $version = trim($output[0] ?? 'unknown');
            return ['success' => true, 'version' => $version];
        } else {
            return ['success' => false, 'error' => 'yt-dlp test failed: ' . implode("\n", $output)];
        }
    }

    /**
     * Details about the yt-dlp binary actually being executed.
     *
     * On Windows that is the bundled yt-dlp.exe, on Linux whichever system or project
     * install getYtDlpPath() selects. In both cases the version is read from the
     * binary itself rather than assumed.
     *
     * @return array {path, source, version, updated, notice, error}
     */
    public static function getYtDlpInfo() {
        $path = self::getYtDlpPath();
        $resolved = realpath($path);
        $isPath = strpbrk($path, '/\\') !== false;

        $info = [
            'path' => $resolved !== false ? $resolved : $path,
            'source' => self::describeYtDlpSource($path),
            'version' => null,
            'updated' => null,
            'notice' => null,
            'error' => null
        ];

        if ($resolved !== false) {
            $mtime = @filemtime($resolved);
            if ($mtime) {
                $info['updated'] = date('Y-m-d H:i', $mtime);
            }
        } elseif ($isPath) {
            $info['error'] = 'Binary not found at this path';
            return $info;
        }

        if (!function_exists('exec') || self::isExecDisabled()) {
            $info['error'] = 'exec() is disabled, cannot read the version';
            return $info;
        }

        $output = [];
        $code = null;
        @exec(
            escapeshellarg($info['path']) . ' --version < '
            . (self::isWindows() ? 'NUL' : '/dev/null') . ' 2>&1',
            $output,
            $code
        );
        $text = trim(implode("\n", $output));

        // yt-dlp prints a bare date-style version, but it may be preceded by an
        // unrelated "your version is older than 90 days" warning on stderr.
        if (preg_match('/^\d{4}\.\d{2}\.\d{2}[\w.\-]*$/m', $text, $match)) {
            $info['version'] = $match[0];
        } elseif ($code === 0 && $text !== '') {
            $info['version'] = strtok($text, "\n");
        } else {
            $info['error'] = $text !== ''
                ? substr($text, 0, 300)
                : 'yt-dlp exited with code ' . var_export($code, true);
        }

        if (stripos($text, 'older than') !== false) {
            $info['notice'] = 'yt-dlp reports itself as out of date; downloads of restricted videos may start failing.';
        }

        return $info;
    }

    /**
     * Human-readable origin of the yt-dlp binary in use.
     */
    private static function describeYtDlpSource($path) {
        if (self::isWindows()) {
            return 'Bundled yt-dlp.exe';
        }
        if (realpath($path) !== false && realpath($path) === realpath(self::$projectBinPath)) {
            return 'Project bin/yt-dlp';
        }
        foreach (self::$systemPaths as $systemPath) {
            if ($path === $systemPath) {
                return 'System install (' . $systemPath . ')';
            }
        }
        return 'Resolved through PATH';
    }

    /**
     * Which PHP runs the site, and which PHP runs the background worker.
     *
     * These are routinely different: the site may be served by PHP-FPM while the
     * worker is launched with the CLI binary picked by resolvePhpCli().
     *
     * @return array {web: {...}, cli: {...}}
     */
    public static function getPhpInfo() {
        $cli = self::resolvePhpCli();

        $info = [
            'web' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'binary' => PHP_BINARY
            ],
            'cli' => [
                'binary' => $cli['binary'],
                'version' => null,
                'error' => null
            ]
        ];

        if (empty($cli['binary'])) {
            $info['cli']['error'] = 'No usable CLI binary found; background downloads cannot start';
            return $info;
        }

        if (!function_exists('exec') || self::isExecDisabled()) {
            $info['cli']['error'] = 'exec() is disabled, cannot read the version';
            return $info;
        }

        $output = [];
        $code = null;
        @exec(
            escapeshellarg($cli['binary']) . ' -r ' . escapeshellarg('echo PHP_VERSION;')
            . ' < ' . (self::isWindows() ? 'NUL' : '/dev/null') . ' 2>&1',
            $output,
            $code
        );
        $text = trim(implode(' ', $output));

        if ($code === 0 && preg_match('/\d+\.\d+\.\d+/', $text, $match)) {
            $info['cli']['version'] = $match[0];
        } else {
            $info['cli']['error'] = $text !== '' ? substr($text, 0, 300) : 'exited with code ' . var_export($code, true);
        }

        return $info;
    }

    /**
     * Get manual installation instructions
     */
    public static function getManualInstructions() {
        return [
            'title' => 'Manual Installation via SSH',
            'steps' => [
                'Connect to your server via SSH',
                'Run the following commands:',
                'sudo curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp',
                'sudo chmod a+rx /usr/local/bin/yt-dlp',
                'yt-dlp --version',
                'Return to this page and click "Test Installation"'
            ]
        ];
    }

    /**
     * Check if running on Windows
     */
    private static function isWindows() {
        return DIRECTORY_SEPARATOR === '\\';
    }
}
