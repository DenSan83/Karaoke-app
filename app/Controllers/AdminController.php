<?php

class AdminController {
    private $groupId;
    private $sysLog;
    private $guestModel;
    private $playlistModel;

    public function __construct() {
        if (!isset($_SESSION['user'])) {
            header('Location: login');
            exit;
        }
        $this->groupId = $_SESSION['group_id'] ?? null;
    }

    public function index() {
        require_once 'views/admin.php';
    }

    public function requests() {
        require_once 'views/admin_requests.php';
    }

    public function logs() {
        require_once 'app/Models/SystemLog.php';
        require_once 'app/Models/Guest.php';
        $this->sysLog = new SystemLog($this->groupId);
        $this->guestModel = new Guest($this->groupId);
        
        $allGuestsData = $this->guestModel->getAll();
        $allGuests = $allGuestsData['guests'] ?? [];
        $userLogs = $this->sysLog->getLogs('user_login');
        $allLogs = $this->sysLog->getLogs(); // Get everything for update
        $updated = false;

        // 1. Resolve missing guestIds in existing logs
        // We iterate through all logs to ensure we update the source
        foreach ($allLogs as &$log) {
            if ($log['type'] === 'user_login' && !isset($log['data']['guestId'])) {
                $name = $log['data']['name'] ?? '';
                foreach ($allGuests as $g) {
                    if ($g['name'] === $name) {
                        $log['data']['guestId'] = $g['id'];
                        $updated = true;
                        break;
                    }
                }
            }
        }

        if ($updated) {
            $this->sysLog->updateLogs($allLogs);
            // Refresh local userLogs after persistence
            $userLogs = $this->sysLog->getLogs('user_login');
        }

        // 2. Ensure all current guests have a login log entry
        $loggedGuestIds = array_filter(array_map(function($l) { 
            return $l['data']['guestId'] ?? null; 
        }, $userLogs));

        foreach ($allGuests as $g) {
            if (!in_array($g['id'], $loggedGuestIds)) {
                $entryData = [
                    'guestId' => $g['id'],
                    'name' => $g['name'],
                    'code' => 'Active', // Changed from "History" to "Active"
                    'category' => 'in person',
                    'timestamp' => $g['created_at'] ?? time()
                ];
                $this->sysLog->log('user_login', $entryData);
                $updated = true;
            }
        }

        // 3. Aggregate Tracks from Queue (playlist.json) and Guests (guests.json)
        $trackLogs = [];
        $addedVideoUserPairs = []; // To avoid duplicates if same song/user in both

        // A. From Playlist (Active/Accepted songs)
        require_once 'app/Models/Playlist.php';
        $this->playlistModel = new Playlist($this->groupId);
        $playlistData = json_decode($this->playlistModel->getAll(), true) ?: [];
        
        // Helper to find category by name
        $findCategory = function($name) use ($allGuests, $userLogs) {
            // 1. Try current guests
            foreach ($allGuests as $g) {
                if ($g['name'] === $name) return $g['category'] ?? 'in person';
            }
            // 2. Try historical logs
            foreach ($userLogs as $l) {
                if (($l['data']['name'] ?? '') === $name) return $l['data']['category'] ?? 'in person';
            }
            // 3. Fallback for admin or unknown
            return 'admin';
        };

        foreach ($playlistData as $track) {
            $user = $track['user'] ?? 'Admin';
            $trackLogs[] = [
                'title' => $track['title'] ?? 'Unknown',
                'userName' => $user,
                'category' => $findCategory($user),
                'added_at' => $track['added_at'] ?? time(),
                'status' => 'Queued'
            ];
            $addedVideoUserPairs[] = ($track['id'] ?? '') . $user;
        }

        // B. From Guests (Requested/Waiting songs)
        foreach ($allGuests as $g) {
            if (isset($g['songs']) && is_array($g['songs'])) {
                foreach ($g['songs'] as $song) {
                    $pair = ($song['id'] ?? '') . $g['name'];
                    if (in_array($pair, $addedVideoUserPairs)) continue;

                    $trackLogs[] = [
                        'title' => $song['title'] ?? 'Unknown',
                        'userName' => $g['name'],
                        'category' => $g['category'] ?? 'in person',
                        'added_at' => $song['added_at'] ?? time(),
                        'status' => $song['status'] ?? 'Waiting'
                    ];
                }
            }
        }

        // Sort tracks: newest first
        usort($trackLogs, function($a, $b) {
            return $b['added_at'] - $a['added_at'];
        });

        // Sort logs: newest first
        usort($userLogs, function($a, $b) {
            $tsA = $a['data']['timestamp'] ?? $a['timestamp'];
            $tsB = $b['data']['timestamp'] ?? $b['timestamp'];
            return $tsB - $tsA;
        });

        require_once 'views/admin_logs.php';
    }

