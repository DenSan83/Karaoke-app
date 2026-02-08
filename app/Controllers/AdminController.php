<?php

class AdminController {
    public function index() {
        if (!isset($_SESSION['user'])) {
            header('Location: login');
            exit;
        }
        require_once 'views/admin.php';
    }

    public function requests() {
        if (!isset($_SESSION['user'])) {
            header('Location: login');
            exit;
        }
        require_once 'views/admin_requests.php';
    }
}
