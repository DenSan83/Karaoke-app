<?php

require_once 'app/Services/FileStorage.php';

class Playlist {
    private $file = 'playlist.json';

    public function __construct() {
        if (!file_exists($this->file)) {
            FileStorage::writeJson($this->file, []);
        }
    }

    public function getAll() {
        $data = FileStorage::readJson($this->file, []);
        return json_encode($data);
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

        $videoId = $ytService->extractVideoId($url);
        if (!$videoId) {
            return ['error' => 'Invalid YouTube URL'];
        }

        $metadata = $ytService->getMetadata($videoId);
        $title = $metadata['title'] ?? 'Unknown Title';
        $needsDownload = !$metadata['oembed_success'];
        
        $newVideo = [
            'id' => $videoId,
            'title' => $title,
            'user' => $user,
            'added_at' => time()
        ];

        // If video needs download (restricted/unavailable via oEmbed), mark as downloading
        if ($needsDownload) {
            $newVideo['downloading'] = true;
        }
        
        // Atomic add to playlist
        $success = FileStorage::atomicUpdate($this->file, function($playlist) use ($newVideo) {
            $playlist[] = $newVideo;
            return $playlist;
        }, []);
        
        if ($success) {
            // Spawn background download if needed
            if ($needsDownload) {
                $this->spawnBackgroundDownload($videoId);
            }
            
            return ['success' => true, 'video' => $newVideo];
        } else {
            return ['error' => 'Failed to save playlist'];
        }
    }

    private function spawnBackgroundDownload($videoId) {
        $workerScript = __DIR__ . '/../../download_worker.php';
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
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
                $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
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
            $escapedPhp = str_replace('"', '""', $phpPath);
            $escapedWorker = str_replace('"', '""', realpath($workerScript));
            
            $vbsContent = "Set WshShell = CreateObject(\"WScript.Shell\")\n";
            $vbsContent .= "WshShell.Run \"\"\"{$escapedPhp}\"\" \"\"{$escapedWorker}\"\" {$videoId}\", 0, False\n";
            file_put_contents($vbsScript, $vbsContent);
            
            // Execute VBS script
            exec("cscript //nologo \"{$vbsScript}\"");
            
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Used PHP: {$phpPath}\n", FILE_APPEND);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - VBS script created and executed\n", FILE_APPEND);
        } else {
            // Linux: Use & to run in background
            $cmd = "php \"{$workerScript}\" {$videoId} > /dev/null 2>&1 &";
            exec($cmd);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Linux command executed\n", FILE_APPEND);
        }
    }


    public function remove($index) {
        $success = FileStorage::atomicUpdate($this->file, function($playlist) use ($index) {
            if (!isset($playlist[$index])) {
                return $playlist; // No change
            }
            
            $itemToRemove = $playlist[$index];
            
            // Check if local file exists
            if (isset($itemToRemove['local_file'])) {
                $filePath = __DIR__ . '/../../' . $itemToRemove['local_file'];
                
                // Count how many times this file is used in the playlist
                $usageCount = 0;
                foreach ($playlist as $item) {
                    if (isset($item['local_file']) && $item['local_file'] === $itemToRemove['local_file']) {
                        $usageCount++;
                    }
                }

                // Only delete the physical file if this is the LAST reference to it
                if ($usageCount <= 1 && file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            array_splice($playlist, $index, 1);
            return $playlist;
        }, []);
        
        if ($success) {
            return ['success' => true];
        } else {
            return ['error' => 'Failed to save playlist'];
        }
    }

    public function markDownloadComplete($videoId, $localPath) {
        $success = FileStorage::atomicUpdate($this->file, function($playlist) use ($videoId, $localPath) {
            foreach ($playlist as &$video) {
                if ($video['id'] === $videoId) {
                    unset($video['downloading']);
                    $video['local_file'] = $localPath;
                    break;
                }
            }
            return $playlist;
        }, []);
        
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

        if (FileStorage::writeJson($this->file, $newPlaylist)) {
            return ['success' => true];
        } else {
            return ['error' => 'Failed to save playlist'];
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
