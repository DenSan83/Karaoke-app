<?php

class YouTubeService {
    /**
     * Extracts YouTube Video ID from various URL formats.
     */
    public function extractVideoId($url) {
        if (empty($url)) return null;

        // Extract Video ID from query string
        parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
        $videoId = $queryParams['v'] ?? '';
        
        // Handle short URLs (youtu.be)
        if (empty($videoId)) {
            $path = parse_url($url, PHP_URL_PATH);
            $path = ltrim($path, '/');
            if (strpos($url, 'youtu.be') !== false) {
                 $videoId = $path;
            }
        }

        return !empty($videoId) ? $videoId : null;
    }

    /**
     * Fetches video metadata (title) using oEmbed with a fallback for restricted videos.
     */
    public function getMetadata($videoId) {
        if (empty($videoId)) return null;

        $oembedUrl = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $oembedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36");
        $oembedData = curl_exec($ch);
        curl_close($ch);
        
        $title = 'Unknown Title';
        $oembedSuccess = false;
        
        if ($oembedData) {
            $videoData = json_decode($oembedData, true);
            if (isset($videoData['title'])) {
                $title = $videoData['title'];
                $oembedSuccess = true;
            }
        }

        // Fallback: Try fetching page title if oEmbed fails (restricted/private/unavailable)
        if (!$oembedSuccess) {
            $pageContent = @file_get_contents("https://www.youtube.com/watch?v={$videoId}");
            if ($pageContent) {
                if (preg_match('/<title>(.*?) - YouTube<\/title>/', $pageContent, $matches)) {
                    $title = $matches[1];
                }
            }
        }

        return [
            'id' => $videoId,
            'title' => html_entity_decode($title, ENT_QUOTES),
            'oembed_success' => $oembedSuccess
        ];
    }

    /**
     * Check if a video is embeddable.
     */
    public function isEmbeddable($videoId) {
        $url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_NOBODY, true); // We only need the headers/status code
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // oEmbed returns 200 for embeddable videos, 401/403/404 for non-embeddable
        return $httpCode === 200;
    }

    /**
     * Check if a video can be downloaded via yt-dlp.
     */
    public function isDownloadable($videoId) {
        require_once __DIR__ . '/SystemCheck.php';
        $ytDlp = SystemCheck::getYtDlpPath();
        $videoUrl = "https://www.youtube.com/watch?v={$videoId}";
        
        // Use --simulate to check without downloading
        $cmd = "\"{$ytDlp}\" --simulate \"{$videoUrl}\" 2>&1";
        exec($cmd, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Check if URL is a YouTube playlist
     */
    public function isPlaylistUrl($url) {
        return strpos($url, 'list=') !== false;
    }

    /**
     * Extract playlist ID from URL
     */
    public function extractPlaylistId($url) {
        $pattern = '/[?&]list=([a-zA-Z0-9_-]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get videos from a YouTube playlist (max 20)
     * Returns array of ['id' => videoId, 'title' => title]
     */
    public function getPlaylistVideos($playlistId, $maxVideos = 20) {
        // Try multiple Invidious instances
        $invidiousInstances = [
            "https://invidious.io.lol",
            "https://inv.nadeko.net",
            "https://invidious.nerdvpn.de"
        ];
        
        $lastError = '';
        
        foreach ($invidiousInstances as $instance) {
            $invidiousUrl = "{$instance}/api/v1/playlists/{$playlistId}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0',
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($invidiousUrl, false, $context);
            
            if ($response === false) {
                $lastError = "Failed to connect to {$instance}";
                continue;
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['videos']) || !is_array($data['videos'])) {
                $lastError = "Invalid response from {$instance}";
                continue;
            }
            
            if (empty($data['videos'])) {
                return ['error' => 'Playlist is empty'];
            }
            
            // Success! Process videos
            $videos = [];
            $count = 0;
            
            foreach ($data['videos'] as $video) {
                if ($count >= $maxVideos) {
                    break;
                }
                
                if (isset($video['videoId']) && isset($video['title'])) {
                    $videos[] = [
                        'id' => $video['videoId'],
                        'title' => $video['title']
                    ];
                    $count++;
                }
            }
            
            return [
                'videos' => $videos,
                'total' => count($data['videos']),
                'fetched' => count($videos)
            ];
        }
        
        // All instances failed
        return ['error' => 'Failed to fetch playlist data. Please try again later.'];
    }
}
