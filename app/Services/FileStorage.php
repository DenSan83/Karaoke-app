<?php

/**
 * Thread-safe file storage helper with proper locking
 */
class FileStorage {
    /**
     * Read JSON file with shared lock (multiple readers allowed)
     */
    public static function readJson($filepath, $default = []) {
        if (!file_exists($filepath)) {
            return $default;
        }

        $fp = fopen($filepath, 'r');
        if (!$fp) {
            return $default;
        }

        // Acquire shared lock (multiple readers can read simultaneously)
        if (flock($fp, LOCK_SH)) {
            clearstatcache(true, $filepath);
            $size = filesize($filepath);
            $content = $size > 0 ? fread($fp, $size) : '';
            flock($fp, LOCK_UN);
            fclose($fp);
            
            $data = json_decode($content, true);
            return $data ?: $default;
        }

        fclose($fp);
        return $default;
    }

    /**
     * Write JSON file with exclusive lock (only one writer at a time)
     */
    public static function writeJson($filepath, $data) {
        $fp = fopen($filepath, 'c');
        if (!$fp) {
            return false;
        }

        // Acquire exclusive lock (blocks all other readers and writers)
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    /**
     * Atomic read-modify-write operation
     * Callback receives current data and returns modified data
     */
    public static function atomicUpdate($filepath, $callback, $default = []) {
        $fp = fopen($filepath, 'c+');
        if (!$fp) {
            return false;
        }

        // Acquire exclusive lock immediately
        if (flock($fp, LOCK_EX)) {
            // Read current data
            clearstatcache(true, $filepath);
            $size = filesize($filepath);
            $content = $size > 0 ? fread($fp, $size) : '';
            $data = !empty($content) ? json_decode($content, true) : $default;
            
            if (!is_array($data)) {
                $data = $default;
            }

            // Apply modification
            $newData = $callback($data);

            // Write back
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }
}
