<?php
require_once 'app/Models/Guest.php';

class WelcomeController {
    private $guestModel;
    private $groupId;
    private $basePath;

    public function __construct($basePath = '') {
        $this->basePath = $basePath;
        $this->groupId = $_SESSION['group_id'] ?? null;
        $this->guestModel = new Guest($this->groupId);
    }

    public function index() {
        if (isset($_SESSION['guest_id'])) {
            header('Location: ' . ($this->basePath ?: '') . '/guest');
            exit;
        }

        require_once 'app/Models/Settings.php';
        $settings = new Settings('system');
        $contactEmail = $settings->get('contact_email', 'contact@devdensan.com');

        $data = [
            'basePath' => $this->basePath,
            'contactEmail' => $contactEmail
        ];
        extract($data);
        require_once 'views/welcome.php';
    }

    public function dashboard() {
        if (!isset($_SESSION['guest_id'])) {
            header('Location: ' . ($this->basePath ?: '/'));
            exit;
        }

        $guest = $this->guestModel->getById($_SESSION['guest_id']);
        if (!$guest) {
            unset($_SESSION['guest_id']);
            header('Location: ' . ($this->basePath ?: '/'));
            exit;
        }

        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        if (!$settings->get('allow_new_sessions', true)) {
            // Master toggle is OFF - End session for this guest
            session_destroy();
            // Clear persistent cookie as well
            setcookie('karaoke_guest_id', '', time() - 3600, '/');
            header('Location: ' . ($this->basePath ?: '') . '/?session_ended=1');
            exit;
        }

        // Get party name
        require_once 'app/Models/Group.php';
        $groupModel = new Group();
        $partyName = 'Karaoke Party';
        if (isset($_SESSION['group_id'])) {
            $group = $groupModel->getById($_SESSION['group_id']);
            if ($group) {
                $partyName = $group['name'];
            }

            // Log connection for guest if not already logged in this session
            if (!isset($_SESSION['guest_logged_' . $_SESSION['group_id']])) {
                require_once 'app/Models/ClientLog.php';
                $clientLog = new ClientLog();
                $identity = $guest['name'] ?? 'guest';
                $clientLog->logConnection($_SESSION['group_id'], 'guest', $identity);
                $_SESSION['guest_logged_' . $_SESSION['group_id']] = true;
            }
        }

        // Calculate song statuses
        require_once 'app/Models/Playlist.php';
        require_once 'app/Models/PlayerStatus.php';
        $playlistModel = new Playlist($this->groupId);
        $statusModel = new PlayerStatus($this->groupId);
        
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

        $data = [
            'guest' => $guest,
            'partyName' => $partyName,
            'calculateStatus' => $calculateStatus,
            'basePath' => $this->basePath
        ];
        extract($data);
        require_once 'views/guest.php';
    }

    public function verifyCode() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $code = strtoupper(trim($data['code'] ?? ''));
        $groupId = $data['group_id'] ?? null;

        // Check if banned
        require_once 'app/Models/ClientLog.php';
        $clientLog = new ClientLog();
        $clientId = $_COOKIE['karaoke_client_id'] ?? null;
        if ($clientId && $clientLog->isBanned($clientId)) {
            echo json_encode(['success' => false, 'error' => 'Invalid code or party is no longer active.']);
            return;
        }

        require_once 'app/Models/Group.php';
        require_once 'app/Models/Settings.php';
        $groupModel = new Group();
        
        $targetGroup = null;

        // 1. If groupId is provided in URL/request, verify it exists and is valid
        if ($groupId) {
            $group = $groupModel->getById($groupId);
            if ($group && $groupModel->isValid($group)) {
                $targetGroup = $group;
            }
        }

        // 2. If no groupId or invalid, search all active groups for this code
        if (!$targetGroup) {
            $allGroups = $groupModel->getAll();
            foreach ($allGroups as $group) {
                if ($groupModel->isValid($group)) {
                    // Check direct access_code from groups table
                    if (!empty($group['access_code']) && strtoupper($group['access_code']) === $code) {
                        $targetGroup = $group;
                        break;
                    }
                    
                    $settings = new Settings($group['id']);
                    $guestCodes = $settings->get('guest_codes', []);
                    // Convert guest codes to upper for comparison
                    $upperGuestCodes = array_map('strtoupper', $guestCodes);
                    $hotelCode = $settings->get('hotel_code');
                    if (in_array($code, $upperGuestCodes) || ($hotelCode && $code === strtoupper($hotelCode))) {
                        $targetGroup = $group;
                        break;
                    }
                }
            }
        }

