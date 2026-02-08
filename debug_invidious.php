<?php
$videoId = 'nJRLaEwIKMU';
$instances = [
    'https://inv.tux.pizza',
    'https://yewtu.be',
    'https://vid.uff.oulu.fi',
    'https://invidious.jing.rocks',
    'https://invidious.nerdvpn.de'
];

foreach ($instances as $instance) {
    echo "Trying {$instance}...\n";
    $apiUrl = "{$instance}/api/v1/videos/{$videoId}";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
        ]
    ]);
    
    $json = @file_get_contents($apiUrl, false, $context);
    
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['formatStreams'])) {
            echo "Success on {$instance}!\n";
            echo "Stream count: " . count($data['formatStreams']) . "\n";
            foreach ($data['formatStreams'] as $stream) {
                echo "- " . $stream['resolution'] . " (" . $stream['container'] . "): " . $stream['url'] . "\n";
            }
            exit;
        } else {
            echo "Failed to find formatStreams on {$instance}\n";
            print_r($data);
        }
    } else {
        echo "Failed to fetch from {$instance}\n";
        $error = error_get_last();
        echo "Error: " . $error['message'] . "\n";
    }
    echo "-------------------\n";
}
