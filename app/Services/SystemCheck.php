<?php

class SystemCheck {
    private static $projectBinPath = __DIR__ . '/../../bin/yt-dlp';
    private static $systemPaths = ['/usr/local/bin/yt-dlp', '/usr/bin/yt-dlp'];

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
            exec('which yt-dlp 2>/dev/null', $output, $returnCode);
            return $returnCode === 0;
        }
    }

    /**
     * Check if database is initialized
     */
    public static function checkDatabase() {
        try {
            require_once __DIR__ . '/Database.php';
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'groups'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Handle table not found specifically if needed, but SHOW TABLES LIKE shouldn't throw it
            return false;
        } catch (Exception $e) {
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
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}
