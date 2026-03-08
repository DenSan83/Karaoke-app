<?php

require_once 'app/Services/FileStorage.php';

class SystemLog {
    private $file = 'activity_logs.json';

    public function __construct() {
        if (!file_exists($this->file)) {
            FileStorage::writeJson($this->file, ['logs' => []]);
        }
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
}
