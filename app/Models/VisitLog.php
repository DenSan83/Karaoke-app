<?php

require_once 'app/Services/Database.php';

class VisitLog {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Log a visit
     * @param string $page The visited page/section
     * @param array $data Technical data (browser, device, etc.)
     */
    public function logVisit($page, $data) {
        $sql = "INSERT INTO `visit_logs` (timestamp, page, data) VALUES (NOW(), ?, ?)";
        return $this->db->query($sql, [$page, json_encode($data)]);
    }

    /**
     * Get all visit logs
     * @param int $limit
     * @return array
     */
    public function getVisits($limit = 1000) {
        try {
            $sql = "SELECT * FROM `visit_logs` ORDER BY timestamp DESC LIMIT " . (int)$limit;
            return $this->db->fetchAll($sql);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') return [];
            throw $e;
        }
    }

    /**
     * Delete a visit log entry
     * @param int $id
     */
    public function deleteVisit($id) {
        $sql = "DELETE FROM `visit_logs` WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}
