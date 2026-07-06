<?php

class PlayerController {
    public function index($basePath = '') {
        $basePath = $basePath ?: './';
        require_once 'views/player.php';
    }
}
