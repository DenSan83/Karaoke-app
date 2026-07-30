<?php

require_once 'app/Services/Database.php';

class Playlist {
    /** A worker that has not written a single progress update by then never started. */
    const SPAWN_STALL_SECONDS = 90;

    /** A download that has not advanced by then is considered dead. */
    const PROGRESS_STALL_SECONDS = 600;

    /** YouTube IDs only. Anything else must never reach a shell command line. */
    const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{5,20}$/';

    private $db;
    private $groupId;
    private $sysLog = null;

    public function __construct($groupId = null) {
        $this->db = Database::getInstance();
        $this->groupId = $groupId ?: 'default';
    }

    public function getAll() {
        $rows = $this->db->fetchAll("SELECT * FROM `playlist` WHERE group_id = ? ORDER BY sort_order ASC", [$this->groupId]);
        // Format to match old JSON structure (map video_id to id)
        $playlist = array_map(function($row) {
            // Self-heal rows whose background worker finished, died or never started.
            if (!empty($row['downloading'])) {
                $row = $this->reconcileDownload($row);
            }
            return [
                'id' => $row['video_id'],
                'title' => $row['title'],
                'user' => $row['user'],
                'added_at' => (int)$row['added_at'],
                'downloading' => (bool)$row['downloading'],
                'download_failed' => !empty($row['download_failed']),
                'download_error' => $row['download_error'] ?? null,
                'local_path' => $row['local_path']
            ];
        }, $rows);
        return json_encode($playlist);
    }

    public function add($url, $user) {
        if (empty($url)) {
            return ['error' => 'URL is required'];
        }

        if (empty($user)) {
             return ['error' => 'User name is required'];
        }

        require_once 'app/Services/YouTubeService.php';
        $ytService = new YouTubeService();

        // Check if this is a playlist URL
        if ($ytService->isPlaylistUrl($url)) {
            return $this->addPlaylist($url, $user, $ytService);
        }

        // Single video handling
        $videoId = $ytService->extractVideoId($url);
        if (!$videoId) {
            return ['error' => 'Invalid YouTube URL'];
        }

        $metadata = $ytService->getMetadata($videoId);
        $title = $metadata['title'] ?? 'Unknown Title';
        $needsDownload = !$metadata['oembed_success'];
        
        $sql = "INSERT INTO `playlist` (group_id, video_id, title, user, added_at, downloading, sort_order) 
                SELECT ?, ?, ?, ?, ?, ?, COALESCE(MAX(sort_order), 0) + 1 FROM `playlist` WHERE group_id = ?";
        $addedAt = time();
        $success = $this->db->query($sql, [
            $this->groupId,
            $videoId,
            $title,
            $user,
            $addedAt,
            $needsDownload ? 1 : 0,
            $this->groupId
        ]);
        
        if ($success) {
            $newVideo = [
                'id' => $videoId,
                'title' => $title,
                'user' => $user,
                'added_at' => $addedAt
            ];
            if ($needsDownload) {
                $spawn = $this->spawnBackgroundDownload($videoId);
                $newVideo['downloading'] = empty($spawn['error']);
                if (!empty($spawn['error'])) {
                    $newVideo['download_failed'] = true;
                    $newVideo['download_error'] = $spawn['error'];
                }
            }
            
            return ['success' => true, 'video' => $newVideo];
        } else {
            return ['error' => 'Failed to save playlist'];
        }
    }

