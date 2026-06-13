<?php

require_once 'app/Services/Database.php';

class Guest {
    private $db;
    private $groupId;

    public function __construct($groupId = null) {
        $this->db = Database::getInstance();
        $this->groupId = $groupId ?: 'default';
    }

    private function getData() {
        $rows = $this->db->fetchAll("SELECT * FROM `guests` WHERE group_id = ?", [$this->groupId]);
        $guests = [];
        foreach ($rows as $row) {
            $guests[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'added_at' => (int)$row['added_at'],
                'songs' => json_decode($row['songs'], true) ?: [],
                'notifications' => json_decode($row['notifications'], true) ?: []
            ];
        }
        return ['guests' => $guests];
    }

    private function saveDataForGuest($guestId, $songs, $notifications) {
        $sql = "UPDATE `guests` SET songs = ?, notifications = ? WHERE id = ? AND group_id = ?";
        return $this->db->query($sql, [json_encode($songs), json_encode($notifications), $guestId, $this->groupId]);
    }

    public function add($name) {
        if (empty($name)) {
            return ['error' => 'Name is required'];
        }

        $id = uniqid('guest_');
        $now = time();
        
        $sql = "INSERT INTO `guests` (id, group_id, name, added_at, songs, notifications) VALUES (?, ?, ?, ?, ?, ?)";
        $success = $this->db->query($sql, [$id, $this->groupId, $name, $now, json_encode([]), json_encode([])]);
        
        if ($success) {
            return ['success' => true, 'guest' => [
                'id' => $id,
                'name' => $name,
                'created_at' => $now,
                'songs' => [],
                'notifications' => []
            ]];
        } else {
            return ['error' => 'Failed to save guest data'];
        }
    }

    public function getById($id) {
        $row = $this->db->fetch("SELECT * FROM `guests` WHERE id = ? AND group_id = ?", [$id, $this->groupId]);
        if ($row) {
            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'added_at' => (int)$row['added_at'],
                'songs' => json_decode($row['songs'], true) ?: [],
                'notifications' => json_decode($row['notifications'], true) ?: []
            ];
        }
        return null;
    }

    public function findDuplicateRequest($videoId, $excludeGuestId) {
        $videoId = trim($videoId);
        $rows = $this->db->fetchAll("SELECT * FROM `guests` WHERE group_id = ? AND id != ?", [$this->groupId, $excludeGuestId]);
        
        foreach ($rows as $row) {
            $songs = json_decode($row['songs'], true) ?: [];
            foreach ($songs as $song) {
                if (isset($song['id']) && trim($song['id']) === $videoId) {
                    return [
                        'id' => $row['id'],
                        'name' => $row['name']
                    ];
                }
            }
        }
        return null;
    }

    public function addNotification($guestId, $notification) {
        $guest = $this->getById($guestId);
        if ($guest) {
            $notifications = $guest['notifications'];
            $notification['id'] = uniqid('notif_');
            $notification['timestamp'] = time();
            $notifications[] = $notification;
            return $this->saveDataForGuest($guestId, $guest['songs'], $notifications);
        }
        return false;
    }

    public function removeSong($guestId, $videoId) {
        $guest = $this->getById($guestId);
        if ($guest) {
            $songs = $guest['songs'];
            $originalCount = count($songs);
            $songs = array_filter($songs, function($s) use ($videoId) {
                return $s['id'] !== $videoId;
            });
            $songs = array_values($songs);
            if (count($songs) !== $originalCount) {
                return $this->saveDataForGuest($guestId, $songs, $guest['notifications']);
            }
        }
        return false;
    }

    public function addSong($guestId, $songData) {
        $guest = $this->getById($guestId);
        if (!$guest) {
            return ['error' => 'Guest not found'];
        }

        $songs = $guest['songs'];
        $songData['added_at'] = time();
        $songData['status'] = 'Waiting';
        $songs[] = $songData;

        if ($this->saveDataForGuest($guestId, $songs, $guest['notifications'])) {
            return ['success' => true];
        } else {
            return ['error' => 'Failed to add song to guest queue'];
        }
    }

    public function reorderSongs($guestId, $newSongs) {
        $guest = $this->getById($guestId);
        if (!$guest) {
            return ['error' => 'Guest not found'];
        }

        if ($this->saveDataForGuest($guestId, $newSongs, $guest['notifications'])) {
            return ['success' => true];
        } else {
            return ['error' => 'Failed to save new order'];
        }
    }

    public function getAll() {
        return $this->getData();
    }

    public function dismissNotification($guestId, $notificationId) {
        $guest = $this->getById($guestId);
        if ($guest) {
            $notifications = $guest['notifications'];
            $notifications = array_filter($notifications, function($n) use ($notificationId) {
                return $n['id'] !== $notificationId;
            });
            $notifications = array_values($notifications);
            return $this->saveDataForGuest($guestId, $guest['songs'], $notifications);
        }
        return false;
    }

    public function updateSongStatus($guestId, $videoId, $status) {
        if ($guestId !== null) {
            $guest = $this->getById($guestId);
            if ($guest) {
                $songs = $guest['songs'];
                $found = false;
                foreach ($songs as &$song) {
                    if ($song['id'] === $videoId) {
                        $song['status'] = $status;
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    return $this->saveDataForGuest($guestId, $songs, $guest['notifications']);
                }
            }
        } else {
            // Admin action: search all guests in this group
            $rows = $this->db->fetchAll("SELECT * FROM `guests` WHERE group_id = ?", [$this->groupId]);
            $anyFound = false;
            foreach ($rows as $row) {
                $songs = json_decode($row['songs'], true) ?: [];
                $foundInThisGuest = false;
                foreach ($songs as &$song) {
                    if ($song['id'] === $videoId) {
                        $song['status'] = $status;
                        $foundInThisGuest = true;
                        $anyFound = true;
                    }
                }
                if ($foundInThisGuest) {
                    $this->saveDataForGuest($row['id'], $songs, json_decode($row['notifications'], true));
                }
            }
            return $anyFound;
        }
        return false;
    }

    public function remove($id) {
        return $this->db->query("DELETE FROM `guests` WHERE id = ? AND group_id = ?", [$id, $this->groupId]);
    }
}
