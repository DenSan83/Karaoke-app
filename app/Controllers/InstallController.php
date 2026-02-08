<?php

require_once 'app/Services/SystemCheck.php';

class InstallController {
    public function index() {
        require_once 'views/installing.php';
    }

    public function install() {
        header('Content-Type: application/json');
        
        $method = $_POST['method'] ?? '';
        
        switch ($method) {
            case 'project':
                $result = SystemCheck::installToProject();
                break;
            case 'system':
                $result = SystemCheck::installToSystem();
                break;
            case 'test':
                $result = SystemCheck::testInstallation();
                break;
            default:
                $result = ['success' => false, 'error' => 'Invalid installation method'];
        }
        
        echo json_encode($result);
    }

    public function getInstructions() {
        header('Content-Type: application/json');
        echo json_encode(SystemCheck::getManualInstructions());
    }
}
