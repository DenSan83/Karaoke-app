<?php
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);
// Set session cookie and garbage collector lifetime to 4 hours (14400 seconds)
ini_set('session.gc_maxlifetime', 14400);
session_set_cookie_params(14400);

session_start();

// Load .env early so APP_TIMEZONE and other vars are available before any service init
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) $_ENV[trim($parts[0])] = trim($parts[1]);
    }
}
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Check system requirements (skip for installing page)
require_once 'app/Services/SystemCheck.php';
require_once 'app/Services/Database.php';

// Auto-detect base path from script location
$scriptName = $_SERVER['SCRIPT_NAME']; // e.g., /git_projects/08.karaoke_admin/index.php or /index.php
$basePath = str_replace('\\', '/', dirname($scriptName));
if ($basePath === '/') {
    $basePath = '';
}

// Get the requested route
$request = $_SERVER['REQUEST_URI'];
$route = str_replace($basePath, '', parse_url($request, PHP_URL_PATH));
$route = trim($route, '/');

if ($route !== 'installing' && strpos($route, 'api/install') !== 0) {
    if (!SystemCheck::checkDatabase()) {
        if (isset($_GET['migration_attempted'])) {
            header('Content-Type: text/plain; charset=utf-8');
            // Try to include more info
            $error = error_get_last();
            $error_msg = $error ? "\nLast error: " . $error['message'] : "";
            die("La migration a été tentée mais la base de données n'est toujours pas prête. Veuillez vérifier votre configuration .env et vos logs d'erreur." . $error_msg);
        }
        try {
            // Log attempt
            error_log("Database check failed, attempting auto-fix/migration...");
            
            require_once 'app/Services/SystemCheck.php';
            SystemCheck::fixSchema();
            
            // Re-check after fix
            if (SystemCheck::checkDatabase()) {
                error_log("Database fix successful.");
                $separator = (strpos($_SERVER['REQUEST_URI'], '?') === false) ? '?' : '&';
                header('Location: ' . $_SERVER['REQUEST_URI'] . $separator . 'migration_attempted=1');
                exit;
            } else {
                error_log("Database fix failed.");
                header('Content-Type: text/plain; charset=utf-8');
                die("Migration failed or database still not ready. Please check error logs.");
            }
        } catch (Exception $e) {
            error_log("Migration exception: " . $e->getMessage());
            header('Content-Type: text/plain; charset=utf-8');
            die("Migration error: " . $e->getMessage());
        }
    }
}

// Check if yt-dlp is available (skip check for installing routes and API)
if (!SystemCheck::checkYtDlp() &&
    $route !== 'installing' &&
    strpos($route, 'api/install') !== 0) {
    header('Location: ' . ($basePath ?: '') . '/installing');
    exit;
}

require_once 'app/Controllers/PlayerController.php';
require_once 'app/Controllers/AdminController.php';
require_once 'app/Controllers/ApiController.php';
require_once 'app/Controllers/AuthController.php';
require_once 'app/Controllers/SuperAdminController.php';
require_once 'app/Controllers/WelcomeController.php';

