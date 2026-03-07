<?php

require_once 'app/Services/FileStorage.php';

class Settings {
    private $file = 'settings.json';
    private $data = [];

    public function __construct() {
        if (!file_exists($this->file)) {
            FileStorage::writeJson($this->file, []);
        }
        $this->data = FileStorage::readJson($this->file, []);
    }

    public function get($key, $default = null) {
        // Refresh data to ensure latest settings
        $this->data = FileStorage::readJson($this->file, []);
        return $this->data[$key] ?? $default;
    }

    public function set($key, $value) {
        return FileStorage::atomicUpdate($this->file, function($current) use ($key, $value) {
            $current[$key] = $value;
            $current['updated_at'] = time();
            return $current;
        }, []);
    }

    public function getAll() {
        return FileStorage::readJson($this->file, []);
    }

    public function update($callback) {
        return FileStorage::atomicUpdate($this->file, function($current) use ($callback) {
            $newData = $callback($current);
            $newData['updated_at'] = time();
            return $newData;
        }, []);
    }
}
