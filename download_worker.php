<?php
/**
 * Background Download Worker
 *
 * This script is spawned as a background process to download videos asynchronously.
 * It updates the playlist table when the download completes.
 *
 * Every outcome (start, success, failure, fatal) is written to the activity log so it
 * shows up on the superadmin Logs page, and a failure is written back to the row as
 * download_failed so the admin queue can offer a retry instead of a stuck hourglass.
 *
 * Usage: php download_worker.php <videoId> [groupId]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script must be run from the command line.\n");
}

if ($argc < 2) {
    die("Usage: php download_worker.php <videoId> [groupId]\n");
}

$videoId = $argv[1];
$groupId = isset($argv[2]) && $argv[2] !== '' ? $argv[2] : 'default';

// The id reaches a shell command line, so validate it rather than trusting the caller.
if (!preg_match('/^[A-Za-z0-9_-]{5,20}$/', $videoId)) {
    die("Invalid video id.\n");
}

$videoUrl = "https://www.youtube.com/watch?v={$videoId}";
$outputFile = "public/media/videos/{$videoId}.mp4";
$absoluteOutputPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputFile);
$progressFile = __DIR__ . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'progress_' . $videoId . '.json';
$workerLogFile = __DIR__ . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'worker_' . $videoId . '.log';

// Ensure directories exist
if (!is_dir(__DIR__ . '/temp')) {
    mkdir(__DIR__ . '/temp', 0777, true);
}

if (!is_dir(__DIR__ . '/public/media/videos')) {
    mkdir(__DIR__ . '/public/media/videos', 0777, true);
}

// Overwrite the 'spawning' marker left by the caller: this is the proof that the
// worker actually started, which is what tells a stalled spawn from a slow download.
file_put_contents($progressFile, json_encode(['status' => 'starting', 'percent' => 0, 'ts' => time()]));

require_once __DIR__ . '/app/Services/Database.php';
require_once __DIR__ . '/app/Models/SystemLog.php';
require_once __DIR__ . '/app/Services/SystemCheck.php';

$db = null;
$sysLog = null;

/**
 * Log to the activity table, but never let a logging problem kill the download.
 */
function workerLog($type, array $data) {
    global $sysLog;
    if ($sysLog === null) {
        return;
    }
    try {
        $sysLog->log($type, $data);
    } catch (Exception $e) {
        error_log('download_worker: failed to write activity log: ' . $e->getMessage());
    }
}

