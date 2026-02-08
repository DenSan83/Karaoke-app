<?php
// Set session cookie and garbage collector lifetime to 4 hours (14400 seconds)
ini_set('session.gc_maxlifetime', 14400);
session_set_cookie_params(14400);
session_start();

// Check system requirements (skip for installing page)
require_once 'app/Services/SystemCheck.php';

// Get the requested route
$request = $_SERVER['REQUEST_URI'];
$basePath = '/git_projects/08.karaoke_admin'; // This should be dynamically determined or configured
$route = str_replace($basePath, '', parse_url($request, PHP_URL_PATH));
$route = trim($route, '/');

// Check if yt-dlp is available (skip check for installing routes and API)
if (!SystemCheck::checkYtDlp() &&
    $route !== 'installing' &&
    strpos($route, 'api/install') !== 0) {
    header('Location: ' . $basePath . '/installing');
    exit;
}

require_once 'app/Controllers/PlayerController.php';
require_once 'app/Controllers/AdminController.php';
require_once 'app/Controllers/ApiController.php';
require_once 'app/Controllers/AuthController.php';

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
        $controller = new WelcomeController();
        $controller->index();
        break;

    case 'screen':
        $controller = new PlayerController();
        $controller->index();
        break;
    
    case 'admin':
        $controller = new AdminController();
        $controller->index();
        break;

    case 'admin/requests':
        $controller = new AdminController();
        $controller->requests();
        break;

    case 'login':
        $controller = new AuthController();
        $controller->index();
        break;

    case 'logout':
        $controller = new AuthController();
        $controller->logout();
        break;

    case 'auth':
        $controller = new AuthController();
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
        header('Location: ./');
        exit;
        break;

    case 'guest-dashboard':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
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
        $controller = new WelcomeController();
        $controller->verifyCode();
        break;

    case 'api/add-guest':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
        $controller->addGuest();
        break;

    case 'api/guest_reorder':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
        $controller->reorderSongs();
        break;

    case 'api/guest_add_song':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
        $controller->guestAddSong();
        break;

    case 'api/guest_join':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
        $controller->joinSinger();
        break;

    case 'api/guest_remove_song':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
        $controller->removeGuestSong();
        break;

    case 'api/guest_dismiss_notification':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
        $controller->dismissNotification();
        break;

    case 'api/guest_notifications':
        require_once 'app/Controllers/WelcomeController.php';
        $controller = new WelcomeController();
        $controller->getNotifications();
        break;

    case 'api/get_requests':
        $controller = new ApiController();
        $controller->getRequests();
        break;

    case 'api/refuse_request':
        $controller = new ApiController();
        $controller->refuseRequest();
        break;

    default:
        http_response_code(404);
        echo '404 - Not Found';
        break;
}