// Activity Tracking & Ban Check
if (!empty($_SESSION) || isset($_COOKIE['karaoke_client_id'])) {
    require_once 'app/Models/ClientLog.php';
    $clientLog = new ClientLog();
    $clientId = $_COOKIE['karaoke_client_id'] ?? null;

    // Check if client is banned
    $isBanned = false;
    try {
        $isBanned = $clientId && $clientLog->isBanned($clientId);
    } catch (Exception $e) {
        // If DB table is missing or other issue, assume not banned to let user continue
        // The migration should ideally have been caught by SystemCheck::checkDatabase()
        error_log("Ban check error: " . $e->getMessage());
    }

    if ($isBanned) {
        // If they are trying to access anything other than root or login with banned status, block them
        $allowed_banned_routes = ['', 'login', 'superadmin/login'];

        // Only log if they are NOT on an allowed route (meaning they are trying to access something they shouldn't)
        // and we haven't logged this specific route in the last 30 seconds to avoid redirect loops spam
        $last_banned_log = $_SESSION['last_banned_log'] ?? 0;
        $last_banned_route = $_SESSION['last_banned_route'] ?? '';

        if (!in_array($route, $allowed_banned_routes) && ($route !== $last_banned_route || (time() - $last_banned_log > 30))) {
            $_SESSION['last_banned_log'] = time();
            $_SESSION['last_banned_route'] = $route;

            // Log the banned try
            $banDetails = $clientLog->getBanDetails($clientId);
            $groupName = 'Unknown';
            if ($banDetails && $banDetails['group_id'] !== 'system') {
                require_once 'app/Models/Group.php';
                $groupModel = new Group();
                $group = $groupModel->getById($banDetails['group_id']);
                if ($group) {
                    $groupName = $group['name'];
                }
            } elseif ($banDetails && $banDetails['group_id'] === 'system') {
                $groupName = 'SuperAdmin';
            }

            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog('system');
            
            $params = $_REQUEST;
            // Capture JSON body if present
            $jsonInput = json_decode(file_get_contents('php://input'), true);
            if ($jsonInput) {
                $params = array_merge($params, $jsonInput);
            }

            if (!empty($params) && is_array($params)) {
                $sysLog->log('BANNED_TRY', [
                    'client_id' => $clientId,
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'params' => $params,
                    'group_name' => $groupName,
                    'banned_since' => $banDetails['banned_at'] ?? 'Unknown'
                ]);
            } else {
                 $sysLog->log('BANNED_TRY', [
                    'client_id' => $clientId,
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'group_name' => $groupName,
                    'banned_since' => $banDetails['banned_at'] ?? 'Unknown'
                ]);
            }
        }
        
        if (!empty($_SESSION)) {
            // Keep the error flag
            $_SESSION['banned_error'] = true;
            
            // Remove identification data to prevent controllers from redirecting
            unset($_SESSION['guest_id']);
            unset($_SESSION['group_id']);
            unset($_SESSION['is_superadmin']);
            unset($_SESSION['user']);
            
            session_write_close();
            
            // Only redirect if NOT already on an allowed banned route
            if (!in_array($route, $allowed_banned_routes)) {
                header('Location: ' . ($basePath ?: '') . '/');
                exit;
            }
        } elseif (!in_array($route, $allowed_banned_routes)) {
            header('Location: ' . ($basePath ?: '') . '/');
            exit;
        }
    }

    if (!empty($_SESSION)) {
        $track_groupId = $_SESSION['group_id'] ?? null;
        $track_type = null;
        $track_identity = $_SESSION['user'] ?? null;

        if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin']) {
            $track_groupId = 'system';
            $track_type = 'superadmin';
        } elseif ($track_groupId && isset($_SESSION['user'])) {
            $track_type = 'admin';
        } elseif (isset($_SESSION['guest_id'])) {
            // Priority to guest_id_group which is specifically set for connection logging
            $track_groupId = $_SESSION['guest_id_group'] ?? $_SESSION['group_id'] ?? null;
            $track_type = 'guest';
            if (isset($_SESSION['guest_name'])) {
                 $track_identity = $_SESSION['guest_name'];
            }
        }

        // Only update activity if we are not on the logout route or logging out
        $isLogoutRoute = (strpos($route, 'logout') !== false);
        if ($track_groupId && $track_type && $track_identity && 
            !$isLogoutRoute && 
            !isset($_SESSION['is_logging_out']) &&
            !isset($_GET['logging_out'])) {
            $clientLog->updateActivity($track_groupId, $track_type, $track_identity);
        }
    }
}

// Check for group session validity
if (isset($_SESSION['group_id']) && !isset($_SESSION['is_superadmin'])) {
    require_once 'app/Models/Group.php';
    $groupModel = new Group();
    $group = $groupModel->getById($_SESSION['group_id']);
    if (!$group || !$groupModel->isValid($group)) {
        if ($group) {
            $groupModel->resetPin($group['id']);
        }
        session_destroy();
        header('Location: ' . ($basePath ?: '') . '/login');
        exit;
    }
}

