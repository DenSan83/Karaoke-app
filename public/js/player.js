let player;
let playlist = [];
let currentVideoIndex = -1;
const playerCheckInterval = 5000;
let lastCommandTimestamp = 0;
let isPlayerReady = false;

// Helper to report status to backend/admin
function reportStatus(state) {
    if (currentVideoIndex >= 0) {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/update_status' + (typeof GROUP_ID !== 'undefined' ? '?group_id=' + GROUP_ID : '');
        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ index: currentVideoIndex, state: state })
        }).catch(err => console.error("Status report error", err));
    }
}


// Load YouTube IFrame API
function onYouTubeIframeAPIReady() {
    player = new YT.Player('player', {
        height: '100%',
        width: '100%',
        playerVars: {
            'playsinline': 1,
            'controls': 0, // Hide controls for cinematic feel
            'rel': 0,
            'autoplay': 1,
            'fs': 0 // We handle FS manually
        },
        events: {
            'onReady': onPlayerReady,
            'onStateChange': onPlayerStateChange,
            'onError': onPlayerError
        }
    });
}

function onPlayerReady(event) {
    isPlayerReady = true;
    pollPlaylist();
}

let activePlayer = 'youtube'; // 'youtube' or 'html5'
const html5Player = document.getElementById('html5-player');

// HTML5 Player Ended Event
html5Player.addEventListener('ended', () => {
    playNext();
});

html5Player.addEventListener('play', () => {
    reportStatus('playing');
    togglePauseOverlay(false);
});

html5Player.addEventListener('pause', () => {
    reportStatus('paused');
    togglePauseOverlay(true);
});

function onPlayerError(event) {
    console.error('YouTube Player Error:', event.data);
    // Error 150/101 = Restricted playback. Error 100 = Not found/Removed.
    if ([100, 101, 150].includes(event.data)) {
        showOverlay('Video embedded restricted. Attempting fallback...');

        // Try fallback to Invidious stream
        const currentVideo = playlist[currentVideoIndex];
        if (currentVideo && currentVideo.id) {
            resolveStream(currentVideo.id);
        } else {
            // If no ID (weird), just skip
            playNext();
        }
    }
}

async function resolveStream(videoId) {
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/resolve_stream' + (typeof GROUP_ID !== 'undefined' ? '?group_id=' + GROUP_ID : '');
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ videoId: videoId })
        });
        const data = await response.json();

        if (data.url) {
            playHtml5(data.url);
        } else {
            showOverlay('Fallback failed. Skipping...');
            setTimeout(playNext, 2000);
        }
    } catch (error) {
        console.error('Error resolving stream:', error);
        playNext();
    }
}

function playHtml5(url) {
    // Stop YouTube if it's playing
    if (player && typeof player.stopVideo === 'function') {
        player.stopVideo();
    }

    // Hide YouTube, Show HTML5
    document.getElementById('player').classList.add('hidden');
    html5Player.classList.remove('hidden');
    html5Player.src = url;
    html5Player.play();
    activePlayer = 'html5';
    showOverlay(null);
}

function switchToYoutube() {
    // Stop HTML5
    html5Player.pause();
    html5Player.src = ""; // Clear source to stop buffering

    if (activePlayer !== 'youtube') {
        html5Player.classList.add('hidden');
        document.getElementById('player').classList.remove('hidden');
        activePlayer = 'youtube';
    }
}

function onPlayerStateChange(event) {
    // When video ends (0),function onPlayerStateChange(event) {
    if (event.data === YT.PlayerState.ENDED) {
        playNext();
        reportStatus('ended');
    } else if (event.data === YT.PlayerState.PLAYING) {
        reportStatus('playing');
        togglePauseOverlay(false);
    } else if (event.data === YT.PlayerState.PAUSED) {
        reportStatus('paused');
        togglePauseOverlay(true);
    }
}

let isWaitingForMore = false;

async function pollPlaylist() {
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/get_playlist' + (typeof GROUP_ID !== 'undefined' ? '?group_id=' + GROUP_ID : '');
        const response = await fetch(apiUrl, { cache: 'no-store' });
        const newPlaylist = await response.json();

        // Always update playlist to handle removals/reordering
        if (JSON.stringify(newPlaylist) !== JSON.stringify(playlist)) {
            const wasQueueEmpty = playlist.length === 0;
            const currentVideoId = (currentVideoIndex !== -1 && playlist[currentVideoIndex]) ? playlist[currentVideoIndex].id : null;

            playlist = newPlaylist;

            console.log('Playlist updated:', playlist);

            // Sync currentVideoIndex if already playing
            if (currentVideoId) {
                const newIndex = playlist.findIndex(v => v.id === currentVideoId);
                if (newIndex !== -1) {
                    currentVideoIndex = newIndex;
                }
            }

            // If queue was empty and we weren't playing, DO NOT start automatically. Wait for manual play.
            if (wasQueueEmpty && currentVideoIndex === -1 && playlist.length > 0) {
                // playNext(); // DISABLE AUTOPLAY
            } else if (isWaitingForMore && playlist.length > currentVideoIndex + 1) {
                // We were waiting, and now there's more!
                playNext();
            }
        }
    } catch (error) {
        console.error('Error fetching playlist:', error);
    }
}

