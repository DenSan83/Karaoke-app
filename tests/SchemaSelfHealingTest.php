<?php
/**
 * The download_failed / download_error columns and the path that adds them.
 *
 * index.php calls SystemCheck::checkDatabase() and, when it returns false,
 * SystemCheck::fixSchema(), which runs the migration. So a column only heals itself
 * on deploy if it is listed in BOTH the migration and the check list — that pairing
 * is what these tests protect.
 */

require_once PROJECT_ROOT . '/app/Services/SystemCheck.php';

test('the migration creates the playlist table with the download failure columns', function () {
    $source = read_project_file('migrate_json_to_mysql.php');

    $start = strpos($source, "'playlist' => \"CREATE TABLE");
    assert_true($start !== false, 'The playlist CREATE TABLE statement could not be found');
    $ddl = substr($source, $start, 900);

    assert_contains('download_failed TINYINT(1) NOT NULL DEFAULT 0', $ddl);
    assert_contains('download_error VARCHAR(255) NULL', $ddl);
});

test('checkDatabase knows about the download failure columns', function () {
    $source = read_project_file('app/Services/SystemCheck.php');

    $start = strpos($source, "'playlist' => [");
    assert_true($start !== false, 'The playlist column list could not be found');
    $columns = substr($source, $start, 300);

    // Missing from this list means checkDatabase() returns true on an old schema and
    // fixSchema() never runs, so the new columns never appear on an existing install.
    assert_contains("'download_failed'", $columns, 'checkDatabase would not notice a missing download_failed column');
    assert_contains("'download_error'", $columns, 'checkDatabase would not notice a missing download_error column');
});

test('fixSchema runs the migration so a deploy heals itself', function () {
    $source = read_project_file('app/Services/SystemCheck.php');

    assert_contains("require_once __DIR__ . '/../../migrate_json_to_mysql.php'", $source);
    assert_contains('runMigration()', $source);
});

test('the migration adds the columns to an existing playlist table', function () {
    $pdo = test_db_reset();

    // Stand up the pre-change schema, exactly what an installation from before this
    // work looks like.
    $pdo->exec('DROP TABLE IF EXISTS `playlist`');
    $pdo->exec("CREATE TABLE `playlist` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id VARCHAR(10) NOT NULL DEFAULT 'default',
        video_id VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        user VARCHAR(255) NOT NULL,
        added_at INT NOT NULL,
        downloading BOOLEAN DEFAULT FALSE,
        local_path VARCHAR(255) NULL,
        sort_order INT DEFAULT 0,
        INDEX (group_id),
        UNIQUE KEY (group_id, video_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $before = playlist_column_names($pdo);
        assert_false(in_array('download_failed', $before, true), 'The legacy fixture already has the column');

        load_function_copy('migrate_json_to_mysql.php', 'updatePlaylistSchema');
        updatePlaylistSchema($pdo, false);

        $after = playlist_column_names($pdo);
        assert_true(in_array('download_failed', $after, true), 'download_failed was not added');
        assert_true(in_array('download_error', $after, true), 'download_error was not added');

        // The upgrade runs on every page load through fixSchema(), so it has to be
        // safe to repeat.
        updatePlaylistSchema($pdo, false);
        assert_same($after, playlist_column_names($pdo), 'Re-running the upgrade changed the schema again');
    } finally {
        $pdo->exec('DROP TABLE IF EXISTS `playlist`');
        test_db_create_tables($pdo);
    }
});

test('an existing row keeps working after the columns are added', function () {
    $pdo = test_db_reset();

    // A queued row from before the change has no failure state; the default must
    // read as "not failed" rather than NULL, which the UI would show as a badge.
    insert_playlist_row(['video_id' => test_video_id('Legacy'), 'downloading' => 1]);
    $row = fetch_playlist_row(test_video_id('Legacy'));

    assert_same(0, (int)$row['download_failed']);
    assert_null($row['download_error']);
});

function playlist_column_names(PDO $pdo) {
    $names = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `playlist`')->fetchAll() as $column) {
        $names[] = $column['Field'];
    }
    sort($names);
    return $names;
}
