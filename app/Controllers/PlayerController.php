<?php

class PlayerController {
    public function index($groupId = null) {
        $groupId = $groupId ?: ($_SESSION['group_id'] ?? null);
        require_once 'app/Models/Group.php';
        $groupModel = new Group();
        $group = $groupModel->getById($groupId);
        if (!$group || !$groupModel->isValid($group)) {
             // Handle invalid group access for screen
             // Maybe show a generic message or redirect
        }
        require_once 'views/player.php';
    }
}
