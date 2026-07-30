<?php
/**
 * Playlist download state: the failure flag, the stall reconciliation that clears a
 * stuck hourglass, the retry path, and the guard that keeps a guest-supplied video
 * id off the command line.
 *
 * These tests run against a scratch database and must never call add() or spawn a
 * worker for a valid id, which would start a real download.
 */

require_once PROJECT_ROOT . '/app/Models/Playlist.php';

function playlist_model($groupId = 'default') {
    test_db();
    return new Playlist($groupId);
}

test('a finished download is reconciled to complete even if the worker never said so', function () {
    test_db_reset();
    $videoId = test_video_id('Done');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);
    write_fake_video($videoId);

    $playlist = playlist_model();
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(0, (int)$row['downloading'], 'A downloaded track must stop showing an hourglass');
    assert_same('public/media/videos/' . $videoId . '.mp4', $row['local_path']);

    $stored = fetch_playlist_row($videoId);
    assert_same(0, (int)$stored['downloading'], 'The completion was not written back to the row');
    assert_same(0, (int)$stored['download_failed']);
    assert_null($stored['download_error']);
});

test('an empty video file does not count as a finished download', function () {
    test_db_reset();
    $videoId = test_video_id('Empty');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1, 'added_at' => time()]);
    write_fake_video($videoId, '');

    $playlist = playlist_model();
    // yt-dlp writes .part first and renames on success, so a zero-byte .mp4 is not
    // a success; with a fresh added_at the row should simply be left alone.
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(1, (int)$row['downloading'], 'A zero-byte file was mistaken for a finished download');
});

test('a download that just started is left alone', function () {
    test_db_reset();
    $videoId = test_video_id('Fresh');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);
    write_progress_file($videoId, ['status' => 'spawning', 'percent' => 0, 'ts' => time()]);

    $playlist = playlist_model();
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(1, (int)$row['downloading'], 'A spawning download was killed off too early');
    assert_same(0, (int)$row['download_failed']);
});

test('a worker that never started is failed after the spawn threshold', function () {
    test_db_reset();
    $videoId = test_video_id('NoStart');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);
    write_progress_file(
        $videoId,
        ['status' => 'spawning', 'percent' => 0, 'ts' => time() - (Playlist::SPAWN_STALL_SECONDS + 30)],
        Playlist::SPAWN_STALL_SECONDS + 30
    );

    $playlist = playlist_model();
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(0, (int)$row['downloading']);
    assert_same(1, (int)$row['download_failed'], 'A worker that never started must be flagged, not left spinning');
    assert_contains('never started', $row['download_error']);

    $stored = fetch_playlist_row($videoId);
    assert_same(1, (int)$stored['download_failed'], 'The failure was not persisted');
    assert_contains('never started', $stored['download_error']);
});

test('a download still making progress is left alone', function () {
    test_db_reset();
    $videoId = test_video_id('Live');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);
    write_progress_file($videoId, ['status' => 'downloading', 'percent' => 42.0, 'ts' => time() - 30], 30);

    $playlist = playlist_model();
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(1, (int)$row['downloading'], 'A live download was cancelled');
});

test('a download stalled past the progress threshold is failed', function () {
    test_db_reset();
    $videoId = test_video_id('Stall');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);
    $age = Playlist::PROGRESS_STALL_SECONDS + 60;
    write_progress_file($videoId, ['status' => 'downloading', 'percent' => 17.5, 'ts' => time() - $age], $age);

    $playlist = playlist_model();
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(1, (int)$row['download_failed']);
    assert_contains('stalled', $row['download_error']);
    assert_contains('17.5%', $row['download_error'], 'The stall message should say how far it got');
});

test('a row with no progress file at all is failed once it is old enough', function () {
    test_db_reset();
    $videoId = test_video_id('Orphan');
    // This is the shape of the rows left behind by the original bug: downloading = 1,
    // no worker, no progress file, nothing to explain it.
    insert_playlist_row([
        'video_id' => $videoId,
        'downloading' => 1,
        'added_at' => time() - (Playlist::SPAWN_STALL_SECONDS + 60)
    ]);

    $playlist = playlist_model();
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(1, (int)$row['download_failed']);
    assert_contains('No progress was ever reported', $row['download_error']);
});

test('a freshly queued row with no progress file yet is left alone', function () {
    test_db_reset();
    $videoId = test_video_id('Queued');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1, 'added_at' => time()]);

    $playlist = playlist_model();
    $row = call_private($playlist, 'reconcileDownload', [fetch_playlist_row($videoId)]);

    assert_same(1, (int)$row['downloading'], 'A row queued a moment ago must not be failed');
});

test('getAll exposes the failure state the queue UI needs', function () {
    test_db_reset();
    $videoId = test_video_id('Api');
    insert_playlist_row([
        'video_id' => $videoId,
        'downloading' => 0,
        'download_failed' => 1,
        'download_error' => 'Worker never started',
        'local_path' => null
    ]);

    $rows = json_decode(playlist_model()->getAll(), true);
    assert_same(1, count($rows));

    // local_path is the key the badge reads; it was local_file for a while and the
    // 'saved' badge silently never appeared.
    assert_array_has_keys(
        ['id', 'title', 'user', 'added_at', 'downloading', 'download_failed', 'download_error', 'local_path'],
        $rows[0]
    );
    assert_same($videoId, $rows[0]['id']);
    assert_true($rows[0]['download_failed'], 'download_failed must reach the client as a boolean true');
    assert_same('Worker never started', $rows[0]['download_error']);
});

