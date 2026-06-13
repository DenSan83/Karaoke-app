<?php

require_once 'app/Models/Group.php';

class SuperAdminController {
    private $groupModel;

    public function __construct() {
        if (!isset($_SESSION['user']) || !isset($_SESSION['is_superadmin']) || !$_SESSION['is_superadmin']) {
            header('Location: login');
            exit;
        }
        $this->groupModel = new Group();
    }

    public function index() {
        global $basePath;
        $groups = $this->groupModel->getAll();
        // Ensure $groups is always an array for the view
        if (!is_array($groups)) $groups = [];
        require_once 'views/superadmin/groups.php';
    }

    public function createGroup() {
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        $adminUsername = $data['admin_username'] ?? '';
        $durationType = $data['duration_type'] ?? 'unlimited';
        $validFrom = $data['valid_from'] ?? null;
        $validTo = $data['valid_to'] ?? null;

        if (empty($name) || empty($adminUsername)) {
            echo json_encode(['success' => false, 'message' => 'Name and Admin Username are required']);
            return;
        }

        $newGroup = $this->groupModel->create($name, $adminUsername, $durationType, $validFrom, $validTo);
        if ($newGroup) {
            $id = $newGroup['id'];

            // Initialize files for the new group
            try {
                require_once 'app/Models/Settings.php';
                require_once 'app/Models/PlayerStatus.php';
                require_once 'app/Models/Playlist.php';
                require_once 'app/Models/Guest.php';
                require_once 'app/Models/SystemLog.php';

                new Settings($id);
                new PlayerStatus($id);
                new Playlist($id);
                new Guest($id);
                new SystemLog($id);
            } catch (Throwable $e) {
                // Silently continue or log? The group is created in groups.json anyway.
            }
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create group']);
        }
    }

    public function deleteGroup() {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            return;
        }

        // Delete group files
        $filesToDelete = [
            "activity_logs_{$id}.json",
            "guests_{$id}.json",
            "playlist_{$id}.json",
            "settings_{$id}.json",
            "status_{$id}.json"
        ];
        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $success = $this->groupModel->delete($id);
        echo json_encode(['success' => $success]);
    }

    public function updateGroup() {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        $newId = $data['new_id'] ?? $id;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            return;
        }

        // Handle ID change if necessary
        if ($newId !== $id) {
            // Validate new ID is unique
            $existing = $this->groupModel->getById($newId);
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'New Party ID already exists']);
                return;
            }

            $filesToRename = [
                "activity_logs_{$id}.json" => "activity_logs_{$newId}.json",
                "guests_{$id}.json" => "guests_{$newId}.json",
                "playlist_{$id}.json" => "playlist_{$newId}.json",
                "settings_{$id}.json" => "settings_{$newId}.json",
                "status_{$id}.json" => "status_{$newId}.json"
            ];
            foreach ($filesToRename as $oldName => $newName) {
                if (file_exists($oldName)) {
                    rename($oldName, $newName);
                }
            }
            $data['id'] = $newId;
        }
        unset($data['new_id']);

        $success = $this->groupModel->update($id, $data);
        echo json_encode(['success' => $success]);
    }
}
