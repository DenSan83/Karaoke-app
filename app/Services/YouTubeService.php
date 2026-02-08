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
}