function playNext() {
    // Move to next video
    const nextIndex = currentVideoIndex + 1;

    if (nextIndex >= playlist.length) {
        // Show Welcome Screen
        const overlay = document.getElementById('overlay-message');
        overlay.querySelector('h1').textContent = 'Welcome to my Karaoke app!';
        overlay.classList.remove('hidden');
        document.getElementById('player-container').classList.add('hidden');

        isWaitingForMore = true;
        return;
    }

    currentVideoIndex = nextIndex;
    isWaitingForMore = false;

    // Get next video
    const video = playlist[currentVideoIndex];
    if (!video) return;

    // Show Interstitial Screen
    showInterstitial(video);
}

function showInterstitial(video) {
    // Stop any currently playing video immediately
    if (activePlayer === 'youtube' && player && typeof player.stopVideo === 'function') {
        player.stopVideo();
    } else {
        html5Player.pause();
        html5Player.currentTime = 0; // Reset
    }

    // Hide everything else
    showOverlay(null);
    const nextOverlay = document.getElementById('next-song-overlay');

    document.getElementById('next-song-title').textContent = video.title;
    document.getElementById('next-song-user').textContent = video.user || 'Unknown';

    nextOverlay.classList.remove('hidden');
    document.getElementById('player-container').classList.add('hidden'); // Ensure player is hidden

    // Wait 8 seconds then play
    setTimeout(() => {
        nextOverlay.classList.add('hidden');
        document.getElementById('player-container').classList.remove('hidden');
        startPlayback(video);
    }, 8000);
}

function startPlayback(video) {
    console.log('Playing:', video.title);

    // Update status to admin
    reportStatus('playing');

    // Check for local file first
    if (video.local_file) {
        console.log('Playing local file:', video.local_file);
        playHtml5(video.local_file);
    } else if (isPlayerReady) {
        showOverlay(null); // Hide overlay
        switchToYoutube(); // Ensure YT is visible
        player.loadVideoById(video.id);
    }
}

function showOverlay(message) {
    const overlay = document.getElementById('overlay-message');
    const overlayText = overlay.querySelector('h1');

    if (message) {
        overlayText.textContent = message;
        overlay.classList.remove('hidden');
        document.getElementById('player-container').classList.add('hidden');
    } else {
        overlay.classList.add('hidden');
        document.getElementById('player-container').classList.remove('hidden');
    }
}

// Pause Overlay Logic
const pauseOverlay = document.getElementById('pause-overlay');

pauseOverlay.addEventListener('click', () => {
    if (activePlayer === 'youtube') {
        player.playVideo();
    } else {
        html5Player.play();
    }
});

function togglePauseOverlay(show) {
    if (show) {
        pauseOverlay.classList.remove('hidden');
    } else {
        pauseOverlay.classList.add('hidden');
    }
}

// Fullscreen logic
const fullscreenBtn = document.getElementById('fullscreenBtn');
if (fullscreenBtn) {
    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
            fullscreenBtn.textContent = 'Exit Fullscreen';
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
                fullscreenBtn.textContent = 'Enter Fullscreen';
            }
        }
    });
}

// Poll every 5 seconds for new songs
setInterval(pollPlaylist, 5000);

// Remote Control Polling

let isFirstStatusPoll = true;

async function pollStatus() {
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/get_status' + (typeof GROUP_ID !== 'undefined' ? '?group_id=' + GROUP_ID : '');
        const response = await fetch(apiUrl, { cache: 'no-store' });
        const data = await response.json();

        // Handle first poll:
        if (isFirstStatusPoll) {
            isFirstStatusPoll = false;
            if (data && data.command_timestamp) {
                lastCommandTimestamp = data.command_timestamp; // Sync timestamp without executing
            }
            return;
        }

        if (data && data.command_timestamp && data.command_timestamp > lastCommandTimestamp) {
            lastCommandTimestamp = data.command_timestamp;
            executeCommand(data);
        }
    } catch (error) {
        console.error('Error fetching status:', error);
    }
}

function executeCommand(commandObj) {
    const command = commandObj.command;
    const payload = commandObj.payload || {};

    console.log('Executing command:', command, payload);
    if (!isPlayerReady) return;

    switch (command) {
        case 'play':
            // If we haven't started yet and there are songs, start!
            if (currentVideoIndex === -1 && playlist.length > 0) {
                playNext();
            } else {
                // Determine if we need to show the player
                const playerContainer = document.getElementById('player-container');
                if (playerContainer.classList.contains('hidden')) {
                    showOverlay(null);
                }

                if (activePlayer === 'youtube') {
                    player.playVideo();
                } else {
                    html5Player.play();
                }
            }
            break;
        case 'pause':
            if (activePlayer === 'youtube') player.pauseVideo();
            else html5Player.pause();
            break;
        case 'restart':
            if (activePlayer === 'youtube') {
                player.seekTo(0);
                player.playVideo();
            } else {
                html5Player.currentTime = 0;
                html5Player.play();
            }
            break;
        case 'next':
            playNext();
            break;
        case 'jump':
            if (typeof payload.index !== 'undefined') {
                currentVideoIndex = payload.index;
                reportStatus('playing'); // Report status immediately on jump
                const video = playlist[currentVideoIndex];

                // Ensure player is visible
                // showOverlay(null); // No longer needed as showInterstitial handles it

                if (video) {
                    showInterstitial(video); // Show "Next Song [Title]" for 4s
                }
            }
            break;
    }
}

// Poll status every 1 second for responsiveness
setInterval(pollStatus, 1000);
