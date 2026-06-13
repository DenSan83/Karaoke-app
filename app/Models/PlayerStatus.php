<?php

require_once 'app/Services/Database.php';

class PlayerStatus {
    private $db;
    private $groupId;

    public function __construct($groupId = null) {
        $this->db = Database::getInstance();
        $this->groupId = $groupId ?: 'default';
    }

    public function get() {
        $row = $this->db->fetch("SELECT * FROM `player_status` WHERE group_id = ?", [$this->groupId]);
        if ($row) {
            $row['payload'] = json_decode($row['payload'], true);
            // Convert back to microtime-like floats for compatibility if they were stored as such
            $row['command_timestamp'] = (float)$row['command_timestamp'];
            $row['state_timestamp'] = (float)$row['state_timestamp'];
            $row['last_updated'] = (float)$row['last_updated'];
            return $row;
        }
        return [];
    }

    public function update($data) {
        $current = $this->get();
        $mergedData = array_merge($current, $data);
        $mergedData['last_updated'] = microtime(true);
        
        $sql = "INSERT INTO `player_status` (group_id, command, payload, command_timestamp, current_index, state, state_timestamp, last_updated) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                command = VALUES(command), 
                payload = VALUES(payload), 
                command_timestamp = VALUES(command_timestamp), 
                current_index = VALUES(current_index), 
                state = VALUES(state), 
                state_timestamp = VALUES(state_timestamp), 
                last_updated = VALUES(last_updated)";
        
        return $this->db->query($sql, [
            $this->groupId,
            $mergedData['command'] ?? null,
            isset($mergedData['payload']) ? json_encode($mergedData['payload']) : null,
            $mergedData['command_timestamp'] ?? null,
            $mergedData['current_index'] ?? null,
            $mergedData['state'] ?? null,
            $mergedData['state_timestamp'] ?? null,
            $mergedData['last_updated']
        ]);
    }

    public function set($command, $payload = []) {
        $allowedCommands = ['play', 'pause', 'next', 'restart', 'jump'];
        if (!in_array($command, $allowedCommands)) {
            return ['error' => 'Invalid command'];
        }

        $data = [
            'command' => $command,
            'payload' => $payload,
            'command_timestamp' => microtime(true)
        ];
        
        return $this->update($data);
    }

    public function updateState($index, $state) {
        return $this->update([
            'current_index' => $index,
            'state' => $state,
            'state_timestamp' => microtime(true)
        ]);
    }
}
