<?php
require_once 'app/Models/Guest.php';

class WelcomeController {
    private $guestModel;

    public function __construct() {
        $this->guestModel = new Guest();
    }

    public function index() {
        if (isset($_SESSION['guest_id'])) {
            header('Location: guest-dashboard');
            exit;
        }
        require_once 'views/welcome.php';
    }

    public function dashboard() {
        if (!isset($_SESSION['guest_id'])) {
            header('Location: ./');
            exit;
        }
        $guest = $this->guestModel->getById($_SESSION['guest_id']);
        if (!$guest) {
            unset($_SESSION['guest_id']);
            header('Location: ./');
            exit;
        }
        require_once 'views/guest_dashboard.php';
    }

    public function verifyCode() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $code = $data['code'] ?? '';

        if ($code === 'TODAY26') {
            $response = ['success' => true];
            
            // Check for persistent guest cookie
            $guestId = $_COOKIE['karaoke_guest_id'] ?? null;
            if ($guestId) {
                $guest = $this->guestModel->getById($guestId);
                if ($guest) {
                    // Auto-login if they have a valid cookie
                    $_SESSION['guest_id'] = $guest['id'];
                    $_SESSION['guest_name'] = $guest['name'];
                    $response['rejoining'] = true;
                    $response['guestName'] = $guest['name'];
                    
                    // Refresh identity cookie for another 4 hours
                    setcookie('karaoke_guest_id', $guest['id'], time() + 14400, '/', '', false, true);
                }
            }
            
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid code']);
        }
    }

    public function addGuest() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';

        $result = $this->guestModel->add($name);
        if (isset($result['success']) && $result['success']) {
            $_SESSION['guest_id'] = $result['guest']['id'];
            $_SESSION['guest_name'] = $result['guest']['name'];
            
            // Set persistent identity cookie for 4 hours
            setcookie('karaoke_guest_id', $result['guest']['id'], time() + 14400, '/', '', false, true);
        }
        echo json_encode($result);
    }

    public function guestAddSong() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $url = $data['song']['url'] ?? '';

        if (empty($url)) {
            http_response_code(400);
            echo json_encode(['error' => 'URL is required']);
            return;
        }

        require_once 'app/Services/YouTubeService.php';
        $ytService = new YouTubeService();

        $videoId = $ytService->extractVideoId($url);
        if (!$videoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid YouTube URL']);
            return;
        }

        // Check for duplicates before adding
        $originalSinger = $this->guestModel->findDuplicateRequest($videoId, $_SESSION['guest_id']);
        
        $metadata = $ytService->getMetadata($videoId);
        
        $songData = [
            'id' => $videoId,
            'url' => $url,
            'title' => $metadata['title'],
            'added_at' => time()
        ];

        $result = $this->guestModel->addSong($_SESSION['guest_id'], $songData);
        
        if ($result['success']) {
            $result['videoId'] = $videoId;
            if ($originalSinger) {
                $result['duplicateFound'] = true;
                $result['originalSingerName'] = $originalSinger['name'];
                $result['originalSingerId'] = $originalSinger['id'];
            } else {
                $result['duplicateFound'] = false;
            }
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function joinSinger() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $targetGuestId = $data['targetGuestId'] ?? '';
        $videoId = $data['videoId'] ?? '';

        if (empty($targetGuestId) || empty($videoId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing target or video info']);
            return;
        }

        // Send notification to the original singer
        $targetGuest = $this->guestModel->getById($targetGuestId);
        $songTitle = 'a song';
        if ($targetGuest && isset($targetGuest['songs'])) {
            foreach ($targetGuest['songs'] as $song) {
                if ($song['id'] === $videoId) {
                    $songTitle = $song['title'];
                    break;
                }
            }
        }

        $currentGuest = $this->guestModel->getById($_SESSION['guest_id']);
        $notification = [
            'type' => 'join_sing',
            'from_name' => $currentGuest['name'] ?? 'Someone',
            'videoId' => $videoId,
            'songTitle' => $songTitle,
            'message' => ($currentGuest['name'] ?? 'Someone') . " would like to sing \"{$songTitle}\" with you!"
        ];

        $success = $this->guestModel->addNotification($targetGuestId, $notification);
        echo json_encode(['success' => (bool)$success], JSON_UNESCAPED_UNICODE);
    }

    public function removeGuestSong() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $videoId = $data['videoId'] ?? '';

        if (empty($videoId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Video ID required']);
            return;
        }

        $success = $this->guestModel->removeSong($_SESSION['guest_id'], $videoId);
        echo json_encode(['success' => (bool)$success], JSON_UNESCAPED_UNICODE);
    }

    public function reorderSongs() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $newSongs = $data['songs'] ?? null;

        if ($newSongs === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Songs list required']);
            return;
        }

        $result = $this->guestModel->reorderSongs($_SESSION['guest_id'], $newSongs);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function dismissNotification() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $notifId = $data['notificationId'] ?? '';

        if (empty($notifId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $success = $this->guestModel->dismissNotification($_SESSION['guest_id'], $notifId);
        echo json_encode(['success' => (bool)$success], JSON_UNESCAPED_UNICODE);
    }

    public function getNotifications() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $guest = $this->guestModel->getById($_SESSION['guest_id']);
        echo json_encode($guest['notifications'] ?? [], JSON_UNESCAPED_UNICODE);
    }
}
