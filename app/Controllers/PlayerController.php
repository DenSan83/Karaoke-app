<?php

class PlayerController {
    public function index($basePath = '') {
        $groupId = $_GET['group_id'] ?? ($_SESSION['group_id'] ?? null);
        
        if ($groupId) {
            // Log connection for screen if not already logged in this session
            if (!isset($_SESSION['screen_logged_' . $groupId])) {
                require_once 'app/Models/ClientLog.php';
                $clientLog = new ClientLog();
                $clientLog->logConnection($groupId, 'screen', 'screen');
                $_SESSION['screen_logged_' . $groupId] = true;
            }
        }

        require_once 'views/player.php';
    }
}