    private function addPlaylist($url, $user, $ytService) {
        $playlistId = $ytService->extractPlaylistId($url);
        if (!$playlistId) {
            return ['error' => 'Invalid playlist URL'];
        }

        $result = $ytService->getPlaylistVideos($playlistId, 20);
        
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $videos = $result['videos'];
        $total = $result['total'];
        $addedVideos = [];

        // Get current max sort_order
        $row = $this->db->fetch("SELECT MAX(sort_order) as max_order FROM `playlist` WHERE group_id = ?", [$this->groupId]);
        $currentMaxOrder = $row ? (int)$row['max_order'] : -1;

        // Add each video from the playlist
        foreach ($videos as $index => $videoData) {
            $newVideo = [
                'id' => $videoData['id'],
                'title' => $videoData['title'],
                'user' => $user,
                'added_at' => time()
            ];
            
            $sql = "INSERT INTO `playlist` (group_id, video_id, title, user, added_at, sort_order) VALUES (?, ?, ?, ?, ?, ?)";
            $this->db->query($sql, [
                $this->groupId,
                $newVideo['id'],
                $newVideo['title'],
                $newVideo['user'],
                $newVideo['added_at'],
                $currentMaxOrder + $index + 1
            ]);

            $addedVideos[] = $newVideo;
        }

        return [
            'success' => true,
            'is_playlist' => true,
            'videos' => $addedVideos,
            'total_in_playlist' => $total,
            'added_count' => count($addedVideos)
        ];
    }

