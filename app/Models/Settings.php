<?php

require_once 'app/Services/Database.php';

class Settings {
    private $db;
    private $groupId;

    public function __construct($groupId = null) {
        $this->db = Database::getInstance();
        $this->groupId = $groupId ?: 'default';
    }

    public function get($key, $default = null) {
        $sql = "SELECT setting_value FROM `settings` WHERE group_id = ? AND setting_key = ?";
        $row = $this->db->fetch($sql, [$this->groupId, $key]);
        if ($row) {
            $value = $row['setting_value'];
            // Try to decode JSON if it looks like JSON
            $decoded = json_decode($value, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        }
        return $default;
    }

    public function set($key, $value) {
        $sql = "INSERT INTO `settings` (group_id, setting_key, setting_value, updated_at) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)";
        $valToStore = is_array($value) ? json_encode($value) : $value;
        return $this->db->query($sql, [$this->groupId, $key, $valToStore, time()]);
    }

    public function getAll() {
        $sql = "SELECT setting_key, setting_value FROM `settings` WHERE group_id = ?";
        $rows = $this->db->fetchAll($sql, [$this->groupId]);
        $settings = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            $decoded = json_decode($value, true);
            $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        }
        return $settings;
    }

    public function update($callback) {
        $current = $this->getAll();
        $newData = $callback($current);
        foreach ($newData as $key => $value) {
            $this->set($key, $value);
        }
        return true;
    }
}
