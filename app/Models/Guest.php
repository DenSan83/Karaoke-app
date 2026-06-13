<?php

require_once 'app/Services/FileStorage.php';

class Guest {
    private $file = 'guests.json';
    private $groupId;

    public function __construct($groupId = null) {
        if ($groupId) {
            $this->file = 'guests_' . $groupId . '.json';
        }
        if (!file_exists($this->file)) {
            FileStorage::writeJson($this->file, ['guests' => []]);
        }
        $this->groupId = $groupId;
    }

    private function getData() {
        return FileStorage::readJson($this->file, ['guests' => []]);
    }

    private function saveData($data) {
        return FileStorage::writeJson($this->file, $data);
    }

    public function add($name) {
        if (empty($name)) {
            return ['error' => 'Name is required'];
        }

        $data = $this->getData();
        $id = uniqid('guest_');
        
        $newGuest = [
            'id' => $id,
            'name' => $name,
            'created_at' => time(),
            'songs' => [],
            'notifications' => []
        ];

        $data['guests'][] = $newGuest;
        
        if ($this->saveData($data)) {
            return ['success' => true, 'guest' => $newGuest];
        } else {
            return ['error' => 'Failed to save guest data'];
        }
    }

    public function getById($id) {
        $data = $this->getData();
        foreach ($data['guests'] as $guest) {
            if ($guest['id'] === $id) {
                // Ensure notifications array exists for older guest records
                if (!isset($guest['notifications'])) {
                    $guest['notifications'] = [];
                }
                return $guest;
            }
        }
        return null;
    }

    public function findDuplicateRequest($videoId, $excludeGuestId) {
        $data = $this->getData();
        if (!isset($data['guests']) || !is_array($data['guests'])) return null;
        
        $videoId = trim($videoId);
        foreach ($data['guests'] as $guest) {
            // Skip the current guest's own list
            if ($guest['id'] === $excludeGuestId) continue;
            
            if (isset($guest['songs']) && is_array($guest['songs'])) {
                foreach ($guest['songs'] as $song) {
                    if (isset($song['id']) && trim($song['id']) === $videoId) {
                        return $guest; 
                    }
                }
            }
        }
        return null;
    }

    public function addNotification($guestId, $notification) {
        $data = $this->getData();
        $found = false;
        foreach ($data['guests'] as &$guest) {
            if ($guest['id'] === $guestId) {
                if (!isset($guest['notifications'])) $guest['notifications'] = [];
                $notification['id'] = uniqid('notif_');
                $notification['timestamp'] = time();
                $guest['notifications'][] = $notification;
                $found = true;
                break;
            }
        }

        if ($found) {
            return $this->saveData($data);
        }
        return false;
    }

    public function removeSong($guestId, $videoId) {
        $data = $this->getData();
        $found = false;
        foreach ($data['guests'] as &$guest) {
            if ($guest['id'] === $guestId) {
                $originalCount = count($guest['songs']);
                $guest['songs'] = array_filter($guest['songs'], function($s) use ($videoId) {
                    return $s['id'] !== $videoId;
                });
                $guest['songs'] = array_values($guest['songs']); // Reset indices
                if (count($guest['songs']) !== $originalCount) {
                    $found = true;
                }
                break;
            }
        }

        if ($found) {
            return $this->saveData($data);
        }
        return false;
    }

    public function addSong($guestId, $songData) {
        $data = $this->getData();
        $found = false;
        
        foreach ($data['guests'] as &$guest) {
            if ($guest['id'] === $guestId) {
                // songData should have title, id (youtube id), etc.
                $songData['added_at'] = time();
                $songData['status'] = 'Waiting';
                $guest['songs'][] = $songData;
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ['error' => 'Guest not found'];
        }

        if ($this->saveData($data)) {
            return ['success' => true];
        } else {
            return ['error' => 'Failed to add song to guest queue'];
        }
    }

    public function reorderSongs($guestId, $newSongs) {
        $data = $this->getData();
        $found = false;
        
        foreach ($data['guests'] as &$guest) {
            if ($guest['id'] === $guestId) {
                $guest['songs'] = $newSongs;
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ['error' => 'Guest not found'];
        }

        if ($this->saveData($data)) {
            return ['success' => true];
        } else {
            return ['error' => 'Failed to save new order'];
        }
    }

    public function getAll() {
        return $this->getData();
    }

    public function dismissNotification($guestId, $notificationId) {
        $data = $this->getData();
        $found = false;
        foreach ($data['guests'] as &$guest) {
            if ($guest['id'] === $guestId) {
                if (isset($guest['notifications'])) {
                    $guest['notifications'] = array_filter($guest['notifications'], function($n) use ($notificationId) {
                        return $n['id'] !== $notificationId;
                    });
                    $guest['notifications'] = array_values($guest['notifications']);
                    $found = true;
                }
                break;
            }
        }

        if ($found) {
            return $this->saveData($data);
        }
        return false;
    }

    public function updateSongStatus($guestId, $videoId, $status) {
        $data = $this->getData();
        $found = false;
        
        foreach ($data['guests'] as &$guest) {
            // If guestId is provided, check it. If null, search all guests (useful for admin actions)
            if ($guestId === null || $guest['id'] === $guestId) {
                if (isset($guest['songs'])) {
                    foreach ($guest['songs'] as &$song) {
                        if ($song['id'] === $videoId) {
                            $song['status'] = $status;
                            $found = true;
                            // If we found the specific guest, break. If searching all, continue? 
                            // Usually a videoId is unique per guest, but multiple guests might request same song.
                            // If searching all, we might update multiple.
                            if ($guestId !== null) break 2; 
                        }
                    }
                }
                if ($guestId !== null && $guest['id'] === $guestId) break;
            }
        }

        if ($found) {
            return $this->saveData($data);
        }
        return false;
    }

    public function remove($id) {
        $data = $this->getData();
        $originalCount = count($data['guests']);
        $data['guests'] = array_filter($data['guests'], function($g) use ($id) {
            return $g['id'] !== $id;
        });
        $data['guests'] = array_values($data['guests']);

        if (count($data['guests']) !== $originalCount) {
            return $this->saveData($data);
        }
        return false;
    }
}
