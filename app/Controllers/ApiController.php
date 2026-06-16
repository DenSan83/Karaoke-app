<?php
require_once 'app/Models/Playlist.php';
require_once 'app/Models/PlayerStatus.php';

class ApiController {
    private $playlistModel;
    private $statusModel;
    private $groupId;

    public function __construct() {
        $this->groupId = $_SESSION['group_id'] ?? null;
        
        // If not in session (e.g. for player screen), try to get from header or query
        if (!$this->groupId) {
            $this->groupId = $_GET['group_id'] ?? ($_SERVER['HTTP_X_GROUP_ID'] ?? null);
        }

        $this->playlistModel = new Playlist($this->groupId);
        $this->statusModel = new PlayerStatus($this->groupId);
    }

    private function checkAuth() {
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return false;
        }
        return true;
    }

    public function getPlaylist() {
        header('Content-Type: application/json');
        echo $this->playlistModel->getAll();
    }

    public function addVideo() {
        if (!$this->checkAuth()) return;
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $url = $data['url'] ?? '';
        $user = $data['user'] ?? '';
        
        $result = $this->playlistModel->add($url, $user);
        
        if (isset($result['error'])) {
            http_response_code(400);
            echo json_encode($result);
        } else {
            // Update Guest Status to 'Accepted' if this was a guest request
            // We search for the videoId in all guests
            if (isset($result['video']['id'])) {
                require_once 'app/Models/Guest.php';
                $guestModel = new Guest($this->groupId);
                // Pass null as guestId to search all guests
                $guestModel->updateSongStatus(null, $result['video']['id'], 'Accepted');
            }
            echo json_encode($result);
        }
    }

    public function getStatus() {
        header('Content-Type: application/json');
        $status = $this->statusModel->get();
        
        // Add current videoId for more robust tracking in UI
        if (isset($status['current_index'])) {
            $playlist = json_decode($this->playlistModel->getAll(), true);
            if (isset($playlist[$status['current_index']])) {
                $status['videoId'] = $playlist[$status['current_index']]['id'];
            }
        }
        
        echo json_encode($status);
    }

    public function sendCommand() {
        if (!$this->checkAuth()) return;
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $command = $data['command'] ?? '';
        $payload = $data['payload'] ?? [];
        
        $result = $this->statusModel->set($command, $payload);
        
        if ($result !== false) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save command']);
        }
    }

    public function removeVideo() {
        if (!$this->checkAuth()) return;
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $index = $data['index'] ?? null;
        
        if ($index === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Index is required']);
            return;
        }

        $result = $this->playlistModel->remove($index);
        
        if (isset($result['error'])) {
            http_response_code(400);
            echo json_encode($result);
        } else {
            echo json_encode($result);
        }
    }

    public function resolveStream() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $videoId = $data['videoId'] ?? null;
        
        if (!$videoId) {
            http_response_code(400);
            echo json_encode(['error' => 'videoId is required']);
            return;
        }

        // Check if we have a local download first
        $playlist = json_decode($this->playlistModel->getAll(), true);
        foreach ($playlist as $video) {
            if ($video['id'] === $videoId && !empty($video['local_path'])) {
                if (file_exists(__DIR__ . '/../../' . $video['local_path'])) {
                    echo json_encode(['url' => $video['local_path'], 'local' => true]);
                    return;
                }
            }
        }

        $result = $this->playlistModel->getStreamUrl($videoId);
        
        if (isset($result['error'])) {
            // Stream failed, attempt download
            $downloadResult = $this->playlistModel->spawnBackgroundDownload($videoId);
            echo json_encode([
                'error' => 'Stream unavailable, download started',
                'downloading' => true,
                'videoId' => $videoId
            ]);
        } else {
            echo json_encode($result);
        }
    }

    public function reorderPlaylist() {
        if (!$this->checkAuth()) return;
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $newPlaylist = $data['playlist'] ?? null;

        if ($newPlaylist === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Playlist data is required']);
            return;
        }

        // 1. Identify active video before reordering
        $currentStatus = $this->statusModel->get();
        $currentIndex = $currentStatus['current_index'] ?? -1;
        $activeVideoId = null;
        
        if ($currentIndex !== -1) {
            $oldPlaylist = json_decode($this->playlistModel->getAll(), true);
            if (isset($oldPlaylist[$currentIndex])) {
                $activeVideoId = $oldPlaylist[$currentIndex]['id'];
            }
        }

        // 2. Perform reorder
        $result = $this->playlistModel->reorder($newPlaylist);

        // 3. Find new index of active video and update status
        if ($result['success'] && $activeVideoId !== null) {
            foreach ($newPlaylist as $newIndex => $video) {
                if ($video['id'] === $activeVideoId) {
                    $this->statusModel->updateState($newIndex, $currentStatus['state'] ?? 'unknown');
                    break;
                }
            }
        }

        echo json_encode($result);
    }

    public function updateStatus() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $index = $data['index'] ?? null;
        $state = $data['state'] ?? 'unknown'; // playing, paused, ended
        
        if ($index !== null) {
            // Get previous index to mark as Done
            $currentStatus = $this->statusModel->get();
            $prevIndex = $currentStatus['current_index'] ?? -1;

            if ($this->statusModel->updateState($index, $state)) {
                
                // If index changed and moved forward, mark previous song as Done
                if ($prevIndex !== -1 && $index > $prevIndex) {
                    $playlist = json_decode($this->playlistModel->getAll(), true);
                    if (isset($playlist[$prevIndex])) {
                        $doneVideoId = $playlist[$prevIndex]['id'];
                        require_once 'app/Models/Guest.php';
                        $guestModel = new Guest();
                        $guestModel->updateSongStatus(null, $doneVideoId, 'Done');
                    }
                }

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to update status']);
            }
        } else {
            echo json_encode(['error' => 'Index required']);
        }
    }

    public function getProgress() {
        header('Content-Type: application/json');
        $videoId = $_GET['id'] ?? null;
        
        if (!$videoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Video ID required']);
            return;
        }
        
        $progressFile = __DIR__ . '/../../temp/progress_' . $videoId . '.json';
        
        if (file_exists($progressFile)) {
            $progressData = json_decode(file_get_contents($progressFile), true);
            echo json_encode([
                'progress' => $progressData['percent'] ?? 0,
                'status' => $progressData['status'] ?? 'unknown'
            ]);
        } else {
            // No progress file means either not started or completed
            echo json_encode(['progress' => 0, 'status' => 'not_started']);
        }
    }

    public function getRequests() {
        if (!$this->checkAuth()) return;
        header('Content-Type: application/json');
        
        require_once 'app/Models/Guest.php';
        $guestModel = new Guest($this->groupId);
        $guests = $guestModel->getAll();
        
        $flattenedRequests = [];
        if (isset($guests['guests'])) {
            foreach ($guests['guests'] as $guest) {
                if (isset($guest['songs']) && is_array($guest['songs'])) {
                    foreach ($guest['songs'] as $song) {
                        // Only show Waiting requests
                        if (($song['status'] ?? 'Waiting') === 'Waiting') {
                            $flattenedRequests[] = [
                                'guest_id' => $guest['id'],
                                'guest_name' => $guest['name'],
                                'video' => $song,
                                'timestamp' => $song['added_at'] ?? 0
                            ];
                        }
                    }
                }
            }
        }
        
        // Group by Video ID
        $grouped = [];
        foreach ($flattenedRequests as $req) {
            $vidId = $req['video']['id'];
            if (!isset($grouped[$vidId])) {
                $grouped[$vidId] = [];
            }
            $grouped[$vidId][] = $req;
        }

        $finalRequests = [];
        foreach ($grouped as $vidId => $group) {
            // Sort by timestamp to find first user
            usort($group, function($a, $b) {
                return $a['timestamp'] - $b['timestamp'];
            });

            // Combine names
            $names = array_map(function($item) {
                return $item['guest_name'];
            }, $group);
            
            // Remove duplicates just in case same user requested twice (though logic prevents that usually)
            $names = array_unique($names);
            
            $displayName = implode(' and ', $names);

            // Use the first request's data as base, but with combined name
            $base = $group[0];
            $base['guest_name'] = $displayName;
            // We set guest_id to null or a special value to indicate group, 
            // but for refuseRequest we might want to handle it differently.
            // If we set it to null, refuseRequest needs to handle it.
            $base['guest_id'] = null; 
            
            $finalRequests[] = $base;
        }

        // Sort final list by earliest timestamp
        usort($finalRequests, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        echo json_encode($finalRequests);
    }

    public function refuseRequest() {
        if (!$this->checkAuth()) return;
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $guestId = $data['guestId'] ?? null;
        $videoId = $data['videoId'] ?? null;

        if (!$videoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Video ID required']);
            return;
        }

        require_once 'app/Models/Guest.php';
        $guestModel = new Guest($this->groupId);
        
        // If guestId is null, this will update status for ALL guests with this videoId
        // This is desired behavior for grouped requests.
        if ($guestModel->updateSongStatus($guestId, $videoId, 'Refused')) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to refuse request']);
        }
    }
}
