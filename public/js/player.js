let player;
let playlist = [];
let currentVideoIndex = -1;
const playerCheckInterval = 5000;
let lastCommandTimestamp = 0;
let isPlayerReady = false;

// --- Screen pairing state ---
let GROUP_ID = null;
let isPaired = false;
let screenCode = null; // current public code (rotates)
let secretId = null;   // permanent per-device identifier (sessionStorage)

const CODE_TTL = 60; // seconds

// Secret ID: permanent for this browser session, never sent to the user
function getOrCreateSecretId() {
    let id = sessionStorage.getItem('karaoke_screen_secret');
    if (!id) {
        // Generate a UUID-like random identifier
        id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        sessionStorage.setItem('karaoke_screen_secret', id);
    }
    return id;
}

// Public code: rotates every 60s, stored in localStorage for persistence across refreshes
function generateNewCode() {
    const code = String(Math.floor(100000 + Math.random() * 900000));
    const expiresAt = Math.floor(Date.now() / 1000) + CODE_TTL;
    localStorage.setItem('karaoke_screen_code', code);
    localStorage.setItem('karaoke_screen_code_expires', String(expiresAt));
    return { code, expiresAt };
}

function getOrCreateScreenCode() {
    const stored = localStorage.getItem('karaoke_screen_code');
    const expiresAt = parseInt(localStorage.getItem('karaoke_screen_code_expires') || '0');
    const now = Math.floor(Date.now() / 1000);
    if (stored && /^\d{6}$/.test(stored) && expiresAt > now) {
        return { code: stored, expiresAt };
    }
    return generateNewCode();
}

// --- Countdown circle ---
let codeExpiresAt = 0;
let timerTick = null;

function startCodeTimer(expiresAt) {
    codeExpiresAt = expiresAt;
    const arc = document.getElementById('timer-arc');
    if (!arc) return;

    if (timerTick) clearInterval(timerTick);

    const tick = () => {
        const remaining = codeExpiresAt - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
            // Time's up — rotate to a new code
            clearInterval(timerTick);
            timerTick = null;
            rotateCode();
            return;
        }
        // Arc always ends at 12 o'clock; gap grows clockwise from 12.
        // visible = fraction of circumference (100 units)
        // dashoffset shifts the arc so its tail lands at 12 o'clock.
        const fraction = remaining / CODE_TTL; // 1 → 0
        const visible = fraction * 100;
        arc.style.strokeDasharray = `${visible} 100`;
        arc.style.strokeDashoffset = String(-(100 - visible));
    };

    tick();
    timerTick = setInterval(tick, 1000);
}

async function rotateCode() {
    const { code, expiresAt } = generateNewCode();
    screenCode = code;
    document.getElementById('screen-code-display').textContent = code;
    await registerScreen(); // re-registers same secret_id with new public_code
    startCodeTimer(expiresAt);
}

function showPairingOverlay() {
    document.getElementById('pairing-overlay').classList.remove('hidden');
    document.getElementById('overlay-message').classList.remove('hidden');
}

function hidePairingOverlay() {
    document.getElementById('pairing-overlay').classList.add('hidden');
}

function onPaired(groupId, groupName) {
    GROUP_ID = groupId;
    isPaired = true;
    hidePairingOverlay();
    const partyNameEl = document.getElementById('party-name');
    if (partyNameEl) {
        partyNameEl.textContent = groupName || '';
        partyNameEl.classList.toggle('hidden', !groupName);
    }
    // Start the YouTube player if not already initialised
    if (!isPlayerReady && typeof YT !== 'undefined' && YT.Player) {
        initYouTubePlayer();
    }
    pollPlaylist();
}

