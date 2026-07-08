<?php

class AuthController {
    private $basePath;

    public function __construct($basePath = '') {
        $this->basePath = $basePath;
    }

    public function index() {
        if (isset($_SESSION['user'])) {
            header('Location: ' . ($this->basePath ?: '') . '/admin');
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
        $isSuperAdmin = false;

        // Check if banned
        require_once 'app/Models/ClientLog.php';
        $clientLog = new ClientLog();
        $clientId = $_COOKIE['karaoke_client_id'] ?? null;
        if ($clientId && $clientLog->isBanned($clientId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }

        // Check superadmin first
        foreach ($users as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $username = $parts[0];
                $hash = $parts[1];

                if ($username === $usernameInput && password_verify($passwordInput, $hash)) {
                    $authenticated = true;
                    $isSuperAdmin = true;
                    break;
                }
            }
        }

        // If not superadmin, check group admins
        if (!$authenticated) {
            require_once 'app/Models/Group.php';
            $groupModel = new Group();
            $group = $groupModel->getByAdminUsername($usernameInput);
            
            if ($group && $group['admin_pin'] === $passwordInput) {
                // Check if the group is still valid
                if ($groupModel->isValid($group)) {
                    $authenticated = true;
                    $_SESSION['group_id'] = $group['id'];
                } else {
                    // Time is over, reset pin and close session (handled by being invalid here)
                    $groupModel->resetPin($group['id']);
                    echo json_encode(['success' => false, 'message' => 'Party time is over.']);
                    return;
                }
            }
        }

        if ($authenticated) {
            $_SESSION['user'] = $usernameInput;
            $_SESSION['is_superadmin'] = $isSuperAdmin;

            // Log the login
            require_once 'app/Models/SystemLog.php';
            $logGroupId = $isSuperAdmin ? 'system' : ($_SESSION['group_id'] ?? 'default');
            $sysLog = new SystemLog($logGroupId);
            $sysLog->log('user_login', [
                'username' => $usernameInput,
                'role' => $isSuperAdmin ? 'superadmin' : 'admin',
                'timestamp' => time()
            ]);

            $redirect = $isSuperAdmin ? 'superadmin' : 'admin';
            echo json_encode(['success' => true, 'redirect' => $redirect]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    }

    public function logout() {
        // Log logout before clearing session
        require_once 'app/Models/ClientLog.php';
        $clientLog = new ClientLog();

        $guestId = $_SESSION['guest_id'] ?? null;
        $groupId = $_SESSION['group_id'] ?? null;
        
        if ($guestId) {
            require_once 'app/Models/Guest.php';
            $guestModel = new Guest($groupId);
            $guest = $guestModel->getById($guestId);
            $identity = $guest['name'] ?? 'guest';
            
            // Try-catch for client log as it might fail on DB issues
            try {
                $clientLog->logout($groupId, 'guest', $identity);
            } catch (Exception $e) {}

            // 1. Remove from guests.json
            $guestModel->remove($guestId);

            // 2. Remove activity logs
            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog($groupId);
            try {
                $sysLog->removeLogsByGuestId($guestId);
            } catch (Exception $e) {}
            
            $redirect = ($this->basePath ?: '') . '/';
        } else {
            $isSuperAdmin = $_SESSION['is_superadmin'] ?? false;
            $identity = $_SESSION['user'] ?? ($isSuperAdmin ? 'superadmin' : 'admin');
            $type = $isSuperAdmin ? 'superadmin' : 'admin';
            $logGroupId = $isSuperAdmin ? 'system' : ($groupId ?? 'default');
            
            // Log logout to ClientLog
            try {
                $clientLog->logout($logGroupId, $type, $identity);
            } catch (Exception $e) {}

            // Also log to SystemLog for admins
            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog($logGroupId);
            try {
                $sysLog->log('user_logout', [
                    'username' => $identity,
                    'role' => $type,
                    'timestamp' => time()
                ]);
            } catch (Exception $e) {}

            $redirect = ($this->basePath ?: '') . '/login';
        }

        // Add a temporary flag to session to prevent index.php from re-marking as online
        $_SESSION['is_logging_out'] = true;
        
        // Ensure we don't have double slashes if basePath is empty but we added one
        if (strpos($redirect, '//') === 0 && strpos($redirect, '///') !== 0) {
            $redirect = '/' . ltrim($redirect, '/');
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: ' . $redirect . (strpos($redirect, '?') === false ? '?' : '&') . 'logging_out=1');
        exit;
    }
}
