<?php
/**
 * Background Download Worker
 * 
 * This script is spawned as a background process to download videos asynchronously.
 * It updates the playlist.json file when the download completes.
 * 
 * Usage: php download_worker.php <videoId>
 */

if ($argc < 2) {
    die("Usage: php download_worker.php <videoId>\n");
}

$videoId = $argv[1];
$playlistFile = __DIR__ . '/playlist.json';
$videoUrl = "https://www.youtube.com/watch?v={$videoId}";
$outputFile = "public/media/videos/{$videoId}.mp4";
$absoluteOutputPath = __DIR__ . '/' . $outputFile;
$progressFile = __DIR__ . '/temp/progress_' . $videoId . '.json';

// Ensure directories exist
if (!is_dir(__DIR__ . '/temp')) {
    mkdir(__DIR__ . '/temp', 0777, true);
}

if (!is_dir(__DIR__ . '/public/media/videos')) {
    mkdir(__DIR__ . '/public/media/videos', 0777, true);
}

// Initialize progress
file_put_contents($progressFile, json_encode(['status' => 'starting', 'percent' => 0]));

// Determine OS and command
require_once __DIR__ . '/app/Services/SystemCheck.php';
$cmd = SystemCheck::getYtDlpPath();

// Command to download
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

// Update playlist.json
if ($returnVar === 0 && file_exists($absoluteOutputPath)) {
    // Read playlist
    $playlist = json_decode(file_get_contents($playlistFile), true);
    
    // Find the video with this ID and update it
    foreach ($playlist as &$video) {
        if ($video['id'] === $videoId && isset($video['downloading']) && $video['downloading'] === true) {
            $video['local_file'] = $outputFile;
            unset($video['downloading']); // Remove downloading flag
            break;
        }
    }
    
    // Save updated playlist
    file_put_contents($playlistFile, json_encode($playlist, JSON_PRETTY_PRINT));
}
