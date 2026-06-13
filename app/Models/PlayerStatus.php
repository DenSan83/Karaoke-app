<?php

require_once 'app/Services/FileStorage.php';

class PlayerStatus {
    private $file = 'status.json';
    private $groupId;

    public function __construct($groupId = null) {
        if ($groupId) {
            $this->file = 'status_' . $groupId . '.json';
        }
        if (!file_exists($this->file)) {
            FileStorage::writeJson($this->file, []);
        }
        $this->groupId = $groupId;
    }

    public function get() {
        return FileStorage::readJson($this->file, []);
    }

    // This new update method replaces the private save method
    public function update($data) {
        return FileStorage::atomicUpdate($this->file, function($current) use ($data) {
            $mergedData = array_merge($current, $data);
            // Ensure timestamp is updated for polling logic to detect changes if needed,
            // though strictly 'command' timestamp cares about command,
            // and 'state' timestamp might care about state.
            // Let's keep a global timestamp for "last update"
            $mergedData['last_updated'] = microtime(true);
            return $mergedData;
        }, []);
    }

    public function set($command, $payload = []) {
        $allowedCommands = ['play', 'pause', 'next', 'restart', 'jump'];
        if (!in_array($command, $allowedCommands)) {
            return ['error' => 'Invalid command'];
        }

        // We only update the command part
        $data = [
            'command' => $command,
            'payload' => $payload,
            'command_timestamp' => microtime(true) // Specific timestamp for commands to avoid re-executing old ones
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
