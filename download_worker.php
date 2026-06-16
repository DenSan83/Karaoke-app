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
$videoUrl = "https://www.youtube.com/watch?v={$videoId}";
$outputFile = "public/media/videos/{$videoId}.mp4";
$absoluteOutputPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputFile);
$progressFile = __DIR__ . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'progress_' . $videoId . '.json';

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

$pipes = [];
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
    
    $errors = stream_get_contents($pipes[2]);
    if (!empty($errors)) {
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Worker Error for {$videoId}: {$errors}\n", FILE_APPEND);
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $returnVar = proc_close($process);
} else {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Worker failed to start proc_open for {$videoId}\n", FILE_APPEND);
    $returnVar = -1;
}

// Cleanup progress file
if (file_exists($progressFile)) {
    unlink($progressFile);
}

// Update Database
if ($returnVar === 0 && file_exists($absoluteOutputPath)) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Worker Success for {$videoId}\n", FILE_APPEND);
    require_once __DIR__ . '/app/Services/Database.php';
    $db = Database::getInstance();
    
    // Update all matching video IDs in playlist table across all groups
    $db->query("UPDATE `playlist` SET downloading = 0, local_path = ? WHERE video_id = ? AND downloading = 1", [$outputFile, $videoId]);
} else {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Worker Finished with error for {$videoId}. Code: {$returnVar}\n", FILE_APPEND);
}
