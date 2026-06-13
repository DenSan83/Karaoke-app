<?php

require_once 'app/Services/FileStorage.php';

class Group {
    private $file = 'groups.json';

    public function __construct() {
        if (!file_exists($this->file)) {
            FileStorage::writeJson($this->file, []);
        }
    }

    public function getAll() {
        return FileStorage::readJson($this->file, []);
    }

    public function getById($id) {
        $groups = $this->getAll();
        foreach ($groups as $group) {
            if ($group['id'] === $id) {
                return $group;
            }
        }
        return null;
    }

    public function getByAdminUsername($username) {
        $groups = $this->getAll();
        foreach ($groups as $group) {
            if ($group['admin_username'] === $username) {
                return $group;
            }
        }
        return null;
    }

    public function create($name, $admin_username, $duration_type, $valid_from = null, $valid_to = null) {
        $newGroup = null;
        $success = FileStorage::atomicUpdate($this->file, function($groups) use ($name, $admin_username, $duration_type, $valid_from, $valid_to, &$newGroup) {
            $id = $this->generateUniqueId($groups, 4);
            $pin = $this->generateUniquePin($groups, 6);
            
            $newGroup = [
                'id' => $id,
                'name' => $name,
                'admin_username' => $admin_username,
                'admin_pin' => $pin,
                'duration_type' => $duration_type, // 'unlimited' or 'limited'
                'valid_from' => $valid_from,
                'valid_to' => $valid_to,
                'created_at' => time()
            ];
            $groups[] = $newGroup;
            return $groups;
        });
        return $success ? $newGroup : false;
    }

    public function update($id, $data) {
        return FileStorage::atomicUpdate($this->file, function($groups) use ($id, $data) {
            foreach ($groups as &$group) {
                if ($group['id'] === $id) {
                    // Check if we are changing the ID to something that already exists
                    if (isset($data['id']) && $data['id'] !== $id) {
                        foreach ($groups as $otherGroup) {
                            if ($otherGroup['id'] === $data['id']) {
                                // ID already exists, don't update this group's ID
                                // or we could return an error here, but atomicUpdate expects return of the data
                                // For now, let's just NOT update if it's a duplicate ID
                                unset($data['id']);
                                break;
                            }
                        }
                    }
                    $group = array_merge($group, $data);
                    break;
                }
            }
            return $groups;
        });
    }

    public function delete($id) {
        return FileStorage::atomicUpdate($this->file, function($groups) use ($id) {
            return array_filter($groups, function($group) use ($id) {
                return $group['id'] !== $id;
            });
        });
    }

    private function generateUniqueId($groups, $length) {
        do {
            $id = '';
            for ($i = 0; $i < $length; $i++) {
                $id .= mt_rand(0, 9);
            }
            $exists = false;
            foreach ($groups as $group) {
                if ($group['id'] === $id) {
                    $exists = true;
                    break;
                }
            }
        } while ($exists);
        return $id;
    }

    private function generateUniquePin($groups, $length) {
        do {
            $pin = '';
            for ($i = 0; $i < $length; $i++) {
                $pin .= mt_rand(0, 9);
            }
            $exists = false;
            foreach ($groups as $group) {
                if ($group['admin_pin'] === $pin) {
                    $exists = true;
                    break;
                }
            }
        } while ($exists);
        return $pin;
    }

    public function isValid($group) {
        if ($group['duration_type'] === 'unlimited') {
            return true;
        }
        $now = time();
        $from = strtotime($group['valid_from']);
        $to = strtotime($group['valid_to']);
        return ($now >= $from && $now <= $to);
    }
    
    public function resetPin($id) {
        return FileStorage::atomicUpdate($this->file, function($groups) use ($id) {
            foreach ($groups as &$group) {
                if ($group['id'] === $id) {
                    $group['admin_pin'] = $this->generateUniquePin($groups, 6);
                    break;
                }
            }
            return $groups;
        });
    }
}
