<?php

require_once 'app/Services/Database.php';

class Group {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM `groups` ");
    }

    public function getById($id) {
        return $this->db->fetch("SELECT * FROM `groups` WHERE id = ?", [$id]);
    }

    public function getByAdminUsername($username) {
        return $this->db->fetch("SELECT * FROM `groups` WHERE admin_username = ?", [$username]);
    }

    public function create($name, $admin_username, $duration_type, $valid_from = null, $valid_to = null) {
        $groups = $this->getAll();
        $id = $this->generateUniqueId($groups, 4);
        $pin = $this->generateUniquePin($groups, 6);
        
        $sql = "INSERT INTO `groups` (id, name, admin_username, admin_pin, duration_type, valid_from, valid_to, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $createdAt = time();
        $success = $this->db->query($sql, [$id, $name, $admin_username, $pin, $duration_type, $valid_from, $valid_to, $createdAt]);
        
        if ($success) {
            return [
                'id' => $id,
                'name' => $name,
                'admin_username' => $admin_username,
                'admin_pin' => $pin,
                'duration_type' => $duration_type,
                'valid_from' => $valid_from,
                'valid_to' => $valid_to,
                'created_at' => $createdAt
            ];
        }
        return false;
    }

    public function update($id, $data) {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "`$key` = ?";
            $params[] = $value;
        }
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE `groups` SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->db->query($sql, $params);
    }

    public function delete($id) {
        return $this->db->query("DELETE FROM `groups` WHERE id = ?", [$id]);
    }

    public function updateRelatedTable($table, $oldGroupId, $newGroupId) {
        $sql = "UPDATE `$table` SET group_id = ? WHERE group_id = ?";
        $result = $this->db->query($sql, [$newGroupId, $oldGroupId]);
        
        // Also update data in activity_logs if it contains the old ID (e.g. in JSON data)
        if ($table === 'activity_logs') {
             $sqlJson = "UPDATE `activity_logs` SET data = REPLACE(data, ?, ?) WHERE group_id = ?";
             $this->db->query($sqlJson, [$oldGroupId, $newGroupId, $newGroupId]);
        }
        
        return $result;
    }

    private function generateUniqueId($groups, $length) {
        do {
            $id = '';
            for ($i = 0; $i < $length; $i++) {
                $id .= mt_rand(0, 9);
            }
            $exists = false;
            foreach ($groups as $group) {
                if ($group['id'] === $id) {
                    $exists = true;
                    break;
                }
            }
        } while ($exists);
        return $id;
    }

    private function generateUniquePin($groups, $length) {
        do {
            $pin = '';
            for ($i = 0; $i < $length; $i++) {
                $pin .= mt_rand(0, 9);
            }
            $exists = false;
            foreach ($groups as $group) {
                if ($group['admin_pin'] === $pin) {
                    $exists = true;
                    break;
                }
            }
        } while ($exists);
        return $pin;
    }

    public function isValid($group) {
        if ($group['duration_type'] === 'unlimited') {
            return true;
        }
        $now = time();
        $from = strtotime($group['valid_from']);
        $to = strtotime($group['valid_to']);
        return ($now >= $from && $now <= $to);
    }
    
    public function resetPin($id) {
        $pin = $this->generateUniquePin($this->getAll(), 6);
        return $this->update($id, ['admin_pin' => $pin]);
    }
}