    public function codes() {
        require_once 'app/Models/Settings.php';
        $settings = new Settings($this->groupId);
        
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
        $settings = new Settings($this->groupId);
        
        if ($settings->update(function($current) use ($codes) {
            $current['guest_codes'] = $codes;
            // Remove legacy fields
            unset($current['guest_code']);
            unset($current['category']);
            return $current;
        })) {
            echo json_encode(['success' => true, 'codes' => $codes]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save codes']);
        }
    }

    public function generateDistantCode() {
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
            'created_at' => time(),
            'group_id' => $this->groupId
        ];

        $code = EncryptionService::encrypt($payload);
        echo json_encode(['success' => true, 'code' => $code]);
    }

    public function generateHotelCode() {
        header('Content-Type: application/json');
        
        // Generate random 6-digit number
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        require_once 'app/Models/Settings.php';
        $settings = new Settings($this->groupId);
        if ($settings->set('hotel_code', $code)) {
            echo json_encode(['success' => true, 'code' => $code]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save hotel code']);
        }
    }

    public function toggleSession() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $allow = (bool)($data['allow'] ?? true);

        require_once 'app/Models/Settings.php';
        $settings = new Settings($this->groupId);
        if ($settings->set('allow_new_sessions', $allow)) {
            echo json_encode(['success' => true, 'allow' => $allow]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update session status']);
        }
    }

    public function deleteGuest() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $guestId = $data['guestId'] ?? '';

        if (empty($guestId)) {
            echo json_encode(['success' => false, 'error' => 'Guest ID is required']);
            exit;
        }

        require_once 'app/Models/Guest.php';
        $guestModel = new Guest($this->groupId);
        
        if ($guestModel->remove($guestId)) {
            // Also remove their activity logs
            require_once 'app/Models/SystemLog.php';
            $sysLog = new SystemLog($this->groupId);
            $sysLog->removeLogsByGuestId($guestId);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Guest not found or could not be deleted']);
        }
    }

    public function downloadTracksList() {
        require_once 'app/Models/Guest.php';
        $guestModel = new Guest($this->groupId);
        $allGuestsData = $guestModel->getAll();
        $allGuests = $allGuestsData['guests'] ?? [];

        require_once 'app/Models/Playlist.php';
        $playlistModel = new Playlist($this->groupId);
        $playlistData = json_decode($playlistModel->getAll(), true) ?: [];

        $lines = [];
        $lines[] = "KARAOKE SONG LIST - " . date('Y-m-d H:i:s');
        $lines[] = "--------------------------------------------------";
        $lines[] = str_pad("#", 5) . str_pad("TITLE", 50) . str_pad("USER", 20) . "GROUP";
        $lines[] = "--------------------------------------------------";

        $tracks = [];
        $addedPairs = [];

        // Helper to find category by name (simpler version for export)
        $findCategory = function($name) use ($allGuests) {
            foreach ($allGuests as $g) {
                if ($g['name'] === $name) return $g['category'] ?? 'in person';
            }
            return 'admin';
        };

        // A. Add from active playlist
        foreach ($playlistData as $track) {
            $user = $track['user'] ?? 'Admin';
            $tracks[] = [
                'title' => $track['title'] ?? 'Unknown',
                'userName' => $user,
                'category' => $findCategory($user),
                'added_at' => $track['added_at'] ?? time()
            ];
            $addedPairs[] = ($track['id'] ?? '') . $user;
        }

        // B. Add from guest requests (if not already in playlist)
        foreach ($allGuests as $g) {
            if (isset($g['songs']) && is_array($g['songs'])) {
                foreach ($g['songs'] as $song) {
                    $pair = ($song['id'] ?? '') . $g['name'];
                    if (in_array($pair, $addedPairs)) continue;

                    $tracks[] = [
                        'title' => $song['title'] ?? 'Unknown',
                        'userName' => $g['name'],
                        'category' => $g['category'] ?? 'in person',
                        'added_at' => $song['added_at'] ?? time()
                    ];
                }
            }
        }

        // Sort by added_at (oldest first for the export list usually)
        usort($tracks, function($a, $b) {
            return $a['added_at'] - $b['added_at'];
        });

        foreach ($tracks as $index => $t) {
            $num = $index + 1;
            $lines[] = str_pad($num, 5) . 
                       str_pad(mb_strimwidth($t['title'], 0, 48, "..."), 50) . 
                       str_pad(mb_strimwidth($t['userName'], 0, 18, "..."), 20) . 
                       $t['category'];
        }

        $content = implode("\r\n", $lines);
        $filename = "song_list_" . date('Y-m-d') . ".txt";

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
