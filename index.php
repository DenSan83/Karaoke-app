<?php
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
            die("La migration a été tentée mais la base de données n'est toujours pas prête. Veuillez vérifier votre configuration .env et vos logs d'erreur.");
        }
        try {
            $GLOBALS['RUN_MIGRATION'] = true;
            require_once 'migrate_json_to_mysql.php';
            // After migration, we should ideally refresh or continue carefully.
            // To be safe and avoid any issues with loaded classes, we can redirect to the same page.
            $separator = (strpos($_SERVER['REQUEST_URI'], '?') === false) ? '?' : '&';
            header('Location: ' . $_SERVER['REQUEST_URI'] . $separator . 'migration_attempted=1');
            exit;
        } catch (Exception $e) {
            // Fallback or log error
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
        require_once 'app/Controllers/WelcomeController.php';
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

    case 'superadmin/logs':
        $controller = new SuperAdminController();
        $controller->logs();
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
        require_once 'app/Controllers/WelcomeController.php';
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
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->verifyCode();
        break;

    case 'api/add-guest':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->addGuest();
        break;

    case 'api/guest_reorder':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->reorderSongs();
        break;

    case 'api/guest_add_song':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->guestAddSong();
        break;

    case 'api/guest_join':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->joinSinger();
        break;

    case 'api/guest_remove_song':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->removeGuestSong();
        break;

    case 'api/guest_dismiss_notification':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->dismissNotification();
        break;

    case 'api/guest_notifications':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->getNotifications();
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

    case 'api/search_songs':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController($basePath);
        $controller->searchSongs();
        break;

    default:
        http_response_code(404);
        echo '404 - Not Found';
        break;
}