test('getAll reconciles a stuck row while listing the queue', function () {
    test_db_reset();
    $videoId = test_video_id('Heal');
    insert_playlist_row([
        'video_id' => $videoId,
        'downloading' => 1,
        'added_at' => time() - (Playlist::SPAWN_STALL_SECONDS + 60)
    ]);

    // Simply opening the queue is what clears a stuck hourglass; nothing else runs.
    $rows = json_decode(playlist_model()->getAll(), true);

    assert_false($rows[0]['downloading'], 'The stuck row was still reported as downloading');
    assert_true($rows[0]['download_failed']);
});

test('markDownloadFailed truncates a long error to the column width', function () {
    test_db_reset();
    $videoId = test_video_id('Long');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);

    playlist_model()->markDownloadFailed($videoId, str_repeat('very long yt-dlp output ', 40));

    $stored = fetch_playlist_row($videoId);
    assert_true(strlen($stored['download_error']) <= 250, 'download_error must fit its VARCHAR(255) column');
    assert_same(1, (int)$stored['download_failed']);
});

test('markDownloadFailed collapses whitespace so the tooltip stays readable', function () {
    test_db_reset();
    $videoId = test_video_id('Space');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);

    playlist_model()->markDownloadFailed($videoId, "  ERROR: something\n\tbroke   badly  ");

    assert_same('ERROR: something broke badly', fetch_playlist_row($videoId)['download_error']);
});

test('markDownloadFailed only touches rows still waiting on a download', function () {
    test_db_reset();
    $videoId = test_video_id('Cross');
    insert_playlist_row(['group_id' => 'default', 'video_id' => $videoId, 'downloading' => 1]);
    insert_playlist_row([
        'group_id' => 'other',
        'video_id' => $videoId,
        'downloading' => 0,
        'local_path' => 'public/media/videos/' . $videoId . '.mp4'
    ]);

    playlist_model()->markDownloadFailed($videoId, 'Worker died');

    // Cross-group on purpose: the download failed for every queue waiting on it.
    assert_same(1, (int)fetch_playlist_row($videoId, 'default')['download_failed']);
    // But a queue that already has the file must not be marked failed.
    $settled = fetch_playlist_row($videoId, 'other');
    assert_same(0, (int)$settled['download_failed'], 'An already-downloaded row was wrongly failed');
});

test('markDownloadFailed removes the stale progress file', function () {
    test_db_reset();
    $videoId = test_video_id('Clean');
    insert_playlist_row(['video_id' => $videoId, 'downloading' => 1]);
    $progressFile = write_progress_file($videoId, ['status' => 'spawning', 'ts' => time()]);

    playlist_model()->markDownloadFailed($videoId, 'Worker died');

    clearstatcache(true, $progressFile);
    assert_false(is_file($progressFile), 'A failed download must not leave a progress file behind');
});

test('markDownloadComplete clears the failure state for every queue', function () {
    test_db_reset();
    $videoId = test_video_id('Fixed');
    insert_playlist_row([
        'group_id' => 'default',
        'video_id' => $videoId,
        'downloading' => 1,
        'download_failed' => 1,
        'download_error' => 'previous failure'
    ]);
    insert_playlist_row([
        'group_id' => 'other',
        'video_id' => $videoId,
        'downloading' => 1,
        'download_failed' => 1,
        'download_error' => 'previous failure'
    ]);

    $path = 'public/media/videos/' . $videoId . '.mp4';
    playlist_model()->markDownloadComplete($videoId, $path);

    foreach (['default', 'other'] as $groupId) {
        $row = fetch_playlist_row($videoId, $groupId);
        assert_same(0, (int)$row['downloading'], "group $groupId still downloading");
        assert_same(0, (int)$row['download_failed'], "group $groupId still flagged as failed");
        assert_null($row['download_error'], "group $groupId kept a stale error");
        assert_same($path, $row['local_path']);
    }
});

test('retrying a track whose file is already there does not start a worker', function () {
    test_db_reset();
    $videoId = test_video_id('Have');
    insert_playlist_row([
        'video_id' => $videoId,
        'downloading' => 0,
        'download_failed' => 1,
        'download_error' => 'Worker never started'
    ]);
    write_fake_video($videoId);

    $result = playlist_model()->retryDownload($videoId);

    assert_true(!empty($result['success']));
    assert_true(!empty($result['already_downloaded']), 'An existing file should short-circuit the retry');

    $stored = fetch_playlist_row($videoId);
    assert_same(0, (int)$stored['download_failed']);
    assert_same('public/media/videos/' . $videoId . '.mp4', $stored['local_path']);

    // No worker means no spawn artifacts.
    assert_false(is_file(project_path('temp/spawn_' . $videoId . '.vbs')));
});

