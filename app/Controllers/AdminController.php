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

    public function codes() {
        if (!isset($_SESSION['user'])) {
            header('Location: ../login');
            exit;
        }
        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        
        // Migrate legacy single code or default if needed
        $legacyCode = $settings->get('guest_code');
        $guestCodes = $settings->get('guest_codes'); // No default here to detect missing key
        
        if ($guestCodes === null) {
            // Settings key missing. 
            // Check legacy code or default to TODAY26
            $default = $legacyCode ? $legacyCode : 'TODAY26';
            $guestCodes = [$default];
            
            // Should we save this state immediately or just show it? 
            // Just showing it lets the user decide.
        }
        
        // Ensure it's an array
        if (!is_array($guestCodes)) {
             $guestCodes = [];
        }

        require_once 'views/admin_codes.php';
    }

    public function updateCode() {
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Expecting an array of codes now, but handle single string for robustness
        $codes = $data['codes'] ?? [];
        if (!is_array($codes)) {
            // If legacy 'code' field sent
            $singleCode = $data['code'] ?? '';
            if ($singleCode) {
                $codes = [$singleCode];
            } else {
                $codes = [];
            }
        }

        // Filter empty
        $codes = array_filter(array_map('trim', $codes));
        $codes = array_values($codes); // Re-index

        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        
        if ($settings->update(function($current) use ($codes) {
            $current['guest_codes'] = $codes;
            // Remove legacy field
            unset($current['guest_code']);
            // Add category
            $current['category'] = 'in person';
            return $current;
        })) {
            echo json_encode(['success' => true, 'codes' => $codes]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save codes']);
        }
    }
}