function onUnpaired() {
    GROUP_ID = null;
    isPaired = false;
    currentVideoIndex = -1;
    playlist = [];
    isWaitingForMore = false;
    lastCommandTimestamp = 0;
    isFirstStatusPoll = true;

    // Stop any active playback
    if (player && typeof player.stopVideo === 'function') player.stopVideo();
    html5Player.pause();
    html5Player.src = '';

    // Hide player, show welcome + pairing overlay
    document.getElementById('player-container').classList.add('hidden');
    document.getElementById('next-song-overlay').classList.add('hidden');
    document.getElementById('overlay-message').classList.remove('hidden');
    document.getElementById('overlay-message').querySelector('h1').textContent = 'Welcome to my Karaoke app!';
    const partyNameEl = document.getElementById('party-name');
    if (partyNameEl) { partyNameEl.textContent = ''; partyNameEl.classList.add('hidden'); }
    togglePauseOverlay(false);
    showPairingOverlay();
    rotateCode();
}

async function pollPairing() {
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') +
            'api/screen_pair_status?secret_id=' + encodeURIComponent(secretId);
        const resp = await fetch(apiUrl, { cache: 'no-store' });
        const data = await resp.json();
        if (data.paired && data.group_id) {
            if (!isPaired) onPaired(data.group_id, data.group_name || '');
        } else {
            if (isPaired) onUnpaired();
        }
    } catch (e) {
        // Network error — keep current state
    }
}

async function registerScreen() {
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/register_screen';
        await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ secret_id: secretId, public_code: screenCode })
        });
    } catch (e) {
        // Silently ignore — will retry on next rotation
    }
}

// Kick off pairing flow on page load
(function initPairing() {
    secretId = getOrCreateSecretId();
    const { code, expiresAt } = getOrCreateScreenCode();
    screenCode = code;
    document.getElementById('screen-code-display').textContent = screenCode;
    showPairingOverlay();
    registerScreen();
    startCodeTimer(expiresAt);
    // Pairing poll runs forever — handles pair and unpair events
    setInterval(pollPairing, 3000);
    pollPairing();
    // Playlist & status polls run forever — no-ops when unpaired
    setInterval(pollPlaylist, 5000);
    setInterval(pollStatus, 1000);
})();

// --- Player helpers ---

function reportStatus(state) {
    if (!isPaired || currentVideoIndex < 0) return;
    const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') +
        'api/update_status?group_id=' + GROUP_ID;
    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ index: currentVideoIndex, state: state })
    }).catch(err => console.error('Status report error', err));
}

// --- YouTube IFrame API ---

function initYouTubePlayer() {
    player = new YT.Player('player', {
        height: '100%',
        width: '100%',
        playerVars: {
            'playsinline': 1,
            'controls': 0,
            'rel': 0,
            'autoplay': 1,
            'fs': 0
        },
        events: {
            'onReady': onPlayerReady,
            'onStateChange': onPlayerStateChange,
            'onError': onPlayerError
        }
    });
}

// Called by YouTube IFrame API when script loads
function onYouTubeIframeAPIReady() {
    // Only init immediately if already paired; otherwise onPaired() will call initYouTubePlayer()
    if (isPaired) {
        initYouTubePlayer();
    }
}

function onPlayerReady(event) {
    isPlayerReady = true;
    pollPlaylist();
}

let activePlayer = 'youtube';
const html5Player = document.getElementById('html5-player');

html5Player.addEventListener('ended', () => { playNext(); });
html5Player.addEventListener('play', () => { reportStatus('playing'); togglePauseOverlay(false); });
html5Player.addEventListener('pause', () => { reportStatus('paused'); togglePauseOverlay(true); });

function onPlayerError(event) {
    console.error('YouTube Player Error:', event.data);
    if ([100, 101, 150].includes(event.data)) {
        showOverlay('Video embedded restricted. Attempting fallback...');
        const currentVideo = playlist[currentVideoIndex];
        if (currentVideo && currentVideo.id) {
            resolveStream(currentVideo.id);
        } else {
            playNext();
        }
    }
}