// The original $route logic using $_GET['r'] is now replaced by the above logic
// $route = $_GET['r'] ?? '/';
// Remove trailing slash if present, unless it's just "/"
// if ($route !== '/' && substr($route, -1) === '/') {
//     $route = rtrim($route, '/');
// }

// Simple router

switch ($route) {
    case '/':
    case '':
        $controller = new WelcomeController($basePath);
        $controller->index();
        break;

    case 'screen':
        $controller = new PlayerController();
        $controller->index($basePath);
        break;
    
    case 'superadmin':
        $controller = new SuperAdminController();
        $controller->index();
        break;

    case 'superadmin/contact':
        $controller = new SuperAdminController();
        $controller->contact();
        break;

    case 'superadmin/access_keys':
        $controller = new SuperAdminController();
        $controller->accessKeys();
        break;

    case 'superadmin/logs':
        $controller = new SuperAdminController();
        $controller->logs();
        break;

    case 'superadmin/clients':
        $controller = new SuperAdminController();
        $controller->clients();
        break;

    case 'superadmin/clear_clients':
        $controller = new SuperAdminController();
        $controller->clearClients();
        break;

    case 'superadmin/delete_client':
        $controller = new SuperAdminController();
        $controller->deleteClient();
        break;

    case 'superadmin/ban_client':
        $controller = new SuperAdminController();
        $controller->banClient();
        break;

    case 'superadmin/unban_client':
        $controller = new SuperAdminController();
        $controller->unbanClient();
        break;

    case 'api/superadmin/create_group':
        $controller = new SuperAdminController();
        $controller->createGroup();
        break;

    case 'api/superadmin/delete_group':
        $controller = new SuperAdminController();
        $controller->deleteGroup();
        break;

    case 'api/superadmin/update_group':
        $controller = new SuperAdminController();
        $controller->updateGroup();
        break;

    case 'api/superadmin/bell/count':
        $controller = new SuperAdminController();
        $controller->getBellCount();
        break;

    case 'api/superadmin/bell/increment':
        // Public endpoint — no auth required (called from the welcome page)
        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        $count = (int)$settings->get('bell_counter', 0);
        $count++;
        $settings->set('bell_counter', $count);
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    case 'api/superadmin/bell/reset':
        $controller = new SuperAdminController();
        $controller->resetBellCount();
        break;

    case 'api/superadmin/get_access_keys':
        $controller = new SuperAdminController(true); // Bypass superadmin check for this specific API
        $controller->getAccessKeys();
        break;

    case 'admin':
        $controller = new AdminController($basePath);
        $controller->index();
        break;

    case 'admin/codes':
        $controller = new AdminController($basePath);
        $controller->codes();
        break;

    case 'codes':
        header('Location: ' . ($basePath ?: '') . '/admin/codes');
        exit;
        break;

    case 'api/update_code':
        $controller = new AdminController($basePath);
        $controller->updateCode();
        break;

    case 'api/generate_distant_code':
        $controller = new AdminController($basePath);
        $controller->generateDistantCode();
        break;

    case 'api/generate_hotel_code':
        $controller = new AdminController($basePath);
        $controller->generateHotelCode();
        break;

    case 'api/toggle_session':
        $controller = new AdminController($basePath);
        $controller->toggleSession();
        break;

    case 'api/delete_guest':
        $controller = new AdminController($basePath);
        $controller->deleteGuest();
        break;

    case 'api/export_clean':
        $controller = new AdminController($basePath);
        $controller->exportAndCleanPlaylist();
        break;

    case 'admin/requests':
        $controller = new AdminController($basePath);
        $controller->requests();
        break;

    case 'admin/logs':
        $controller = new AdminController($basePath);
        $controller->logs();
        break;

    case 'logs':
        header('Location: ' . ($basePath ?: '') . '/admin/logs');
        exit;
        break;

    case 'admin/logs/download_tracks':
        $controller = new AdminController($basePath);
        $controller->downloadTracksList();
        break;

    case 'login':
        // If already logged in, redirect based on role
        if (isset($_SESSION['group_id'])) {
            header('Location: ' . ($basePath ?: '') . (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] ? '/superadmin' : '/admin'));
            exit;
        }
        $controller = new AuthController($basePath);
        $controller->index();
        break;

    case 'logout':
        $controller = new AuthController($basePath);
        $controller->logout();
        break;

    case 'auth':
        $controller = new AuthController($basePath);
        $controller->login();
        break;

    case 'api/get_playlist':
        $controller = new ApiController();
        $controller->getPlaylist();
        break;

    case 'api/add_video':
        $controller = new ApiController();
        $controller->addVideo();
        break;

    case 'api/get_status':
        $controller = new ApiController();
        $controller->getStatus();
        break;

    case 'api/send_command':
        $controller = new ApiController();
        $controller->sendCommand();
        break;

    case 'api/remove_video':
        $controller = new ApiController();
        $controller->removeVideo();
        break;

    case 'api/resolve_stream':
        $controller = new ApiController();
        $controller->resolveStream();
        break;
    case 'api/download_progress':
        $controller = new ApiController();
        $controller->getProgress();
        break;
    case 'api/update_status':
        $controller = new ApiController();
        $controller->updateStatus();
        break;

    case 'api/reorder_playlist':
        $controller = new ApiController();
        $controller->reorderPlaylist();
        break;

    case 'welcome':
        header('Location: ' . ($basePath ?: '/'));
        exit;
        break;

    case 'guest':
        $controller = new WelcomeController($basePath);
        $controller->dashboard();
        break;

    case 'installing':
        require_once 'app/Controllers/InstallController.php';
        $controller = new InstallController();
        $controller->index();
        break;

    case 'api/install':
        require_once 'app/Controllers/InstallController.php';
        $controller = new InstallController();
        $controller->install();
        break;

    case 'api/install_instructions':
        require_once 'app/Controllers/InstallController.php';
        $controller = new InstallController();
        $controller->getInstructions();
        break;

    case 'api/verify-code':
        $controller = new WelcomeController($basePath);
        $controller->verifyCode();
        break;

    case 'api/add-guest':
        $controller = new WelcomeController($basePath);
        $controller->addGuest();
        break;

    case 'api/guest_reorder':
        $controller = new WelcomeController($basePath);
        $controller->reorderSongs();
        break;

    case 'api/guest_add_song':
        $controller = new WelcomeController($basePath);
        $controller->guestAddSong();
        break;

    case 'api/guest_join':
        $controller = new WelcomeController($basePath);
        $controller->joinSinger();
        break;

    case 'api/guest_remove_song':
        $controller = new WelcomeController($basePath);
        $controller->removeGuestSong();
        break;

    case 'api/guest_dismiss_notification':
        $controller = new WelcomeController($basePath);
        $controller->dismissNotification();
        break;

    case 'api/guest_notifications':
        $controller = new WelcomeController($basePath);
        $controller->getNotifications();
        break;

    case 'api/guest_dashboard_data':
        $controller = new WelcomeController($basePath);
        $controller->getDashboardData();
        break;

    case 'api/get_requests':
        $controller = new ApiController();
        $controller->getRequests();
        break;

    case 'requests':
        header('Location: ' . ($basePath ?: '') . '/admin/requests');
        exit;
        break;

    case 'api/refuse_request':
        $controller = new ApiController();
        $controller->refuseRequest();
        break;

    case 'api/register_screen':
        $controller = new ApiController();
        $controller->registerScreen();
        break;

    case 'api/screen_pair_status':
        $controller = new ApiController();
        $controller->screenPairStatus();
        break;

    case 'api/pair_screen':
        $controller = new ApiController();
        $controller->pairScreen();
        break;

    case 'api/update_word_filter':
        $controller = new ApiController();
        $controller->updateWordFilter();
        break;

    case 'api/search_songs':
        $controller = new WelcomeController($basePath);
        $controller->searchSongs();
        break;

    default:
        http_response_code(404);
        echo '404 - Not Found';
        break;
}
