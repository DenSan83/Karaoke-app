<?php

require_once 'app/Services/Database.php';

class ClientLog {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function logConnection($groupId, $type, $identity = null) {
        if (!$groupId) return false;

        $clientId = $this->getOrCreateClientId();
        
        $metadata = [
            'client_id' => $clientId,
            'browser' => $this->getBrowserInfo(),
            'device' => $this->getDeviceType(),
            'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
            'ip_address' => $this->getIpAddress()
        ];

        // Add fingerprint from session if available
        if (isset($_SESSION['guest_fingerprint'])) {
            $metadata['fingerprint'] = $_SESSION['guest_fingerprint'];
        }

        try {
            $sql = "INSERT INTO `client_connections` (group_id, client_id, type, identity, last_activity, is_online, data) 
                    VALUES (?, ?, ?, ?, NOW(), 1, ?)
                    ON DUPLICATE KEY UPDATE 
                    last_activity = NOW(),
                    is_online = 1,
                    data = VALUES(data),
                    created_at = CURRENT_TIMESTAMP";
            return $this->db->query($sql, [$groupId, $clientId, $type, $identity, json_encode($metadata)]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                return false;
            }
            throw $e;
        }
    }

    public function updateActivity($groupId, $type, $identity) {
        if (!$groupId) return false;
        $clientId = $this->getOrCreateClientId();
        try {
            $sql = "UPDATE `client_connections` 
                    SET last_activity = NOW(), is_online = 1 
                    WHERE group_id = ? AND client_id = ? AND type = ? AND identity = ?";
            return $this->db->query($sql, [$groupId, $clientId, $type, $identity]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                return false;
            }
            throw $e;
        }
    }

    public function logout($groupId, $type, $identity) {
        if (!$groupId) return false;
        $clientId = $this->getOrCreateClientId();
        try {
            $sql = "UPDATE `client_connections` 
                    SET is_online = 0 
                    WHERE group_id = ? AND client_id = ? AND type = ? AND identity = ?";
            return $this->db->query($sql, [$groupId, $clientId, $type, $identity]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                return false;
            }
            throw $e;
        }
    }

    private function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            // If multiple IPs, take the first one
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        return $ip;
    }

    public function getConnectionsByGroup($groupId) {
        try {
            $sql = "SELECT * FROM `client_connections` 
                    WHERE group_id = ? 
                    ORDER BY created_at DESC";
            $rows = $this->db->fetchAll($sql, [$groupId]);
            return $this->groupIdentities($rows, true);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') return [];
            throw $e;
        }
    }

    public function getAllClients() {
        try {
            $sql = "SELECT * FROM `client_connections` 
                    ORDER BY created_at DESC";
            $rows = $this->db->fetchAll($sql);
            return $this->groupIdentities($rows, false);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') return [];
            throw $e;
        }
    }

    private function groupIdentities($rows, $byGroupOnly = true) {
        $clients = [];
        foreach ($rows as $row) {
            $key = $byGroupOnly ? $row['client_id'] : $row['client_id'] . '_' . $row['group_id'];
            if (!isset($clients[$key])) {
                $clients[$key] = [
                    'client_id' => $row['client_id'],
                    'group_id' => $row['group_id'],
                    'type' => $row['type'],
                    'created_at' => $row['created_at'],
                    'max_last_activity' => $row['last_activity'],
                    'is_banned' => $row['is_banned'],
                    'data' => $row['data'],
                    'identities' => []
                ];
            }
            
            $clients[$key]['identities'][] = [
                'identity' => $row['identity'],
                'is_online' => $row['is_online'],
                'last_activity' => $row['last_activity']
            ];
            
            if (strtotime($row['last_activity']) > strtotime($clients[$key]['max_last_activity'])) {
                $clients[$key]['max_last_activity'] = $row['last_activity'];
            }
            if ($row['is_banned'] > $clients[$key]['is_banned']) {
                $clients[$key]['is_banned'] = $row['is_banned'];
            }
        }

        // Format identities as JSON string to match previous API behavior if needed, 
        // or just return the array. The previous code used JSON_ARRAYAGG which returns a JSON string in MySQL.
        foreach ($clients as &$client) {
            $client['identities_json'] = json_encode($client['identities']);
        }
        
        return array_values($clients);
    }

    public function clearConnectionsByGroup($groupId) {
        if (!$groupId) return false;
        try {
            $sql = "DELETE FROM `client_connections` WHERE group_id = ?";
            return $this->db->query($sql, [$groupId]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') return true;
            throw $e;
        }
    }

    public function deleteClient($clientId, $groupId) {
        if (!$clientId || !$groupId) return false;
        try {
            $sql = "DELETE FROM `client_connections` WHERE client_id = ? AND group_id = ?";
            return $this->db->query($sql, [$clientId, $groupId]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') return true;
            throw $e;
        }
    }

    public function banClient($clientId) {
        if (!$clientId) return false;
        try {
            // Mark as offline and banned for all entries of this client
            $sql = "UPDATE `client_connections` SET is_banned = 1, is_online = 0, banned_at = NOW() WHERE client_id = ?";
            return $this->db->query($sql, [$clientId]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') return false;
            throw $e;
        }
    }

    public function unbanClient($clientId) {
        if (!$clientId) return false;
        try {
            $sql = "UPDATE `client_connections` SET is_banned = 0, banned_at = NULL WHERE client_id = ?";
            return $this->db->query($sql, [$clientId]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') return true;
            throw $e;
        }
    }

    public function isBanned($clientId) {
        if (!$clientId) return false;
        try {
            $sql = "SELECT COUNT(*) as count FROM `client_connections` WHERE client_id = ? AND is_banned = 1";
            $result = $this->db->fetch($sql, [$clientId]);
            return ($result['count'] ?? 0) > 0;
        } catch (PDOException $e) {
            // If table doesn't exist, nobody is banned yet
            if ($e->getCode() == '42S02') {
                return false;
            }
            throw $e;
        }
    }

    public function getBanDetails($clientId) {
        if (!$clientId) return null;
        try {
            $sql = "SELECT group_id, banned_at FROM `client_connections` WHERE client_id = ? AND is_banned = 1 LIMIT 1";
            return $this->db->fetch($sql, [$clientId]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                return null;
            }
            throw $e;
        }
    }

    public function getOrCreateClientId() {
        if (!isset($_COOKIE['karaoke_client_id'])) {
            $clientId = bin2hex(random_bytes(16));
            setcookie('karaoke_client_id', $clientId, time() + (86400 * 365), "/");
            $_COOKIE['karaoke_client_id'] = $clientId;
        }
        return $_COOKIE['karaoke_client_id'];
    }

    private function getBrowserInfo() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        if (preg_match('/MSIE/i', $ua) && !preg_match('/Opera/i', $ua)) return 'Internet Explorer';
        if (preg_match('/Firefox/i', $ua)) return 'Firefox';
        if (preg_match('/Chrome/i', $ua)) return 'Chrome';
        if (preg_match('/Safari/i', $ua)) return 'Safari';
        if (preg_match('/Opera/i', $ua)) return 'Opera';
        if (preg_match('/Netscape/i', $ua)) return 'Netscape';
        return 'Unknown';
    }

    private function getDeviceType() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        if (preg_match('/mobile/i', $ua)) return 'Mobile';
        if (preg_match('/tablet/i', $ua)) return 'Tablet';
        return 'Desktop';
    }
}
