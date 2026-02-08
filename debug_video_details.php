<?php
$videoId = 'nJRLaEwIKMU';

echo "--- oEmbed Check ---\n";
$oembedUrl = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $oembedUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$oembedData = curl_exec($ch);
curl_close($ch);

echo "oEmbed Response Code: " . ($oembedData ? "200 OK" : "Failed") . "\n";
echo "oEmbed Data: " . $oembedData . "\n";

echo "\n--- Invidious Check ---\n";
$instances = [
    'https://inv.tux.pizza',
    'https://yewtu.be',
];

foreach ($instances as $instance) {
    echo "Checking {$instance}...\n";
    $apiUrl = "{$instance}/api/v1/videos/{$videoId}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $json = curl_exec($ch);
    curl_close($ch);
    
    if ($json) {
        $data = json_decode($json, true);
        echo "Format Streams: " . (isset($data['formatStreams']) ? count($data['formatStreams']) : '0') . "\n";
        echo "Error: " . ($data['error'] ?? 'None') . "\n";
        break; 
    }
}
