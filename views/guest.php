<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - Karaoke Party</title>
    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/guest.css">
</head>
<body>
    <div class="guest-container">
        <header class="guest-header">
            <div class="guest-welcome">
                <h1>Hi, <?php echo htmlspecialchars($guest['name']); ?>!</h1>
                <h2 class="party-name"><?php echo htmlspecialchars($partyName); ?></h2>
                <p>What would you like to sing?</p>
            </div>
            <a href="logout" class="logout-icon" title="Leave Party">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                    <line x1="12" y1="2" x2="12" y2="12"></line>
                </svg>
            </a>
        </header>

        <div id="notifications-area">
            <?php if (!empty($guest['notifications'])): ?>
                <?php foreach (array_reverse($guest['notifications']) as $notif): ?>
                    <div class="notification-banner">
                        <span class="icon">🔔</span>
                        <div class="text"><?php echo htmlspecialchars($notif['message']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="search-help">
            <span class="help-icon">💡</span>
            <p class="help-text">
                <b>To request a song:</b> Open YouTube, copy the link of your favorite video (<b>ensure "karaoke" is in the title</b>), and paste it below. Then hit ➜!
            </p>
        </div>

        <section class="search-section">
            <h3>Request a Song</h3>
            <div class="search-group">
                <div class="input-wrapper">
                    <input type="text" id="song-url" class="search-input" placeholder="Paste YouTube URL here" autocomplete="off">
                    <div id="search-results" class="search-dropdown"></div>
                </div>
                <button id="request-btn" class="add-btn" title="Add Song">➜</button>
            </div>
            <p id="request-msg" class="request-status-msg"></p>
        </section>

        <section class="my-queue">
            <div class="my-queue-header">
                <h2>My Requests</h2>
                <button id="reorder-btn" class="guest-reorder-btn">Reorder</button>
            </div>
            <ul id="guest-songs">
                <?php if (empty($guest['songs'])): ?>
                    <li class="empty-queue-msg">
                        You haven't requested any songs yet.<br>
                        <small>Add a YouTube URL above to join the fun!</small>
                    </li>
                <?php else: ?>
                    <?php 
                    // Show latest first
                    $songs = array_reverse($guest['songs']);
                    foreach ($songs as $song): 
                    ?>
                        <li class="song-item">
                            <img src="https://img.youtube.com/vi/<?php echo htmlspecialchars($song['id'] ?? ''); ?>/mqdefault.jpg" class="video-thumbnail" alt="thumbnail">
                            <div class="video-info">
                                <div class="video-title"><?php echo htmlspecialchars($song['title'] ?? 'Song Request'); ?></div>
                                <div class="video-id"><?php echo htmlspecialchars($song['id'] ?? ''); ?></div>
                            </div>
                            <?php 
                                $displayStatus = isset($calculateStatus) 
                                    ? $calculateStatus($song['id'], $song['status'] ?? 'Waiting') 
                                    : ($song['status'] ?? 'Waiting'); 
                                
                                $statusClass = strtolower($song['status'] ?? 'waiting');
                                if ($displayStatus === 'Singing now') $statusClass .= ' singing-now';
                                elseif ($displayStatus === 'Coming up') $statusClass .= ' coming-up';
                                elseif (strpos($displayStatus, 'songs left') !== false) $statusClass .= ' songs-left';
                                elseif ($displayStatus === 'Done') $statusClass = 'done'; // Override status class for Done
                            ?>
                            <div class="song-status <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($displayStatus); ?>
                            </div>
                            <button class="remove-song-btn" onclick="confirmRemoveSong('<?php echo $song['id']; ?>', '<?php echo addslashes($song['title']); ?>')" title="Remove Song">×</button>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    </div>

    <!-- Collision Modal (Re-named for extreme specificity) -->
    <div id="guest-collision-modal" class="guest-modal-overlay">
        <div class="guest-modal-content">
            <h2>Duet Potential!</h2>
            <p id="guest-collision-text" class="guest-modal-text"></p>
            <div class="guest-modal-actions">
                <button id="guest-collision-yes" class="guest-modal-btn confirm">Yes, sing together!</button>
                <button id="guest-collision-no" class="guest-modal-btn cancel">No, cancel request</button>
            </div>
        </div>
    </div>

    <!-- Social Notification Modal -->
    <div id="social-notif-modal" class="guest-modal-overlay">
        <div class="guest-modal-content">
            <h2 id="social-notif-title">Duo Request!</h2>
            <p id="social-notif-text" class="guest-modal-text"></p>
            <div class="guest-modal-actions">
                <button id="social-notif-ok" class="guest-modal-btn confirm">OK!!</button>
            </div>
        </div>
    </div>

    <!-- Karaoke Confirmation Modal -->
    <div id="guest-karaoke-confirm-modal" class="guest-modal-overlay">
        <div class="guest-modal-content">
            <h2>Are you sure?</h2>
            <p id="guest-karaoke-confirm-text" class="guest-modal-text"></p>
            <div class="guest-modal-actions">
                <button id="guest-karaoke-yes" class="guest-modal-btn confirm">Yes, add track</button>
                <button id="guest-karaoke-no" class="guest-modal-btn cancel">No, cancel</button>
            </div>
        </div>
    </div>

    <script>
        const BASE_PATH = <?= json_encode($this->basePath ?? '') ?>;
        // Define global variables for the external script
        window.guestSongs = <?php echo json_encode($guest['songs']); ?>;
        window.activeNotifications = <?php echo json_encode($guest['notifications'] ?? []); ?>;
    </script>
    <script src="public/js/guest.js"></script>
</body>
</html>
