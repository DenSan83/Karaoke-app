<?php

class AuthController {
    public function index() {
        if (isset($_SESSION['user'])) {
            header('Location: admin');
            exit;
        }
        require_once 'views/login.php';
    }

    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        $usernameInput = $data['username'] ?? '';
        $passwordInput = $data['password'] ?? '';

        $htpwdPath = __DIR__ . '/../../.htpwd';
        if (!file_exists($htpwdPath)) {
            echo json_encode(['success' => false, 'message' => 'Auth system error']);
            return;
        }

        $users = file($htpwdPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $authenticated = false;

        foreach ($users as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $username = $parts[0];
                $hash = $parts[1];

                if ($username === $usernameInput && password_verify($passwordInput, $hash)) {
                    $authenticated = true;
                    break;
                }
            }
        }

        if ($authenticated) {
            $_SESSION['user'] = $usernameInput;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    }

    public function logout() {
        $guestId = $_SESSION['guest_id'] ?? null;
        
        if ($guestId) {
            // 1. Remove from guests.json
            require_once 'app/Models/Guest.php';
            $guestModel = new Guest();
            $guestModel->remove($guestId);

            // 2. Remove activity logs
            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog();
            $sysLog->removeLogsByGuestId($guestId);
            
            $redirect = './';
        } else {
            $redirect = 'login';
        }

        session_destroy();
        header('Location: ' . $redirect);
        exit;
    }
}
