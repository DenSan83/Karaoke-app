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

        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        if (!$settings->get('allow_new_sessions', true)) {
            // Master toggle is OFF - End session for this guest
            session_destroy();
            // Clear persistent cookie as well
            setcookie('karaoke_guest_id', '', time() - 3600, '/');
            header('Location: ./?session_ended=1');
            exit;
        }

        $guest = $this->guestModel->getById($_SESSION['guest_id']);
        if (!$guest) {
            unset($_SESSION['guest_id']);
            header('Location: ./');
            exit;
        }

        // Calculate song statuses
        require_once 'app/Models/Playlist.php';
        require_once 'app/Models/PlayerStatus.php';
        $playlistModel = new Playlist();
        $statusModel = new PlayerStatus();
        
        $playlist = json_decode($playlistModel->getAll(), true) ?? [];
        $status = $statusModel->get();
        $currentIndex = $status['current_index'] ?? -1;

        $songStatusMap = [];

        // Build a map of videoId -> first future index
        $futureIndices = [];
        $currentVideoId = null;

        foreach ($playlist as $index => $video) {
            if ($index == $currentIndex) {
                $currentVideoId = $video['id'];
            }
            if ($index > $currentIndex) {
                if (!isset($futureIndices[$video['id']])) {
                    $futureIndices[$video['id']] = $index;
                }
            }
        }

        // Prepare helper for view
        $calculateStatus = function($videoId, $baseStatus) use ($currentIndex, $currentVideoId, $futureIndices) {
            if ($baseStatus !== 'Accepted') return $baseStatus; // Keep 'Waiting', 'Refused' etc.

            if ($videoId === $currentVideoId) {
                return "Singing now";
            }

            if (isset($futureIndices[$videoId])) {
                $dist = $futureIndices[$videoId] - $currentIndex;
                if ($dist === 1) {
                    return "Coming up";
                } elseif ($dist > 1) {
                    return "$dist songs left";
                }
            }

            // If Accepted but not current or future, assume Done
            return "Done";
        };

        require_once 'views/guest_dashboard.php';
    }

    public function verifyCode() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $code = trim($data['code'] ?? '');

        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        
        // GLOBAL SESSION STATUS CHECK
        $allowNew = $settings->get('allow_new_sessions', true);
        if (!$allowNew) {
            echo json_encode(['success' => false, 'error' => 'Sessions are currently closed by the administrator.']);
            exit;
        }

        // Check for new system configuration
        $guestCodes = $settings->get('guest_codes'); // Returns null if not set (no default arg)

        $isValid = false;
        $distantName = null;
        $autoLogin = false;
        $isHotelJoin = false;
        $isDistantInvite = false;

        if ($guestCodes !== null && is_array($guestCodes)) {
            // New system is active - strictly check against the list (case sensitive)
            if (in_array($code, $guestCodes)) {
                $isValid = true;
                $_SESSION['used_code'] = $code;
                $_SESSION['user_category'] = 'in person';
            }
        }

        // Check for dedicated Hotel code
        if (!$isValid) {
            $hotelCode = $settings->get('hotel_code');
            if ($hotelCode && $code === $hotelCode) {
                $isValid = true;
                $isHotelJoin = true;
                $_SESSION['used_code'] = $code;
                $_SESSION['user_category'] = 'hotel';
            }
        }

        // Check for "Distant" codes if not already valid
        if (!$isValid) {
            require_once 'app/Services/EncryptionService.php';
            $decrypted = EncryptionService::decrypt($code);
            
            if ($decrypted && isset($decrypted['type']) && $decrypted['type'] === 'distant') {
                $isValid = true;
                $isDistantInvite = true;
                $distantName = $decrypted['name'];
                $_SESSION['used_code'] = $code;
                $_SESSION['user_category'] = 'distant';
                
                // LOG THIS ACCESS
                $logFile = 'distant_access.log';
                $logEntry = date('Y-m-d H:i:s') . " - One-click access by: " . $decrypted['name'] . " (" . $decrypted['email'] . ") using code: " . substr($code, 0, 10) . "...\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);

                // AUTO-REGISTER AND LOGIN
                $result = $this->guestModel->add($decrypted['name']);
                if (isset($result['success']) && $result['success']) {
                    $_SESSION['guest_id'] = $result['guest']['id'];
                    $_SESSION['guest_name'] = $result['guest']['name'];
                    setcookie('karaoke_guest_id', $result['guest']['id'], time() + 14400, '/', '', false, true);
                    $autoLogin = true;

                    // LOG LOGIN
                    require_once 'app/Models/SystemLog.php';
                    $sysLog = new SystemLog();
                    $sysLog->log('user_login', [
                        'guestId' => $result['guest']['id'],
                        'name' => $result['guest']['name'],
                        'code' => $code,
                        'category' => 'distant'
                    ]);
                }
            }
        }

        if ($isValid) {
            $response = ['success' => true];
            if (isset($autoLogin) && $autoLogin) {
                $response['autoLogin'] = true;
            }
            if (isset($distantName)) {
                $response['distantName'] = $distantName;
            }
            
            // Check for persistent guest cookie
            $guestId = $_COOKIE['karaoke_guest_id'] ?? null;
            if ($guestId && !$isHotelJoin && !$isDistantInvite) {
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
        
        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        if (!$settings->get('allow_new_sessions', true)) {
            echo json_encode(['success' => false, 'error' => 'Registration is currently closed.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';

        $result = $this->guestModel->add($name);
        if (isset($result['success']) && $result['success']) {
            $_SESSION['guest_id'] = $result['guest']['id'];
            $_SESSION['guest_name'] = $result['guest']['name'];
            
            // Set persistent identity cookie for 4 hours
            setcookie('karaoke_guest_id', $result['guest']['id'], time() + 14400, '/', '', false, true);

            // LOG LOGIN
            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog();
            $sysLog->log('user_login', [
                'guestId' => $result['guest']['id'],
                'name' => $name,
                'code' => $_SESSION['used_code'] ?? 'Unknown',
                'category' => $_SESSION['user_category'] ?? 'in person'
            ]);
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

        // Check if this is a playlist
        if ($ytService->isPlaylistUrl($url)) {
            $this->guestAddPlaylist($url, $ytService);
            return;
        }

        // Single video handling
        $videoId = $ytService->extractVideoId($url);
        if (!$videoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid YouTube URL']);
            return;
        }

        // Check for duplicates before adding
        $originalSinger = $this->guestModel->findDuplicateRequest($videoId, $_SESSION['guest_id']);
        
        $playlistDuplicate = null;
        if (!$originalSinger) {
            require_once 'app/Models/Playlist.php';
            $playlistModel = new Playlist();
            $playlistJson = $playlistModel->getAll();
            $playlist = json_decode($playlistJson, true) ?? [];
            
            error_log("Checking playlist for duplicate videoId: $videoId");
            error_log("Playlist count: " . count($playlist));

            foreach ($playlist as $video) {
                if ($video['id'] === $videoId) {
                    error_log("Found in playlist. User: " . ($video['user'] ?? 'null'));
                    // Check if it's not me
                    $currentGuestName = $_SESSION['guest_name'] ?? '';
                    if (isset($video['user']) && $video['user'] !== $currentGuestName) {
                        $playlistDuplicate = $video;
                        error_log("Marked as playlist duplicate against $currentGuestName");
                    } else {
                        error_log("Self-duplicate ignored or user missing");
                    }
                    break;
                }
            }
        } else {
            error_log("Found in active guests list");
        }
        
        $metadata = $ytService->getMetadata($videoId);
        $title = $metadata['title'] ?? 'Unknown Title';
        
        // CHECK FOR "KARAOKE" KEYWORD
        $isForced = (bool)($data['force'] ?? false);
        if (!$isForced && stripos($title, 'karaoke') === false) {
            echo json_encode([
                'success' => false, 
                'needsConfirmation' => true,
                'title' => $title,
                'videoId' => $videoId
            ]);
            return;
        }
        
        $songData = [
            'id' => $videoId,
            'url' => $url,
            'title' => $title,
            'added_at' => time()
        ];

        $result = $this->guestModel->addSong($_SESSION['guest_id'], $songData);
        
        if ($result['success']) {
            $result['videoId'] = $videoId;
            if ($originalSinger) {
                $result['duplicateFound'] = true;
                $result['originalSingerName'] = $originalSinger['name'];
                $result['originalSingerId'] = $originalSinger['id'];
            } elseif ($playlistDuplicate) {
                $result['duplicateFound'] = true;
                $result['originalSingerName'] = $playlistDuplicate['user'];
                
                // Try to find the guest ID for this user to allow joining
                $allGuests = $this->guestModel->getAll();
                $guestId = 'unknown';
                if (isset($allGuests['guests'])) {
                    foreach ($allGuests['guests'] as $g) {
                        if ($g['name'] === $playlistDuplicate['user']) {
                            $guestId = $g['id'];
                            break;
                        }
                    }
                }
                $result['originalSingerId'] = $guestId;
            } else {
                $result['duplicateFound'] = false;
            }
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function guestAddPlaylist($url, $ytService) {
        $playlistId = $ytService->extractPlaylistId($url);
        if (!$playlistId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid playlist URL']);
            return;
        }

        $result = $ytService->getPlaylistVideos($playlistId, 20);
        
        if (isset($result['error'])) {
            http_response_code(400);
            echo json_encode(['error' => $result['error']]);
            return;
        }

        $videos = $result['videos'];
        $total = $result['total'];
        $addedSongs = [];

        // Add each video from playlist
        foreach ($videos as $videoData) {
            $originalSinger = $this->guestModel->findDuplicateRequest($videoData['id'], $_SESSION['guest_id']);
            
            $songData = [
                'id' => $videoData['id'],
                'url' => "https://www.youtube.com/watch?v={$videoData['id']}",
                'title' => $videoData['title'],
                'added_at' => time()
            ];

            $songResult = $this->guestModel->addSong($_SESSION['guest_id'], $songData);
            if (isset($songResult['success'])) {
                $addedSongs[] = [
                    'videoId' => $videoData['id'],
                    'title' => $videoData['title'],
                    'duplicateFound' => $originalSinger ? true : false,
                    'originalSingerName' => $originalSinger ? $originalSinger['name'] : null
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'is_playlist' => true,
            'songs' => $addedSongs,
            'total_in_playlist' => $total,
            'added_count' => count($addedSongs)
        ], JSON_UNESCAPED_UNICODE);
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

    public function searchSongs() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $q = $_GET['q'] ?? '';
        if (strlen($q) < 3) {
            echo json_encode([]);
            return;
        }

        $q = mb_strtolower($q);
        $results = [];
        $seenIds = [];

        $results = [];
        $seenIds = [];

        // 1. Get current playlist status to filter out played/playing songs
        require_once 'app/Models/Playlist.php';
        require_once 'app/Models/PlayerStatus.php';
        $playlistModel = new Playlist();
        $statusModel = new PlayerStatus();
        
        $playlistJson = $playlistModel->getAll(); // returns JSON string
        $playlist = json_decode($playlistJson, true) ?? [];
        $status = $statusModel->get();
        $currentIndex = $status['current_index'] ?? -1;

        $forbiddenVideoIds = [];
        $playlistFutureMap = [];

        foreach ($playlist as $index => $video) {
            if ($index <= $currentIndex) {
                $forbiddenVideoIds[] = $video['id'];
            } else {
                // Keep track of future songs to identify them as "playlist" source even if found in guest history
                $playlistFutureMap[$video['id']] = true;
            }
        }

        // 2. Search in other guests' lists (Prioritize social / active requests)
        $allGuests = $this->guestModel->getAll();
        if (isset($allGuests['guests'])) {
            foreach ($allGuests['guests'] as $g) {
                // Skip current guest's own songs from "social" match
                if ($g['id'] === $_SESSION['guest_id']) continue;

                if (isset($g['songs'])) {
                    foreach ($g['songs'] as $song) {
                        if (in_array($song['id'], $forbiddenVideoIds)) continue;

                        if (isset($song['title']) && strpos(mb_strtolower($song['title']), $q) !== false) {
                            if (!in_array($song['id'], $seenIds)) {
                                $results[] = [
                                    'id' => $song['id'],
                                    'title' => $song['title'],
                                    // If it's in future playlist, mark as playlist (or guest_history, but user said not to show distinct history if available in playlist? 
                                    // Actually, duplicate logic handles "if in playlist, show as playlist".
                                    // But here we are searching.
                                    // If I search "Hello", and it's requested by Bob (future).
                                    // Should it show "History" or "Playlist"?
                                    // Probably "Playlist" is more "official".
                                    // Let's check if it is in future playlist.
                                    'source' => isset($playlistFutureMap[$song['id']]) ? 'playlist' : 'guest_history'
                                ];
                                $seenIds[] = $song['id'];
                            }
                        }
                    }
                }
            }
        }

        // 3. Search in global playlist (Future only)
        foreach ($playlist as $index => $video) {
            if ($index <= $currentIndex) continue;

            if (isset($video['title']) && strpos(mb_strtolower($video['title']), $q) !== false) {
                if (!in_array($video['id'], $seenIds)) {
                    $results[] = [
                        'id' => $video['id'],
                        'title' => $video['title'],
                        'source' => 'playlist'
                    ];
                    $seenIds[] = $video['id'];
                }
            }
        }

        // Limit results
        echo json_encode(array_slice($results, 0, 10), JSON_UNESCAPED_UNICODE);
    }
}