    /**
     * Launch download_worker.php in the background for a queued video.
     *
     * Every outcome is recorded in the superadmin activity log (type 'worker_spawn')
     * and any failure is written back to the row, so a download can never sit on an
     * hourglass with nothing explaining why.
     */
    public function spawnBackgroundDownload($videoId) {
        require_once __DIR__ . '/../Services/SystemCheck.php';

        if (!preg_match(self::VIDEO_ID_PATTERN, (string)$videoId)) {
            // Video IDs come from a user-supplied URL and end up on a command line.
            return $this->failSpawn($videoId, 'Refusing to spawn: unsafe video id');
        }

        if (!function_exists('exec')) {
            return $this->failSpawn($videoId, 'exec() is unavailable, cannot start the worker');
        }

        $tempDir = $this->tempDir();
        if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true)) {
            return $this->failSpawn($videoId, 'Cannot create temp directory', ['path' => $tempDir]);
        }
        $this->pruneTempArtifacts();

        $workerScript = realpath(__DIR__ . '/../../download_worker.php');
        if (!$workerScript) {
            return $this->failSpawn($videoId, 'Worker script not found', ['expected' => __DIR__ . '/../../download_worker.php']);
        }

        $php = SystemCheck::resolvePhpCli();
        if (empty($php['binary'])) {
            return $this->failSpawn($videoId, 'No usable PHP CLI binary found', [
                'web_sapi' => $php['sapi'],
                'web_php_binary' => $php['php_binary'],
                'attempts' => $php['attempts']
            ]);
        }

        // Seed the progress file before spawning: its timestamp is how we later tell
        // "the worker never started" apart from "the worker is still working".
        $progressFile = $this->progressFilePath($videoId);
        if (@file_put_contents($progressFile, json_encode(['status' => 'spawning', 'percent' => 0, 'ts' => time()])) === false) {
            return $this->failSpawn($videoId, 'Cannot write progress file', ['path' => $progressFile]);
        }

        // The worker inherits stdout/stderr into this file, so a failure to even
        // start the interpreter is captured instead of going to /dev/null.
        $workerLog = $this->workerLogPath($videoId);
        @unlink($workerLog);

        $groupArg = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$this->groupId);

        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: WScript.Shell runs it with no console window, cmd /s /c gives
            // us the output redirection. Inside a VBS string literal "" is one quote.
            $vbsQuote = function ($value) {
                return '""' . str_replace('/', '\\', $value) . '""';
            };
            $inner = $vbsQuote($php['binary']) . ' ' . $vbsQuote($workerScript) . ' ' . $videoId . ' ' . $groupArg
                   . ' > ' . $vbsQuote($workerLog) . ' 2>&1';

            $vbsScript = $tempDir . DIRECTORY_SEPARATOR . 'spawn_' . $videoId . '.vbs';
            $vbsContent = "Set WshShell = CreateObject(\"WScript.Shell\")\r\n"
                        . "WshShell.Run \"cmd /s /c \"\"" . $inner . "\"\"\", 0, False\r\n";
            if (@file_put_contents($vbsScript, $vbsContent) === false) {
                return $this->failSpawn($videoId, 'Cannot write spawn script', ['path' => $vbsScript]);
            }
            $cmd = 'cscript //nologo ' . escapeshellarg($vbsScript);
        } else {
            $cmd = escapeshellarg($php['binary'])
                 . ' ' . escapeshellarg($workerScript)
                 . ' ' . escapeshellarg($videoId)
                 . ' ' . escapeshellarg($groupArg)
                 . ' > ' . escapeshellarg($workerLog) . ' 2>&1 &';
        }

        $output = [];
        $exitCode = null;
        @exec($cmd, $output, $exitCode);
        $launcherOutput = trim(implode("\n", $output));

        // NOTE: on Linux the trailing '&' means this exit code only describes the
        // backgrounding shell, not the worker. The worker logs its own start and
        // finish, and reconcileDownload() catches the case where it never does.
        $this->log('worker_spawn', [
            'status' => $exitCode === 0 ? 'spawned' : 'launcher_error',
            'videoId' => $videoId,
            'php_cli' => $php['binary'],
            'web_sapi' => $php['sapi'],
            'web_php_binary' => $php['php_binary'],
            'command' => $cmd,
            'launcher_exit' => $exitCode,
            'launcher_output' => $launcherOutput !== '' ? $launcherOutput : null
        ]);

        if ($exitCode !== 0) {
            $this->markDownloadFailed($videoId, 'Launcher exited with code ' . var_export($exitCode, true));
            return ['error' => 'Failed to start the download worker'];
        }

        return ['success' => true, 'php_cli' => $php['binary']];
    }

    /**
     * Record a spawn that never got off the ground, then mark the row failed.
     */
    private function failSpawn($videoId, $reason, $details = []) {
        $this->log('worker_spawn', array_merge([
            'status' => 'error',
            'videoId' => $videoId,
            'reason' => $reason
        ], $details));

        $this->markDownloadFailed($videoId, $reason);

        return ['error' => $reason];
    }

    /**
     * Flag a download as failed so the UI can offer a retry instead of an hourglass.
     * Cross-group on purpose: the download failed for every queue holding this video.
     */
    public function markDownloadFailed($videoId, $reason, $details = []) {
        $short = substr(preg_replace('/\s+/', ' ', trim((string)$reason)), 0, 250);

        $this->db->query(
            "UPDATE `playlist` SET downloading = 0, download_failed = 1, download_error = ? WHERE video_id = ? AND downloading = 1",
            [$short, $videoId]
        );

        @unlink($this->progressFilePath($videoId));

        if (!empty($details)) {
            $this->log('worker_event', array_merge([
                'status' => 'failed',
                'videoId' => $videoId,
                'message' => $short
            ], $details));
        }

        return ['success' => true];
    }

    /**
     * Clear the failure state and launch a fresh download attempt.
     */
    public function retryDownload($videoId) {
        $row = $this->db->fetch(
            "SELECT * FROM `playlist` WHERE group_id = ? AND video_id = ? LIMIT 1",
            [$this->groupId, $videoId]
        );
        if (!$row) {
            return ['error' => 'This track is not in the queue'];
        }

        // Maybe it actually succeeded and only the bookkeeping failed.
        $localPath = $this->localVideoPath($videoId);
        if ($localPath !== null) {
            $this->markDownloadComplete($videoId, $localPath);
            return ['success' => true, 'already_downloaded' => true];
        }

        @unlink($this->progressFilePath($videoId));
        @unlink($this->workerLogPath($videoId));

        $this->db->query(
            "UPDATE `playlist` SET downloading = 1, download_failed = 0, download_error = NULL WHERE group_id = ? AND video_id = ?",
            [$this->groupId, $videoId]
        );

        $this->log('worker_event', ['status' => 'retry_requested', 'videoId' => $videoId]);

        return $this->spawnBackgroundDownload($videoId);
    }

    /**
     * Decide whether a row still marked as downloading is alive, finished or dead.
     * Returns the row, updated in place when its state changed.
     */
    private function reconcileDownload(array $row) {
        $videoId = $row['video_id'];
        $progressFile = $this->progressFilePath($videoId);

        // yt-dlp only renames its .part file once the download is complete, so an
        // existing non-empty .mp4 means success even if the DB never heard about it.
        $localPath = $this->localVideoPath($videoId);
        if ($localPath !== null) {
            $this->markDownloadComplete($videoId, $localPath);
            $row['downloading'] = 0;
            $row['download_failed'] = 0;
            $row['download_error'] = null;
            $row['local_path'] = $localPath;
            return $row;
        }

        $now = time();

        if (file_exists($progressFile)) {
            $progress = json_decode((string)@file_get_contents($progressFile), true) ?: [];
            $status = $progress['status'] ?? 'unknown';
            $lastSign = max((int)(@filemtime($progressFile) ?: 0), (int)($progress['ts'] ?? 0));
            $age = $now - $lastSign;

            if ($status === 'spawning') {
                if ($age < self::SPAWN_STALL_SECONDS) {
                    return $row;
                }
                $reason = 'Worker never started (no progress after ' . self::SPAWN_STALL_SECONDS . 's)';
            } else {
                if ($age < self::PROGRESS_STALL_SECONDS) {
                    return $row;
                }
                $reason = 'Download stalled at ' . (float)($progress['percent'] ?? 0)
                        . '% for over ' . self::PROGRESS_STALL_SECONDS . 's';
            }
        } else {
            // No progress file at all: a row left over from before this check existed,
            // or a worker that died between spawning and its first write.
            if ($now - (int)$row['added_at'] < self::SPAWN_STALL_SECONDS) {
                return $row;
            }
            $reason = 'No progress was ever reported for this download';
        }

        $this->markDownloadFailed($videoId, $reason, ['worker_output' => $this->readWorkerLog($videoId)]);

        $row['downloading'] = 0;
        $row['download_failed'] = 1;
        $row['download_error'] = $reason;
        return $row;
    }

    private function tempDir() {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'temp';
    }

    /**
     * Drop leftover worker artifacts so temp/ cannot grow without bound.
     *
     * A day is far beyond both stall thresholds, so nothing belonging to a live
     * download is ever in range. Windows in particular cannot delete a worker log
     * while its process still holds the handle, which is why this sweep exists.
     */
    private function pruneTempArtifacts($maxAgeSeconds = 86400) {
        $cutoff = time() - $maxAgeSeconds;
        $patterns = ['worker_*.log', 'progress_*.json', 'spawn_*.vbs'];

        foreach ($patterns as $pattern) {
            foreach (glob($this->tempDir() . DIRECTORY_SEPARATOR . $pattern) ?: [] as $path) {
                $mtime = @filemtime($path);
                if ($mtime !== false && $mtime < $cutoff) {
                    @unlink($path);
                }
            }
        }
    }

    private function progressFilePath($videoId) {
        return $this->tempDir() . DIRECTORY_SEPARATOR . 'progress_' . $videoId . '.json';
    }

    private function workerLogPath($videoId) {
        return $this->tempDir() . DIRECTORY_SEPARATOR . 'worker_' . $videoId . '.log';
    }

    /**
     * Relative path of the downloaded file, or null when it is absent or empty.
     */
    private function localVideoPath($videoId) {
        $relative = 'public/media/videos/' . $videoId . '.mp4';
        $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return (is_file($absolute) && filesize($absolute) > 0) ? $relative : null;
    }

    /**
     * Whatever the spawned process printed before dying, trimmed for the log.
     */
    private function readWorkerLog($videoId) {
        $path = $this->workerLogPath($videoId);
        if (!is_file($path)) {
            return null;
        }
        $contents = trim((string)@file_get_contents($path));
        if ($contents === '') {
            return null;
        }
        return substr($contents, -2000);
    }

    /**
     * Write to the activity log shown on the superadmin Logs page.
     */
    private function log($type, array $data) {
        try {
            if ($this->sysLog === null) {
                require_once __DIR__ . '/SystemLog.php';
                $this->sysLog = new SystemLog($this->groupId);
            }
            $this->sysLog->log($type, $data);
        } catch (Exception $e) {
            error_log('Playlist: failed to write activity log (' . $type . '): ' . $e->getMessage());
        }
    }


    public function remove($index) {
        $playlist = json_decode($this->getAll(), true);
        if (!isset($playlist[$index])) {
            return ['error' => 'Invalid index'];
        }
        
        $itemToRemove = $playlist[$index];
        
        // Check if local file exists
        if (isset($itemToRemove['local_path'])) {
            $filePath = __DIR__ . '/../../' . $itemToRemove['local_path'];
            
            // Count how many times this file is used in the database
            $row = $this->db->fetch("SELECT COUNT(*) as usage_count FROM `playlist` WHERE local_path = ?", [$itemToRemove['local_path']]);
            $usageCount = $row ? (int)$row['usage_count'] : 0;

            // Only delete the physical file if this is the LAST reference to it
            if ($usageCount <= 1 && file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        // Delete from database using group_id and video_id and sort_order to be precise
        // Since we want to remove EXACTLY the one at $index
        $rows = $this->db->fetchAll("SELECT id FROM `playlist` WHERE group_id = ? ORDER BY sort_order ASC LIMIT ?, 1", [$this->groupId, $index]);
        if ($rows) {
            $dbId = $rows[0]['id'];
            $this->db->query("DELETE FROM `playlist` WHERE id = ?", [$dbId]);
            // Re-normalize sort_order to avoid gaps (optional but good)
            try {
                $this->db->query("SET @rank = -1");
                $this->db->query("UPDATE `playlist` SET sort_order = (@rank := @rank + 1) WHERE group_id = ? ORDER BY sort_order ASC", [$this->groupId]);
            } catch (\PDOException $e) {
                // Ignore if rank re-normalization fails
            }
        }
        
        return ['success' => true];
    }

    public function removeByVideoIdAndUser($videoId, $user) {
        // Find if this video exists in the playlist for this user
        // We use group_id, video_id and user to be precise
        $sql = "DELETE FROM `playlist` WHERE group_id = ? AND video_id = ? AND user = ?";
        $this->db->query($sql, [$this->groupId, $videoId, $user]);
        
        // Re-normalize sort_order
        try {
            $this->db->query("SET @rank = -1");
            $this->db->query("UPDATE `playlist` SET sort_order = (@rank := @rank + 1) WHERE group_id = ? ORDER BY sort_order ASC", [$this->groupId]);
        } catch (\PDOException $e) {
            // If the single-query multi-statement is not allowed or fails, we skip re-normalization or do it differently
            // but usually separate queries should work fine.
        }
        
        return ['success' => true];
    }

    /**
     * Mark a download as finished and clear any previous failure.
     * Cross-group on purpose: the file is on disk, so every queue entry for this
     * video can play it locally.
     */
    public function markDownloadComplete($videoId, $localPath) {
        $sql = "UPDATE `playlist` SET downloading = 0, download_failed = 0, download_error = NULL, local_path = ? WHERE video_id = ?";
        $success = $this->db->query($sql, [$localPath, $videoId]);

        @unlink($this->progressFilePath($videoId));
        @unlink($this->workerLogPath($videoId));

        if ($success) {
            return ['success' => true];
        } else {
            return ['error' => 'Failed to update playlist'];
        }
    }

    public function reorder($newPlaylist) {
        if (!is_array($newPlaylist)) {
            return ['error' => 'Invalid data format'];
        }

        $this->db->beginTransaction();
        try {
            // Simplest way: clear and re-insert or update all.
            // But $newPlaylist contains the full objects.
            // Let's just update sort_order for each video_id in the given order.
            // NOTE: This assumes video_ids are unique in the playlist for that group.
            // If there are duplicates, this might be problematic. 
            // Better to use the database IDs if we had them.
            
            foreach ($newPlaylist as $index => $item) {
                $videoId = $item['id'];
                $this->db->query("UPDATE `playlist` SET sort_order = ? WHERE group_id = ? AND video_id = ? LIMIT 1", [$index, $this->groupId, $videoId]);
            }
            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['error' => 'Failed to save playlist: ' . $e->getMessage()];
        }
    }

    private function downloadVideo($videoId) {
        $videoUrl = "https://www.youtube.com/watch?v={$videoId}";
        $outputFile = "public/media/videos/{$videoId}.mp4";
        $absoluteOutputPath = __DIR__ . '/../../' . $outputFile;
        $progressFile = __DIR__ . '/../../temp/progress_' . $videoId . '.json';
        
        // Ensure temp dir exists
        if (!is_dir(__DIR__ . '/../../temp')) {
            mkdir(__DIR__ . '/../../temp', 0777, true);
        }

        // Initialize progress
        file_put_contents($progressFile, json_encode(['status' => 'starting', 'percent' => 0]));

        // Determine OS and command
            require_once __DIR__ . '/../Services/SystemCheck.php';
            $cmd = SystemCheck::getYtDlpPath();

        // Command to download: Force a single pre-merged file to avoid ffmpeg dependency.
        // best[ext=mp4] might fetch 1080p without audio if we are unlucky, 
        // so we use 'best[height<=720][ext=mp4]' to try and get a standard 720p/360p file which usually has audio.
        // OR better: 'best[vcodec^=avc1][height<=720]' to ensure compatibility.
        // Easiest reliable single file: -f "best[ext=mp4]/best"
        $fullCmd = "{$cmd} -f \"best[ext=mp4]/best\" -o \"{$absoluteOutputPath}\" --newline --progress-template \"%(progress._percent_str)s\" {$videoUrl}";
        
        // Use proc_open to read output incrementally
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];

        set_time_limit(0); // Unlimited execution time
        
        $process = proc_open($fullCmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            while ($s = fgets($pipes[1])) {
                // Parse percent from output (e.g. " 45.6%")
                if (preg_match('/(\d+(\.\d+)?)%/', $s, $matches)) {
                    $percent = floatval($matches[1]);
                    file_put_contents($progressFile, json_encode(['status' => 'downloading', 'percent' => $percent]));
                }
                flush();
            }
            
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnVar = proc_close($process);
        } else {
            $returnVar = -1;
        }

        // Cleanup progress file
        if (file_exists($progressFile)) {
            unlink($progressFile);
        }

        if ($returnVar === 0 && file_exists($absoluteOutputPath)) {
            return ['local_file' => $outputFile, 'title' => 'Downloaded Video'];
        } else {
            return ['error' => 'Download failed'];
        }
    }

    public function getStreamUrl($videoId) {
        // Try multiple Invidious instances if one fails
        $instances = [
            'https://inv.tux.pizza',
            'https://yewtu.be',
            'https://vid.uff.oulu.fi',
            'https://invidious.jing.rocks',
            'https://invidious.nerdvpn.de',
            'https://invidious.fdn.fr'
        ];

        foreach ($instances as $instance) {
            $apiUrl = "{$instance}/api/v1/videos/{$videoId}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL issues on local WAMP
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36");
            
            $json = curl_exec($ch);
            curl_close($ch);
            
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['formatStreams'])) {
                    $bestUrl = '';
                    // Prefer 720p mp4
                    foreach ($data['formatStreams'] as $stream) {
                        if ($stream['container'] === 'mp4') {
                            $bestUrl = $stream['url'];
                            if ($stream['resolution'] === '720p') {
                                break;
                            }
                        }
                    }
                    if ($bestUrl) return ['url' => $bestUrl];
                }
            }
        }
        
        return ['error' => 'No suitable stream found'];
    }
}
