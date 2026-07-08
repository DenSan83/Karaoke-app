<?php

require_once 'app/Services/Database.php';
require_once 'app/Services/FileStorage.php';

function runMigration() {
    // Load .env
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $_ENV[trim($parts[0])] = trim($parts[1]);
            }
        }
    } else {
        if (php_sapi_name() === 'cli') {
            die(".env file not found. Please create it based on .env.example\n");
        }
        return false;
    }

    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
    } catch (Exception $e) {
        if (php_sapi_name() === 'cli') {
            die("Database connection failed: " . $e->getMessage() . "\n");
        }
        return false;
    }

    $isCli = php_sapi_name() === 'cli';
    if ($isCli) echo "Creating tables...\n";

    $tables = [
        'groups' => "CREATE TABLE IF NOT EXISTS `groups` (
            id VARCHAR(10) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            admin_username VARCHAR(255) NOT NULL,
            admin_pin VARCHAR(10) NOT NULL,
            duration_type ENUM('unlimited', 'limited') NOT NULL,
            valid_from DATETIME NULL,
            valid_to DATETIME NULL,
            created_at INT NOT NULL,
            allow_fallback TINYINT(1) NOT NULL DEFAULT 0,
            must_have_words TEXT NULL,
            must_not_have_words TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        'settings' => "CREATE TABLE IF NOT EXISTS `settings` (
            group_id VARCHAR(10) NOT NULL DEFAULT 'default',
            setting_key VARCHAR(255) NOT NULL,
            setting_value TEXT,
            updated_at INT,
            PRIMARY KEY (group_id, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        'guests' => "CREATE TABLE IF NOT EXISTS `guests` (
            id VARCHAR(50) PRIMARY KEY,
            group_id VARCHAR(10) NOT NULL DEFAULT 'default',
            name VARCHAR(255) NOT NULL,
            songs LONGTEXT,
            notifications LONGTEXT,
            added_at INT NOT NULL,
            INDEX (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        'playlist' => "CREATE TABLE IF NOT EXISTS `playlist` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        'activity_logs' => "CREATE TABLE IF NOT EXISTS `activity_logs` (
            id VARCHAR(50) PRIMARY KEY,
            group_id VARCHAR(10) NOT NULL DEFAULT 'default',
            type VARCHAR(50) NOT NULL,
            timestamp INT NOT NULL,
            data LONGTEXT,
            INDEX (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        'player_status' => "CREATE TABLE IF NOT EXISTS `player_status` (
            group_id VARCHAR(10) PRIMARY KEY,
            command VARCHAR(50),
            payload LONGTEXT,
            command_timestamp DOUBLE,
            current_index INT,
            state VARCHAR(50),
            state_timestamp DOUBLE,
            last_updated DOUBLE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        'screens' => "CREATE TABLE IF NOT EXISTS `screens` (
            secret_id VARCHAR(36) PRIMARY KEY,
            public_code VARCHAR(6) NULL,
            group_id VARCHAR(10) NULL,
            created_at INT NOT NULL,
            paired_at INT NULL,
            UNIQUE KEY (public_code),
            INDEX (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        'client_connections' => "CREATE TABLE IF NOT EXISTS `client_connections` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id VARCHAR(10) NOT NULL,
            client_id VARCHAR(50) NOT NULL,
            type VARCHAR(20) NOT NULL, -- 'admin', 'screen', 'guest'
            identity VARCHAR(255),     -- admin username, guest stage names, or 'screen'
            data LONGTEXT,             -- JSON: browser, device, language, ip_address
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            banned_at DATETIME NULL,
            INDEX (group_id),
            INDEX (client_id),
            UNIQUE KEY (group_id, client_id, type, identity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            if ($isCli) echo "Table '$name' checked/created.\n";
            error_log("Migration: Table '$name' checked/created.");

            // Special handling for schema updates on client_connections
            if ($name === 'client_connections') {
                updateClientConnectionsSchema($pdo, $isCli);
            }
        } catch (Exception $e) {
            $msg = "Error creating table '$name': " . $e->getMessage();
            if ($isCli) echo $msg . "\n";
            error_log("Migration: " . $msg);
        }
    }
    if ($isCli) echo "Tables created successfully.\n";

    // Re-check tables existence to be sure before migrating data
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'groups'");
        if ($stmt->rowCount() === 0) {
            if ($isCli) echo "Failed to create tables. Migration aborted.\n";
            return false;
        }
    } catch (Exception $e) {
        if ($isCli) echo "Error verifying table creation: " . $e->getMessage() . "\n";
        return false;
    }

    // Now it's safe to load models that might use these tables
    // Note: We don't necessarily need to load them here if we redirect in index.php
    /*
    $models = [
        'app/Models/Group.php',
        'app/Models/Guest.php',
        'app/Models/Playlist.php',
        'app/Models/Settings.php',
        'app/Models/SystemLog.php',
        'app/Models/PlayerStatus.php'
    ];
    foreach ($models as $model) {
        if (file_exists($model)) {
            require_once $model;
        }
    }
    */

    // Migrate groups first as they might define which other files to look for
    migrateGroups($pdo);

    // Also scan for any other group-specific files that might not be in groups.json anymore
    // but still exist in the directory
    if ($isCli) echo "Scanning for additional group files...\n";
    $allFiles = scandir(__DIR__);
    $groupIds = [];
    foreach ($allFiles as $file) {
        if (preg_match('/^(settings|guests|playlist|activity_logs|status)_([a-zA-Z0-9]+)\.json$/', $file, $matches)) {
            $groupIds[$matches[2]] = true;
        }
    }

    // Filter out groups already migrated
    $groups = FileStorage::readJson('groups.json', []);
    foreach ($groups as $group) {
        unset($groupIds[$group['id']]);
    }

    foreach (array_keys($groupIds) as $groupId) {
        if ($isCli) echo "Found orphaned files for group: $groupId. Migrating...\n";
        migrateSettings($pdo, $groupId);
        migrateGuests($pdo, $groupId);
        migratePlaylist($pdo, $groupId);
        migrateLogs($pdo, $groupId);
        migrateStatus($pdo, $groupId);
    }

    // Delete JSON files after successful migration
    if ($isCli) echo "Cleaning up JSON files...\n";
    $filesToDelete = ['groups.json'];
    
    // We already migrated based on groups.json and a scan.
    // Let's just collect ALL matching json files that we've successfully migrated.
    foreach ($allFiles as $file) {
        if (preg_match('/^(groups|settings|guests|playlist|activity_logs|status)(_[a-zA-Z0-9]+)?\.json$/', $file)) {
            $filesToDelete[] = $file;
        }
    }
    $filesToDelete = array_unique($filesToDelete);

    foreach ($filesToDelete as $file) {
        if (file_exists($file)) {
            if (unlink($file)) {
                if ($isCli) echo "  Deleted $file\n";
            } else {
                if ($isCli) echo "  Failed to delete $file\n";
            }
        }
    }

    if ($isCli) echo "Migration finished.\n";
    return true;
}

function updateClientConnectionsSchema($pdo, $isCli) {
    // Check if identity column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `client_connections` LIKE 'identity'");
    if ($stmt->rowCount() === 0) {
        if ($isCli) echo "  Adding 'identity' column to 'client_connections'...\n";
        $pdo->exec("ALTER TABLE `client_connections` ADD COLUMN identity VARCHAR(255) AFTER type");
    }

    // Check if last_activity column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `client_connections` LIKE 'last_activity'");
    if ($stmt->rowCount() === 0) {
        if ($isCli) echo "  Adding 'last_activity' column to 'client_connections'...\n";
        $pdo->exec("ALTER TABLE `client_connections` ADD COLUMN last_activity DATETIME DEFAULT CURRENT_TIMESTAMP AFTER identity");
    }

    // Check if is_online column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `client_connections` LIKE 'is_online'");
    if ($stmt->rowCount() === 0) {
        if ($isCli) echo "  Adding 'is_online' column to 'client_connections'...\n";
        $pdo->exec("ALTER TABLE `client_connections` ADD COLUMN is_online TINYINT(1) DEFAULT 1 AFTER last_activity");
    }

    // Check if is_banned column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `client_connections` LIKE 'is_banned'");
    if ($stmt->rowCount() === 0) {
        if ($isCli) echo "  Adding 'is_banned' column to 'client_connections'...\n";
        $pdo->exec("ALTER TABLE `client_connections` ADD COLUMN is_banned TINYINT(1) DEFAULT 0 AFTER is_online");
    }

    // Check if banned_at column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `client_connections` LIKE 'banned_at'");
    if ($stmt->rowCount() === 0) {
        if ($isCli) echo "  Adding 'banned_at' column to 'client_connections'...\n";
        $pdo->exec("ALTER TABLE `client_connections` ADD COLUMN banned_at DATETIME NULL AFTER is_banned");
    }

    // Check if data column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `client_connections` LIKE 'data'");
    if ($stmt->rowCount() === 0) {
        if ($isCli) echo "  Adding 'data' column to 'client_connections'...\n";
        $pdo->exec("ALTER TABLE `client_connections` ADD COLUMN data LONGTEXT AFTER identity");

        // Optional: migrate old columns to JSON if they exist
        $stmt = $pdo->query("SHOW COLUMNS FROM `client_connections` LIKE 'browser'");
        if ($stmt->rowCount() > 0) {
            if ($isCli) echo "  Migrating old columns to 'data' JSON...\n";
            $pdo->exec("UPDATE `client_connections` SET data = JSON_OBJECT(
                'browser', browser,
                'device', device,
                'language', language,
                'ip_address', ip_address
            ) WHERE data IS NULL");
            
            // Drop old columns
            $pdo->exec("ALTER TABLE `client_connections` 
                DROP COLUMN browser,
                DROP COLUMN device,
                DROP COLUMN language,
                DROP COLUMN ip_address");
        }
    }

    // Check if unique key exists for (group_id, client_id, type, identity)
    $stmt = $pdo->query("SHOW INDEX FROM `client_connections` WHERE Key_name = 'unique_connection'");
    if ($stmt->rowCount() === 0) {
        if ($isCli) echo "  Adding unique constraint to 'client_connections'...\n";
        
        // Before adding the unique constraint, we must remove duplicates or the ALTER TABLE will fail.
        // We keep the row with the most recent created_at for each group/client/type/identity combination.
        $pdo->exec("DELETE c1 FROM client_connections c1
                   INNER JOIN client_connections c2 
                   WHERE c1.id < c2.id 
                   AND c1.group_id = c2.group_id 
                   AND c1.client_id = c2.client_id 
                   AND c1.type = c2.type 
                   AND (c1.identity = c2.identity OR (c1.identity IS NULL AND c2.identity IS NULL))");

        $pdo->exec("ALTER TABLE `client_connections` ADD UNIQUE KEY `unique_connection` (group_id, client_id, type, identity)");
    }
}

// Migration Logic
function migrateGroups($pdo) {
    if (php_sapi_name() === 'cli') echo "Migrating groups...\n";
    $groups = FileStorage::readJson('groups.json', []);
    $stmt = $pdo->prepare("INSERT INTO `groups` (id, name, admin_username, admin_pin, duration_type, valid_from, valid_to, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), admin_username=VALUES(admin_username), admin_pin=VALUES(admin_pin), duration_type=VALUES(duration_type), valid_from=VALUES(valid_from), valid_to=VALUES(valid_to), created_at=VALUES(created_at)");
    foreach ($groups as $group) {
        $stmt->execute([
            $group['id'],
            $group['name'],
            $group['admin_username'],
            $group['admin_pin'],
            $group['duration_type'],
            $group['valid_from'],
            $group['valid_to'],
            $group['created_at']
        ]);
        migrateSettings($pdo, $group['id']);
        migrateGuests($pdo, $group['id']);
        migratePlaylist($pdo, $group['id']);
        migrateLogs($pdo, $group['id']);
        migrateStatus($pdo, $group['id']);
    }
    // Also migrate default files (no group_id)
    migrateSettings($pdo, null);
    migrateGuests($pdo, null);
    migratePlaylist($pdo, null);
    migrateLogs($pdo, null);
    migrateStatus($pdo, null);
}

function migrateSettings($pdo, $groupId) {
    $file = $groupId ? "settings_$groupId.json" : "settings.json";
    if (!file_exists($file)) return;
    if (php_sapi_name() === 'cli') echo "  Migrating settings from $file...\n";
    $data = FileStorage::readJson($file, []);
    $updatedAt = $data['updated_at'] ?? time();
    $stmt = $pdo->prepare("INSERT INTO `settings` (group_id, setting_key, setting_value, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)");
    foreach ($data as $key => $value) {
        if ($key === 'updated_at') continue;
        $val = $groupId ?: 'default';
        $stmt->execute([$val, $key, is_array($value) ? json_encode($value) : $value, $updatedAt]);
    }
}

function migrateGuests($pdo, $groupId) {
    $file = $groupId ? "guests_$groupId.json" : "guests.json";
    if (!file_exists($file)) return;
    if (php_sapi_name() === 'cli') echo "  Migrating guests from $file...\n";
    $data = FileStorage::readJson($file, []);
    
    // Support both {"guests": [...]} and [...]
    $guests = isset($data['guests']) ? $data['guests'] : (isset($data[0]) ? $data : []);
    
    $stmt = $pdo->prepare("INSERT INTO `guests` (id, group_id, name, songs, notifications, added_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), songs=VALUES(songs), notifications=VALUES(notifications), added_at=VALUES(added_at)");
    foreach ($guests as $guest) {
        if (!isset($guest['id'])) continue;
        $stmt->execute([
            $guest['id'],
            $groupId ?: 'default',
            $guest['name'] ?? 'Unknown',
            isset($guest['songs']) ? json_encode($guest['songs']) : null,
            isset($guest['notifications']) ? json_encode($guest['notifications']) : null,
            $guest['added_at'] ?? ($guest['created_at'] ?? time())
        ]);
    }
}

function migratePlaylist($pdo, $groupId) {
    $file = $groupId ? "playlist_$groupId.json" : "playlist.json";
    if (!file_exists($file)) return;
    if (php_sapi_name() === 'cli') echo "  Migrating playlist from $file...\n";
    $data = FileStorage::readJson($file, []);
    
    // Support both {"playlist": [...]} and [...]
    $playlist = isset($data['playlist']) ? $data['playlist'] : (isset($data[0]) ? $data : []);
    
    $stmt = $pdo->prepare("INSERT INTO `playlist` (group_id, video_id, title, user, added_at, downloading, local_path, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), user=VALUES(user), added_at=VALUES(added_at), downloading=VALUES(downloading), local_path=VALUES(local_path), sort_order=VALUES(sort_order)");
    foreach ($playlist as $index => $item) {
        if (!isset($item['id'])) continue;
        $stmt->execute([
            $groupId ?: 'default',
            $item['id'],
            $item['title'] ?? 'Unknown',
            $item['user'] ?? 'Unknown',
            $item['added_at'] ?? time(),
            isset($item['downloading']) ? $item['downloading'] : 0,
            $item['local_path'] ?? null,
            $index
        ]);
    }
}

function migrateLogs($pdo, $groupId) {
    $file = $groupId ? "activity_logs_$groupId.json" : "activity_logs.json";
    if (!file_exists($file)) return;
    if (php_sapi_name() === 'cli') echo "  Migrating logs from $file...\n";
    $data = FileStorage::readJson($file, ['logs' => []]);
    
    // Support both {"logs": [...]} and [...]
    $logs = isset($data['logs']) ? $data['logs'] : (isset($data[0]) ? $data : []);
    
    $stmt = $pdo->prepare("INSERT INTO `activity_logs` (id, group_id, type, timestamp, data) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE type=VALUES(type), timestamp=VALUES(timestamp), data=VALUES(data)");
    foreach ($logs as $log) {
        if (!isset($log['id'])) continue;
        $stmt->execute([
            $log['id'],
            $groupId ?: 'default',
            $log['type'] ?? 'info',
            $log['timestamp'] ?? time(),
            isset($log['data']) ? json_encode($log['data']) : null
        ]);
    }
}

function migrateStatus($pdo, $groupId) {
    $file = $groupId ? "status_$groupId.json" : "status.json";
    if (!file_exists($file)) return;
    if (php_sapi_name() === 'cli') echo "  Migrating status from $file...\n";
    $data = FileStorage::readJson($file, []);
    if (empty($data)) return;
    
    $stmt = $pdo->prepare("INSERT INTO `player_status` (group_id, command, payload, command_timestamp, current_index, state, state_timestamp, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE command=VALUES(command), payload=VALUES(payload), command_timestamp=VALUES(command_timestamp), current_index=VALUES(current_index), state=VALUES(state), state_timestamp=VALUES(state_timestamp), last_updated=VALUES(last_updated)");
    $stmt->execute([
        $groupId ?: 'default',
        $data['command'] ?? null,
        isset($data['payload']) ? json_encode($data['payload']) : null,
        $data['command_timestamp'] ?? null,
        $data['current_index'] ?? null,
        $data['state'] ?? null,
        $data['state_timestamp'] ?? null,
        $data['last_updated'] ?? null
    ]);
}

if (php_sapi_name() === 'cli' || isset($GLOBALS['RUN_MIGRATION'])) {
    runMigration();
}
