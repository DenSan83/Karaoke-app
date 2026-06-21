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
            $redirect = $isSuperAdmin ? 'superadmin' : 'admin';
            echo json_encode(['success' => true, 'redirect' => $redirect]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    }

    public function logout() {
        $guestId = $_SESSION['guest_id'] ?? null;
        $groupId = $_SESSION['group_id'] ?? null;
        
        if ($guestId) {
            // 1. Remove from guests.json
            require_once 'app/Models/Guest.php';
            $guestModel = new Guest($groupId);
            $guestModel->remove($guestId);

            // 2. Remove activity logs
            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog($groupId);
            $sysLog->removeLogsByGuestId($guestId);
            
            $redirect = ($this->basePath ?: '') . '/';
        } else {
            $redirect = ($this->basePath ?: '') . '/login';
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
        header('Location: ' . $redirect);
        exit;
    }
}