        // 3. If still no group, check for distant access (encrypted codes)
        if (!$targetGroup && strlen($code) > 20) {
            require_once 'app/Services/EncryptionService.php';
            $decrypted = EncryptionService::decrypt($code);
            if ($decrypted && isset($decrypted['group_id'])) {
                $group = $groupModel->getById($decrypted['group_id']);
                if ($group && $groupModel->isValid($group)) {
                    $targetGroup = $group;
                }
            }
        }

        if (!$targetGroup) {
            echo json_encode(['success' => false, 'error' => 'Invalid code or party is no longer active.']);
            return;
        }

        // Update session group_id and re-instantiate guestModel
        $_SESSION['group_id'] = $targetGroup['id'];
        $this->groupId = $targetGroup['id'];
        $this->guestModel = new Guest($this->groupId);

        $settings = new Settings($this->groupId);
        
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
        
        // Check group's main access_code
        if (!empty($targetGroup['access_code']) && strtoupper($targetGroup['access_code']) === $code) {
            $isValid = true;
            $_SESSION['used_code'] = $code;
        }

        if (!$isValid && $guestCodes !== null && is_array($guestCodes)) {
            // New system is active - strictly check against the list (case insensitive via uppercase)
            $upperGuestCodes = array_map('strtoupper', $guestCodes);
            if (in_array($code, $upperGuestCodes)) {
                $isValid = true;
                $_SESSION['used_code'] = $code;
            }
        }