async function resolveStream(videoId) {
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') +
            'api/resolve_stream?group_id=' + GROUP_ID;
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
    if (player && typeof player.stopVideo === 'function') {
        player.stopVideo();
    }
    document.getElementById('player').classList.add('hidden');
    html5Player.classList.remove('hidden');
    html5Player.src = url;
    html5Player.play();
    activePlayer = 'html5';
    showOverlay(null);
}

function switchToYoutube() {
    html5Player.pause();
    html5Player.src = '';
    if (activePlayer !== 'youtube') {
        html5Player.classList.add('hidden');
        document.getElementById('player').classList.remove('hidden');
        activePlayer = 'youtube';
    }
}

function onPlayerStateChange(event) {
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
    if (!isPaired) return;
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') +
            'api/get_playlist?group_id=' + GROUP_ID;
        const response = await fetch(apiUrl, { cache: 'no-store' });
        const newPlaylist = await response.json();

        if (JSON.stringify(newPlaylist) !== JSON.stringify(playlist)) {
            const wasQueueEmpty = playlist.length === 0;
            const currentVideoId = (currentVideoIndex !== -1 && playlist[currentVideoIndex])
                ? playlist[currentVideoIndex].id : null;

            playlist = newPlaylist;

            if (currentVideoId) {
                const newIndex = playlist.findIndex(v => v.id === currentVideoId);
                if (newIndex !== -1) currentVideoIndex = newIndex;
            }

            if (isWaitingForMore && playlist.length > currentVideoIndex + 1) {
                playNext();
            }
        }
    } catch (error) {
        console.error('Error fetching playlist:', error);
    }
}

function playNext() {
    const nextIndex = currentVideoIndex + 1;

    if (nextIndex >= playlist.length) {
        const overlay = document.getElementById('overlay-message');
        overlay.querySelector('h1').textContent = 'Welcome to my Karaoke app!';
        overlay.classList.remove('hidden');
        document.getElementById('player-container').classList.add('hidden');
        isWaitingForMore = true;
        return;
    }

    currentVideoIndex = nextIndex;
    isWaitingForMore = false;

    const video = playlist[currentVideoIndex];
    if (!video) return;

    showInterstitial(video);
}

function showInterstitial(video) {
    if (activePlayer === 'youtube' && player && typeof player.stopVideo === 'function') {
        player.stopVideo();
    } else {
        html5Player.pause();
        html5Player.currentTime = 0;
    }

    showOverlay(null);
    const nextOverlay = document.getElementById('next-song-overlay');
    document.getElementById('next-song-title').textContent = video.title;
    document.getElementById('next-song-user').textContent = video.user || 'Unknown';
    nextOverlay.classList.remove('hidden');
    document.getElementById('player-container').classList.add('hidden');

    setTimeout(() => {
        nextOverlay.classList.add('hidden');
        document.getElementById('player-container').classList.remove('hidden');
        startPlayback(video);
    }, 8000);
}

function startPlayback(video) {
    console.log('Playing:', video.title);
    reportStatus('playing');
    if (video.local_file) {
        playHtml5(video.local_file);
    } else if (isPlayerReady) {
        showOverlay(null);
        switchToYoutube();
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

// Remote Control Polling

let isFirstStatusPoll = true;

async function pollStatus() {
    if (!isPaired) return;
    try {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') +
            'api/get_status?group_id=' + GROUP_ID;
        const response = await fetch(apiUrl, { cache: 'no-store' });
        const data = await response.json();

        if (isFirstStatusPoll) {
            isFirstStatusPoll = false;
            if (data && data.command_timestamp) {
                lastCommandTimestamp = data.command_timestamp;
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
            if (currentVideoIndex === -1 && playlist.length > 0) {
                playNext();
            } else {
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
                reportStatus('playing');
                const video = playlist[currentVideoIndex];
                if (video) {
                    showInterstitial(video);
                }
            }
            break;
    }
}
