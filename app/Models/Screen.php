<?php

require_once 'app/Services/Database.php';

class Screen {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable() {
        $pdo = $this->db->getConnection();
        // Check if table has the new schema (secret_id column).
        // If not (old schema or missing), drop and recreate.
        try {
            $pdo->query("SELECT secret_id FROM `screens` LIMIT 1");
        } catch (Exception $e) {
            $pdo->exec("DROP TABLE IF EXISTS `screens`");
            $pdo->exec("CREATE TABLE `screens` (
                secret_id VARCHAR(36) PRIMARY KEY,
                public_code VARCHAR(6) NULL,
                group_id VARCHAR(10) NULL,
                created_at INT NOT NULL,
                paired_at INT NULL,
                UNIQUE KEY (public_code),
                INDEX (group_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    /**
     * Register or refresh a screen. Called every time the screen loads or rotates its code.
     * The secret_id is stable; only public_code changes on rotation.
     */
    public function register($secretId, $publicCode) {
        $pdo = $this->db->getConnection();
        $now = time();
        $stmt = $pdo->prepare(
            "INSERT INTO `screens` (secret_id, public_code, group_id, created_at)
             VALUES (:sid, :code, NULL, :ts)
             ON DUPLICATE KEY UPDATE public_code = :code2"
        );
        $stmt->execute([
            ':sid'   => $secretId,
            ':code'  => $publicCode,
            ':ts'    => $now,
            ':code2' => $publicCode,
        ]);
    }

    /**
     * Admin pairs a screen by entering the public code.
     * Looks up the secret_id for that code, unpairs any screen already on this group,
     * then pairs the found screen.
     * Returns the secret_id on success, false if the public code is unknown.
     */
    public function pairByPublicCode($publicCode, $groupId) {
        $pdo = $this->db->getConnection();

        // Find the screen with this public code
        $stmt = $pdo->prepare("SELECT secret_id FROM `screens` WHERE public_code = :code");
        $stmt->execute([':code' => $publicCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;

        $secretId = $row['secret_id'];

        // Unpair any screen currently on this group
        $unpair = $pdo->prepare(
            "UPDATE `screens` SET group_id = NULL, paired_at = NULL WHERE group_id = :gid"
        );
        $unpair->execute([':gid' => $groupId]);

        // Pair the new screen
        $pair = $pdo->prepare(
            "UPDATE `screens` SET group_id = :gid, paired_at = :ts WHERE secret_id = :sid"
        );
        $pair->execute([':gid' => $groupId, ':ts' => time(), ':sid' => $secretId]);

        return $secretId;
    }

    /**
     * Check if a screen (by secret_id) is currently paired, and return its group_id.
     */
    public function getPairingBySecret($secretId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare(
            "SELECT group_id FROM `screens` WHERE secret_id = :sid"
        );
        $stmt->execute([':sid' => $secretId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