        // Check for dedicated Hotel code
        if (!$isValid) {
            $hotelCode = $settings->get('hotel_code');
            if ($hotelCode && $code === strtoupper($hotelCode)) {
                $isValid = true;
                $isHotelJoin = true;
                $_SESSION['used_code'] = $code;
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
        
        // Check if banned
        require_once 'app/Models/ClientLog.php';
        $clientLog = new ClientLog();
        $clientId = $_COOKIE['karaoke_client_id'] ?? null;
        if ($clientId && $clientLog->isBanned($clientId)) {
            echo json_encode(['success' => false, 'error' => 'You are not allowed to join this party.']);
            exit;
        }

        require_once 'app/Models/Settings.php';
        $settings = new Settings();
        if (!$settings->get('allow_new_sessions', true)) {
            echo json_encode(['success' => false, 'error' => 'Registration is currently closed.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        $fingerprint = $data['fingerprint'] ?? null;

        if ($fingerprint) {
            $_SESSION['guest_fingerprint'] = $fingerprint;
        }

        $result = $this->guestModel->add($name);
        if (isset($result['success']) && $result['success']) {
            $_SESSION['guest_id'] = $result['guest']['id'];
            $_SESSION['guest_name'] = $result['guest']['name'];
            $_SESSION['guest_id_group'] = $this->groupId;
            
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
        $friendName = $data['friendName'] ?? null;

        if (empty($url)) {
            http_response_code(400);
            echo json_encode(['error' => 'URL is required']);
            return;
        }

        require_once 'app/Services/YouTubeService.php';
        $ytService = new YouTubeService();

        // Check if this is a playlist
        if ($ytService->isPlaylistUrl($url)) {
            $this->guestAddPlaylist($url, $ytService, $friendName);
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
            $playlistModel = new Playlist($this->groupId);
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

        // TEST ACCESSIBILITY (Embed or Download)
        if (!$ytService->isEmbeddable($videoId)) {
            $fallbackAllowed = false;
            if ($this->groupId) {
                require_once 'app/Models/Group.php';
                $groupModel = new Group();
                $group = $groupModel->getById($this->groupId);
                $fallbackAllowed = !empty($group['allow_fallback']);
            }

            if (!$fallbackAllowed || !$ytService->isDownloadable($videoId)) {
                http_response_code(422);
                $errorMsg = $fallbackAllowed
                    ? "This video cannot be played. YouTube restricts embedding and it cannot be downloaded."
                    : "YouTube restricts embedding of this video. Please find a different version and try again.";
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                return;
            }
        }
        
        // CHECK FOR WORD FILTERS
        $isForced = (bool)($data['force'] ?? false);
        if (!$isForced && $this->groupId) {
            require_once 'app/Models/Group.php';
            $groupModel = new Group();
            $group = $groupModel->getById($this->groupId);

            if ($group) {
                $mustHaveRaw = $group['must_have_words'] ?? '';
                $mustNotHaveRaw = $group['must_not_have_words'] ?? '';

                $needsConfirmation = false;
                $failedWord = "";
                $filterType = ""; // 'must_have' or 'must_not_have'

                // Check Must Have Words
                if (!empty(trim($mustHaveRaw))) {
                    $mustHaveWords = array_map('trim', explode(',', $mustHaveRaw));
                    $found = false;
                    foreach ($mustHaveWords as $word) {
                        if (!empty($word) && stripos($title, $word) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $needsConfirmation = true;
                        $filterType = 'must_have';
                        $failedWord = str_replace(',', ', ', $mustHaveRaw); // For 'must_have', show all required words nicely formatted
                    }
                }

                // Check Must Not Have Words
                if (!$needsConfirmation && !empty(trim($mustNotHaveRaw))) {
                    $mustNotHaveWords = array_map('trim', explode(',', $mustNotHaveRaw));
                    foreach ($mustNotHaveWords as $word) {
                        if (!empty($word) && stripos($title, $word) !== false) {
                            $needsConfirmation = true;
                            $filterType = 'must_not_have';
                            $failedWord = $word;
                            break;
                        }
                    }
                }

                if ($needsConfirmation) {
                    echo json_encode([
                        'success' => false, 
                        'needsConfirmation' => true,
                        'filterType' => $filterType,
                        'failedWord' => $failedWord,
                        'title' => $title,
                        'videoId' => $videoId
                    ]);
                    return;
                }
            }
        }
        
        $songData = [
            'id' => $videoId,
            'url' => $url,
            'title' => $title,
            'can_embed' => $ytService->isEmbeddable($videoId),
            'added_at' => time()
        ];

        if ($friendName) {
            $currentGuestName = $_SESSION['guest_name'] ?? 'Guest';
            $songData['requested_by'] = "$friendName (added by $currentGuestName)";
        }

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

    private function guestAddPlaylist($url, $ytService, $friendName = null) {
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

            if ($friendName) {
                $currentGuestName = $_SESSION['guest_name'] ?? 'Guest';
                $songData['requested_by'] = "$friendName (added by $currentGuestName)";
            }

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

        // Remove debug logs
        $success = $this->guestModel->removeSong($_SESSION['guest_id'], $videoId);

        // Also remove from admin queue if it was already accepted
        if ($success) {
            require_once 'app/Models/Playlist.php';
            $playlistModel = new Playlist($this->groupId);
            $user = $_SESSION['guest_name'] ?? '';
            $playlistModel->removeByVideoIdAndUser($videoId, $user);

            // Log the removal
            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog($this->groupId);
            $sysLog->log('track_removed_by_user', [
                'guestId' => $_SESSION['guest_id'],
                'guestName' => $user,
                'videoId' => $videoId
            ]);
        }

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

    public function getDashboardData() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['guest_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $guest = $this->guestModel->getById($_SESSION['guest_id']);
        if (!$guest) {
            http_response_code(404);
            echo json_encode(['error' => 'Guest not found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Calculate statuses (logic synced with index/dashboard)
        require_once 'app/Models/Playlist.php';
        require_once 'app/Models/PlayerStatus.php';
        $playlistModel = new Playlist($this->groupId);
        $statusModel = new PlayerStatus($this->groupId);
        
        $playlist = json_decode($playlistModel->getAll(), true) ?? [];
        $status = $statusModel->get();
        $currentIndex = $status['current_index'] ?? -1;

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

        foreach ($guest['songs'] as &$song) {
            $baseStatus = $song['status'] ?? 'Waiting';
            $displayStatus = $baseStatus;

            if ($baseStatus === 'Accepted') {
                if ($song['id'] === $currentVideoId) {
                    $displayStatus = "Singing now";
                } elseif (isset($futureIndices[$song['id']])) {
                    $dist = $futureIndices[$song['id']] - $currentIndex;
                    if ($dist === 1) {
                        $displayStatus = "Coming up";
                    } else {
                        $displayStatus = "$dist songs left";
                    }
                } else {
                    $displayStatus = "Done";
                }
            } else {
                // If not Accepted, it might be Waiting or Refused. 
                // We keep it as is, BUT we should make sure 'Waiting' is capitalized consistently if needed.
                $displayStatus = $baseStatus;
            }
            $song['display_status'] = $displayStatus;
        }

        echo json_encode([
            'songs' => $guest['songs'],
            'notifications' => $guest['notifications'] ?? []
        ], JSON_UNESCAPED_UNICODE);
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
        $playlistModel = new Playlist($this->groupId);
        $statusModel = new PlayerStatus($this->groupId);
        
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