test('retrying a track that is not queued reports an error', function () {
    test_db_reset();

    $result = playlist_model()->retryDownload(test_video_id('Ghost'));

    assert_true(isset($result['error']));
    assert_contains('not in the queue', $result['error']);
});

test('an unsafe video id is never handed to a shell', function () {
    test_db_reset();
    $playlist = playlist_model();

    // Video ids originate in a guest-supplied URL. Before the fix they were
    // interpolated straight into exec().
    $unsafe = ['abc', str_repeat('a', 21), 'abc; rm -rf /', 'abc$(whoami)', '../../etc/passwd', 'abc def', ''];

    foreach ($unsafe as $videoId) {
        $result = $playlist->spawnBackgroundDownload($videoId);

        assert_true(isset($result['error']), "spawnBackgroundDownload accepted '$videoId'");
        assert_contains('unsafe video id', $result['error'], "Wrong rejection reason for '$videoId'");

        // Nothing may be written to disk for a rejected id, let alone executed.
        assert_false(is_file(project_path('temp/spawn_' . $videoId . '.vbs')), "A spawn script was written for '$videoId'");
        assert_false(is_file(project_path('temp/progress_' . $videoId . '.json')), "A progress file was written for '$videoId'");
    }
});

test('a refused spawn is recorded in the superadmin activity log', function () {
    test_db_reset();

    playlist_model()->spawnBackgroundDownload('bad id; whoami');

    // The whole point of the change: nothing fails silently any more.
    $logs = fetch_activity_logs('worker_spawn');
    assert_same(1, count($logs), 'A refused spawn must leave exactly one worker_spawn entry');

    $data = json_decode($logs[0]['data'], true);
    assert_same('error', $data['status']);
    assert_contains('unsafe video id', $data['reason']);
});

test('a reconciled failure is recorded in the superadmin activity log', function () {
    test_db_reset();
    $videoId = test_video_id('Logged');
    insert_playlist_row([
        'video_id' => $videoId,
        'downloading' => 1,
        'added_at' => time() - (Playlist::SPAWN_STALL_SECONDS + 60)
    ]);

    call_private(playlist_model(), 'reconcileDownload', [fetch_playlist_row($videoId)]);

    $logs = fetch_activity_logs('worker_event');
    assert_same(1, count($logs));

    $data = json_decode($logs[0]['data'], true);
    assert_same('failed', $data['status']);
    assert_same($videoId, $data['videoId']);
    assert_contains('No progress was ever reported', $data['message']);
});

test('temp artifacts older than a day are pruned and live ones are kept', function () {
    test_db();
    $playlist = playlist_model();

    $old = [
        track_path(project_path('temp/worker_zzPruneOld01.log')),
        track_path(project_path('temp/progress_zzPruneOld01.json')),
        track_path(project_path('temp/spawn_zzPruneOld01.vbs'))
    ];
    $fresh = [
        track_path(project_path('temp/worker_zzPruneNew01.log')),
        track_path(project_path('temp/progress_zzPruneNew01.json')),
        track_path(project_path('temp/spawn_zzPruneNew01.vbs'))
    ];

    foreach (array_merge($old, $fresh) as $path) {
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, 'x');
    }
    foreach ($old as $path) {
        touch($path, time() - 86400 - 3600);
    }
    clearstatcache();

    call_private($playlist, 'pruneTempArtifacts');

    foreach ($old as $path) {
        clearstatcache(true, $path);
        assert_false(is_file($path), 'A day-old artifact should have been pruned: ' . basename($path));
    }
    foreach ($fresh as $path) {
        clearstatcache(true, $path);
        assert_true(is_file($path), 'A live artifact must never be pruned: ' . basename($path));
    }
});

test('pruning never reaches a file belonging to a running download', function () {
    // The prune window has to sit clear of both stall thresholds, otherwise a slow
    // but healthy download could have its own progress file deleted underneath it.
    $reflection = new ReflectionMethod('Playlist', 'pruneTempArtifacts');
    $default = $reflection->getParameters()[0]->getDefaultValue();

    assert_true(
        $default > Playlist::PROGRESS_STALL_SECONDS,
        'The prune age must exceed the stall threshold'
    );
    assert_true($default >= 86400, 'The prune age should stay at a day or more');
});

test('the video id pattern accepts real YouTube ids and nothing else', function () {
    $valid = ['mfivXPztcKU', 'dQw4w9WgXcQ', 'a_b-c1234', 'ZZZZZ'];
    foreach ($valid as $videoId) {
        assert_matches(Playlist::VIDEO_ID_PATTERN, $videoId, "Rejected a legitimate id: $videoId");
    }

    $invalid = ['abcd', str_repeat('a', 21), 'abc def', 'abc.def', 'abc/def', 'abc;id', '../abcd', ''];
    foreach ($invalid as $videoId) {
        assert_false(
            (bool)preg_match(Playlist::VIDEO_ID_PATTERN, $videoId),
            "Accepted an unsafe id: '$videoId'"
        );
    }
});
