<?php

require_once 'app/Services/Database.php';

class SystemLog {
    private $db;
    private $groupId;

    public function __construct($groupId = null) {
        $this->db = Database::getInstance();
        $this->groupId = $groupId ?: 'default';
    }

    /**
     * Log an activity
     * @param string $type e.g. 'user_login', 'track_added', 'system_event'
     * @param array $data Specific data for the event
     */
    public function log($type, $data) {
        $id = uniqid('log_');
        $timestamp = time();
        $sql = "INSERT INTO `activity_logs` (id, group_id, type, timestamp, data) VALUES (?, ?, ?, ?, ?)";
        return $this->db->query($sql, [$id, $this->groupId, $type, $timestamp, json_encode($data)]);
    }

    /**
     * Get logs by type
     * @param string|null $type
     * @return array
     */
    public function getLogs($type = null) {
        if ($type) {
            $sql = "SELECT * FROM `activity_logs` WHERE group_id = ? AND type = ? ORDER BY timestamp DESC";
            $rows = $this->db->fetchAll($sql, [$this->groupId, $type]);
        } else {
            $sql = "SELECT * FROM `activity_logs` WHERE group_id = ? ORDER BY timestamp DESC";
            $rows = $this->db->fetchAll($sql, [$this->groupId]);
        }

        $logs = [];
        foreach ($rows as $row) {
            $logs[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'timestamp' => (int)$row['timestamp'],
                'data' => json_decode($row['data'], true)
            ];
        }
        return $logs;
    }

    /**
     * Remove all logs associated with a specific guest ID
     */
    public function removeLogsByGuestId($guestId) {
        if (!$guestId) return false;
        
        // This is tricky because data is JSON. MySQL 5.7+ has JSON support, but let's be compatible.
        // We'll fetch all, filter and delete/update or use LIKE if data is simple.
        // Actually, simpler to just delete where data contains the guestId.
        $sql = "DELETE FROM `activity_logs` WHERE group_id = ? AND data LIKE ?";
        return $this->db->query($sql, [$this->groupId, '%' . $guestId . '%']);
    }

    /**
     * Batch update logs (used for backfill persistence)
     */
    public function updateLogs($logs) {
        // Clear and re-insert or just ignore for now as it's for migration/backfill
        $this->db->query("DELETE FROM `activity_logs` WHERE group_id = ?", [$this->groupId]);
        foreach ($logs as $log) {
            $this->log($log['type'], $log['data']);
        }
        return true;
    }
}
