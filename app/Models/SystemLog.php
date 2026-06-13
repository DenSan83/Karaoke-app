<?php

require_once 'app/Services/FileStorage.php';

class SystemLog {
    private $file = 'activity_logs.json';
    private $groupId;

    public function __construct($groupId = null) {
        if ($groupId) {
            $this->file = 'activity_logs_' . $groupId . '.json';
        }
        if (!file_exists($this->file)) {
            FileStorage::writeJson($this->file, ['logs' => []]);
        }
        $this->groupId = $groupId;
    }

    private function getData() {
        return FileStorage::readJson($this->file, ['logs' => []]);
    }

    private function saveData($data) {
        return FileStorage::writeJson($this->file, $data);
    }

    /**
     * Log an activity
     * @param string $type e.g. 'user_login', 'track_added', 'system_event'
     * @param array $data Specific data for the event
     */
    public function log($type, $data) {
        $allData = $this->getData();
        
        $entry = [
            'id' => uniqid('log_'),
            'type' => $type,
            'timestamp' => time(),
            'data' => $data
        ];

        $allData['logs'][] = $entry;
        
        // Optional: Limit total logs to avoid file bloat (e.g., keep last 5000)
        if (count($allData['logs']) > 5000) {
            array_shift($allData['logs']);
        }

        return $this->saveData($allData);
    }

    /**
     * Get logs by type
     * @param string|null $type
     * @return array
     */
    public function getLogs($type = null) {
        $allData = $this->getData();
        $logs = $allData['logs'] ?? [];

        if ($type) {
            return array_filter($logs, function($l) use ($type) {
                return $l['type'] === $type;
            });
        }

        return $logs;
    }

    /**
     * Remove all logs associated with a specific guest ID
     */
    public function removeLogsByGuestId($guestId) {
        if (!$guestId) return false;
        
        $allData = $this->getData();
        $originalCount = count($allData['logs']);
        
        $allData['logs'] = array_filter($allData['logs'], function($l) use ($guestId) {
            return ($l['data']['guestId'] ?? '') !== $guestId;
        });
        $allData['logs'] = array_values($allData['logs']);

        if (count($allData['logs']) !== $originalCount) {
            return $this->saveData($allData);
        }
        return true;
    }

    /**
     * Batch update logs (used for backfill persistence)
     */
    public function updateLogs($logs) {
        $allData = $this->getData();
        $allData['logs'] = $logs;
        return $this->saveData($allData);
    }
}
