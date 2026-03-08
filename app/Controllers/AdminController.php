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

        $hotelCode = $settings->get('hotel_code');
        $allowNewSessions = $settings->get('allow_new_sessions', true);

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

    public function generateDistantCode() {
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');

        if (empty($name) || empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Name and Email are required']);
            exit;
        }

        require_once 'app/Services/EncryptionService.php';
        $payload = [
            'name' => $name,
            'email' => $email,
            'salt' => bin2hex(openssl_random_pseudo_bytes(4)),
            'type' => 'distant',
            'created_at' => time()
        ];

        $code = EncryptionService::encrypt($payload);
        echo json_encode(['success' => true, 'code' => $code]);
    }

    public function generateHotelCode() {
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        header('Content-Type: application/json');
        
        // Generate random 6-digit number
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        if ($settings->set('hotel_code', $code)) {
            echo json_encode(['success' => true, 'code' => $code]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save hotel code']);
        }
    }

    public function toggleSession() {
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $allow = (bool)($data['allow'] ?? true);

        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        if ($settings->set('allow_new_sessions', $allow)) {
            echo json_encode(['success' => true, 'allow' => $allow]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update session status']);
        }
    }
}
