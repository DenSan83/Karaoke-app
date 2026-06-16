<?php

require_once 'app/Services/Database.php';

class Playlist {
    private $db;
    private $groupId;

    public function __construct($groupId = null) {
        $this->db = Database::getInstance();
        $this->groupId = $groupId ?: 'default';
    }

    public function getAll() {
        $rows = $this->db->fetchAll("SELECT * FROM `playlist` WHERE group_id = ? ORDER BY sort_order ASC", [$this->groupId]);
        // Format to match old JSON structure (map video_id to id)
        $playlist = array_map(function($row) {
            return [
                'id' => $row['video_id'],
                'title' => $row['title'],
                'user' => $row['user'],
                'added_at' => (int)$row['added_at'],
                'downloading' => (bool)$row['downloading'],
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
                $newVideo['downloading'] = true;
                $this->spawnBackgroundDownload($videoId);
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

    public function spawnBackgroundDownload($videoId) {
        $workerScript = __DIR__ . '/../../download_worker.php';
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        
        // Log the spawn attempt
        $logFile = __DIR__ . '/../../temp/spawn_log.txt';
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Spawning download for {$videoId}\n", FILE_APPEND);
        
        if ($isWindows) {
            // Windows: Find the actual php.exe. PHP_BINARY might point to httpd.exe in Apache.
            $phpPath = PHP_BINARY;
            if (strpos(strtolower($phpPath), 'httpd.exe') !== false || strpos(strtolower($phpPath), 'apache') !== false) {
                // Try to find php.exe in WAMP's bin directory matching current version
                $wampPhpDir = 'C:/wamp64/bin/php/php' . PHP_VERSION . '/php.exe';
                if (file_exists($wampPhpDir)) {
                    $phpPath = $wampPhpDir;
                } else {
                    // Fallback: search for any php.exe in common WAMP paths
                    $possiblePaths = [
                        'C:/wamp64/bin/php/php8.3.14/php.exe',
                        'C:/wamp64/bin/php/php8.2.26/php.exe',
                        'C:/wamp64/bin/php/php8.1.31/php.exe'
                    ];
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $phpPath = $path;
                            break;
                        }
                    }
                }
            }
            
            $vbsScript = __DIR__ . '/../../temp/spawn_' . $videoId . '.vbs';
            
            // Create a VBS script to run PHP in background (hidden window)
            // Properly escape paths for VBScript
            $phpPath = str_replace('/', '\\', $phpPath);
            $escapedPhp = str_replace('"', '""', $phpPath);
            $workerScriptPath = str_replace('/', '\\', realpath($workerScript));
            $escapedWorker = str_replace('"', '""', $workerScriptPath);
            
            $vbsContent = "Set WshShell = CreateObject(\"WScript.Shell\")\n";
            $vbsContent .= "WshShell.Run \"\"\"{$escapedPhp}\"\" \"\"{$escapedWorker}\"\" {$videoId}\", 0, False\n";
            file_put_contents($vbsScript, $vbsContent);
            
            // Execute VBS script
            exec("cscript //nologo \"{$vbsScript}\"");
            
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Used PHP (Windows): {$phpPath}\n", FILE_APPEND);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - VBS script created and executed\n", FILE_APPEND);
        } else {
            // Linux: Use & to run in background
            // Use PHP_BINARY if it looks like a real binary, else fallback to 'php'
            $phpPath = PHP_BINARY;
            if (strpos($phpPath, 'php') === false) {
                $phpPath = 'php';
            }
            
            $cmd = "{$phpPath} \"{$workerScript}\" {$videoId} > /dev/null 2>&1 &";
            exec($cmd);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Linux command executed: {$cmd}\n", FILE_APPEND);
        }
    }


    public function remove($index) {
        $playlist = json_decode($this->getAll(), true);
        if (!isset($playlist[$index])) {
            return ['error' => 'Invalid index'];
        }
        
        $itemToRemove = $playlist[$index];
        $videoId = $itemToRemove['id'];
        
        // Check if local file exists
        if (isset($itemToRemove['local_file'])) {
            $filePath = __DIR__ . '/../../' . $itemToRemove['local_file'];
            
            // Count how many times this file is used in the database
            $row = $this->db->fetch("SELECT COUNT(*) as usage_count FROM `playlist` WHERE local_path = ?", [$itemToRemove['local_file']]);
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
            $this->db->query("SET @rank = -1; UPDATE `playlist` SET sort_order = (@rank := @rank + 1) WHERE group_id = ? ORDER BY sort_order ASC", [$this->groupId]);
        }
        
        return ['success' => true];
    }

    public function markDownloadComplete($videoId, $localPath) {
        $sql = "UPDATE `playlist` SET downloading = 0, local_path = ? WHERE group_id = ? AND video_id = ?";
        $success = $this->db->query($sql, [$localPath, $this->groupId, $videoId]);
        
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