try {
    $db = Database::getInstance();
    $sysLog = new SystemLog($groupId);
} catch (Exception $e) {
    // Without a database there is nothing to update; the spawn log file keeps the trace.
    error_log('download_worker: no database connection: ' . $e->getMessage());
    fwrite(STDERR, "Database unavailable: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Pick the one line of yt-dlp output worth showing in the UI.
 *
 * Its stderr usually opens with unrelated noise ("your version is older than
 * 90 days..."), which would otherwise be all that fits in download_error.
 * The full output still goes to the activity log.
 */
function summariseYtDlpError($stderr) {
    $lines = [];
    foreach (preg_split('/\r?\n/', (string)$stderr) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    if (empty($lines)) {
        return '';
    }

    // yt-dlp may print a warning traceback before the actual terminal error. Read
    // backwards and only accept its top-level ERROR lines so a PermissionError in
    // a warning does not hide the real download failure.
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (preg_match('/^ERROR\s*:/i', $lines[$i])) {
            return $lines[$i];
        }
    }

    return end($lines);
}

/** Run one yt-dlp attempt while publishing its progress to the admin queue. */
function runYtDlpAttempt($command, $progressFile, $status) {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $pipes = [];
    $process = function_exists('proc_open') ? proc_open($command, $descriptors, $pipes) : false;

    if (!is_resource($process)) {
        return [
            'exitCode' => -1,
            'errors' => function_exists('proc_open')
                ? 'proc_open() refused to start yt-dlp'
                : 'proc_open() is disabled in php.ini'
        ];
    }

    while ($line = fgets($pipes[1])) {
        if (preg_match('/(\d+(\.\d+)?)%/', $line, $matches)) {
            file_put_contents($progressFile, json_encode([
                'status' => $status,
                'percent' => (float)$matches[1],
                'ts' => time()
            ]));
        }
        flush();
    }

    $errors = (string)stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exitCode' => proc_close($process), 'errors' => $errors];
}

/**
 * Persist a failure on every queue row waiting for this video.
 */
function markFailed($videoId, $reason) {
    global $db;
    if (!$db) {
        return;
    }
    try {
        $db->query(
            "UPDATE `playlist` SET downloading = 0, download_failed = 1, download_error = ? WHERE video_id = ? AND downloading = 1",
            [substr(preg_replace('/\s+/', ' ', trim($reason)), 0, 250), $videoId]
        );
    } catch (Exception $e) {
        error_log('download_worker: failed to flag the row: ' . $e->getMessage());
    }
}

// A fatal error (out of memory, killed pipe, uncaught throwable) must not leave the
// row spinning either.
$finished = false;
register_shutdown_function(function () use (&$finished, $videoId, $progressFile) {
    if ($finished) {
        return;
    }
    $error = error_get_last();
    $message = $error ? $error['message'] : 'Worker exited before finishing';
    workerLog('worker_event', ['status' => 'aborted', 'videoId' => $videoId, 'message' => $message]);
    markFailed($videoId, 'Worker aborted: ' . $message);
    if (file_exists($progressFile)) {
        unlink($progressFile);
    }
});

$ytDlpPath = SystemCheck::getYtDlpPath();
// Canonicalise the '/../..' relative segments so the quoted path is valid on Windows.
// A bare command resolved through PATH has no realpath and is used as-is.
$resolvedYtDlp = realpath($ytDlpPath);
if ($resolvedYtDlp !== false) {
    $ytDlpPath = $resolvedYtDlp;
}

workerLog('worker_event', [
    'status' => 'started',
    'videoId' => $videoId,
    'php_cli' => PHP_BINARY,
    'yt_dlp' => $ytDlpPath
]);

set_time_limit(0); // Unlimited execution time

$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'yt-dlp-cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$commandBase = escapeshellarg($ytDlpPath)
             . ' -o ' . escapeshellarg($absoluteOutputPath)
             . ' --cache-dir ' . escapeshellarg($cacheDir)
             . ' --newline --progress-template "%(progress._percent_str)s"';

// First try a ready-to-play file, which needs no ffmpeg merge.
$attempt = runYtDlpAttempt(
    $commandBase . ' -f "best[ext=mp4]/best" ' . escapeshellarg($videoUrl),
    $progressFile,
    'downloading'
);
$returnVar = $attempt['exitCode'];
$errors = $attempt['errors'];

// A format-selection failure means this video only offers separate streams. Record
// the real first error before starting the slower adaptive download.
if ($returnVar !== 0 && stripos($errors, 'Requested format is not available') !== false) {
    $firstError = summariseYtDlpError($errors);
    workerLog('worker_event', [
        'status' => 'format_fallback',
        'videoId' => $videoId,
        'exitCode' => $returnVar,
        'message' => $firstError,
        'output' => substr(trim($errors), 0, 2000)
    ]);

    file_put_contents($progressFile, json_encode(['status' => 'adaptive', 'percent' => 0, 'ts' => time()]));
    $attempt = runYtDlpAttempt(
        $commandBase
        . ' -f "bestvideo[ext=mp4]+bestaudio[ext=m4a]/bestvideo+bestaudio"'
        . ' --merge-output-format mp4 ' . escapeshellarg($videoUrl),
        $progressFile,
        'adaptive'
    );
    $returnVar = $attempt['exitCode'];
    $errors = $attempt['errors'];
}

// Cleanup progress file
if (file_exists($progressFile)) {
    unlink($progressFile);
}

// Update Database
if ($returnVar === 0 && file_exists($absoluteOutputPath) && filesize($absoluteOutputPath) > 0) {
    workerLog('worker_event', ['status' => 'success', 'videoId' => $videoId, 'file' => $outputFile]);

    // Cross-group and without a `downloading = 1` guard: the file exists now, so every
    // queue entry for this video can use it, including rows already flagged as failed.
    $db->query(
        "UPDATE `playlist` SET downloading = 0, download_failed = 0, download_error = NULL, local_path = ? WHERE video_id = ?",
        [$outputFile, $videoId]
    );

    // Silenced on purpose: this file is our own redirected stdout/stderr, and Windows
    // refuses to unlink it while the shell still holds the handle. Harmless either way,
    // the next spawn and the periodic prune both clear it.
    @unlink($workerLogFile);
} else {
    $summary = summariseYtDlpError($errors);
    if ($summary === '') {
        $summary = 'yt-dlp exited with code ' . var_export($returnVar, true) . ' and produced no file';
    }

    workerLog('worker_event', [
        'status' => 'failed',
        'videoId' => $videoId,
        'exitCode' => $returnVar,
        'yt_dlp' => $ytDlpPath,
        'message' => $summary,
        'output' => substr(trim((string)$errors), 0, 2000)
    ]);

    markFailed($videoId, $summary);
}

$finished = true;
