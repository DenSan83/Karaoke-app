// Modal & Notification Elements
const collisionModal = document.getElementById('guest-collision-modal');
const collisionText = document.getElementById('guest-collision-text');
const collisionYes = document.getElementById('guest-collision-yes');
const collisionNo = document.getElementById('guest-collision-no');
const socialNotifModal = document.getElementById('social-notif-modal');
const socialNotifText = document.getElementById('social-notif-text');
const socialNotifOk = document.getElementById('social-notif-ok');
const requestBtn = document.getElementById('request-btn');
const urlInput = document.getElementById('song-url');
const msgDiv = document.getElementById('request-msg');
const forFriendToggle = document.getElementById('for-friend-toggle');
const friendNameContainer = document.getElementById('friend-name-container');
const friendNameInput = document.getElementById('friend-name');

if (forFriendToggle && friendNameContainer) {
    forFriendToggle.addEventListener('change', () => {
        if (forFriendToggle.checked) {
            friendNameContainer.style.display = 'block';
            if (friendNameInput) friendNameInput.focus();
        } else {
            friendNameContainer.style.display = 'none';
            if (friendNameInput) friendNameInput.value = '';
        }
    });
}
// Tab Elements
const tabs = document.querySelectorAll('.nav-link[data-tab]');
const tabPanes = document.querySelectorAll('.tab-pane');
const songList = document.getElementById('guest-songs');
const myRequestsSection = document.getElementById('my-requests-section');
const fullPlaylistSection = document.getElementById('full-playlist-section');
const fullPlaylistSongs = document.getElementById('full-playlist-songs');

let fullPlaylistVisible = false;

if (tabs.length > 0) {
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.getAttribute('data-tab');
            
            // Update tab buttons
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            // Update tab panes
            if (targetTab === 'full-playlist') {
                fullPlaylistVisible = true;
                if (myRequestsSection) {
                    myRequestsSection.style.display = 'none';
                    myRequestsSection.classList.remove('active');
                }
                if (fullPlaylistSection) {
                    fullPlaylistSection.style.display = 'block';
                    fullPlaylistSection.classList.add('active');
                }
                fetchFullPlaylist();
            } else {
                fullPlaylistVisible = false;
                if (myRequestsSection) {
                    myRequestsSection.style.display = 'block';
                    myRequestsSection.classList.add('active');
                }
                if (fullPlaylistSection) {
                    fullPlaylistSection.style.display = 'none';
                    fullPlaylistSection.classList.remove('active');
                }
            }
        });
    });
}

let lastPlaylistData = null;

async function fetchFullPlaylist() {
    try {
        const response = await fetch('api/get_playlist');
        const playlist = await response.json();
        const statusResponse = await fetch('api/get_status');
        const status = await statusResponse.json();
        
        const currentData = JSON.stringify({ playlist, status });
        if (lastPlaylistData === currentData) return;
        lastPlaylistData = currentData;

        renderFullPlaylist(playlist, status);
    } catch (e) {
        console.error("Failed to fetch full playlist:", e);
    }
}

function renderFullPlaylist(playlist, status) {
    if (!fullPlaylistSongs) return;
    
    if (!playlist || playlist.length === 0) {
        fullPlaylistSongs.innerHTML = '<li class="empty-queue-msg">The playlist is currently empty.</li>';
        return;
    }

    let html = '';
    // Guest sees: CURRENT track, and MAXIMUM 4 more BEFORE that one.
    // Plus all tracks AFTER the current one.
    const currentIndex = status.current_index;
    const startIndex = Math.max(0, currentIndex - 4);

    playlist.forEach((song, index) => {
        // Skip songs that are more than 4 positions before the current one
        if (index < startIndex) return;

        const isSinging = index === currentIndex;
        const playerState = status.state || 'paused';
        const isActuallyPlaying = isSinging && playerState === 'playing';
        
        const itemClass = isSinging ? 'full-playlist-item current-singing' : 'full-playlist-item';
        
        const isMySong = song.user === window.currentGuestName || (song.user && song.user.includes(`(added by ${window.currentGuestName})`));
        const userDisplay = isMySong ? 'Requested by: <span class="highlight-you">YOU</span>' : `Requested by: ${song.user}`;
        
        const equalizerHtml = isActuallyPlaying ? `
            <div class="equalizer-overlay">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        ` : '';

        html += `
            <li class="${itemClass}">
                <div class="thumbnail-container">
                    <img src="https://img.youtube.com/vi/${song.id}/mqdefault.jpg" class="video-thumbnail" alt="thumbnail">
                    ${equalizerHtml}
                </div>
                <div class="video-info">
                    <div class="video-title">${song.title}</div>
                    <div class="video-user">${userDisplay}</div>
                </div>
            </li>
        `;
    });
    fullPlaylistSongs.innerHTML = html;
}

// Karaoke Confirmation Modal
const karaokeModal = document.getElementById('guest-karaoke-confirm-modal');
const karaokeText = document.getElementById('guest-karaoke-confirm-text');
const karaokeYes = document.getElementById('guest-karaoke-yes');
const karaokeNo = document.getElementById('guest-karaoke-no');

// let guestSongs = ...
// let activeNotifications = ...

// Search & Autocomplete Logic
let searchTimeout = null;
const searchResults = document.getElementById('search-results');

urlInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const q = e.target.value.trim();

    if (q.length < 3) {
        searchResults.classList.remove('active');
        return;
    }

    // specific youtube url check - don't search if it looks like a URL
    if (q.includes('youtube.com') || q.includes('youtu.be')) {
        searchResults.classList.remove('active');
        return;
    }

    searchTimeout = setTimeout(() => performSearch(q), 300);
});

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!urlInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.classList.remove('active');
    }

    // Tooltip toggle
    const helpTrigger = document.querySelector('.help-trigger');
    const tooltip = document.querySelector('.search-help-tooltip');
    if (helpTrigger && tooltip) {
        if (helpTrigger.contains(e.target)) {
            tooltip.classList.toggle('active');
        } else if (!tooltip.contains(e.target)) {
            tooltip.classList.remove('active');
        }
    }
});

async function performSearch(query) {
    try {
        const res = await fetch(`api/search_songs?q=${encodeURIComponent(query)}`);
        const results = await res.json();

        if (results && results.length > 0) {
            renderSearchResults(results);
        } else {
            searchResults.classList.remove('active');
        }
    } catch (err) {
        console.error("Search error:", err);
    }
}

function renderSearchResults(results) {
    searchResults.innerHTML = '';
    results.forEach(item => {
        const div = document.createElement('div');
        div.className = 'search-item';
        div.innerHTML = `
            <span class="title">${item.title}</span>
            <span class="source">${item.source === 'playlist' ? 'Playlist' : 'History'}</span>
        `;
        div.addEventListener('click', () => {
            urlInput.value = `https://www.youtube.com/watch?v=${item.id}`;
            searchResults.classList.remove('active');
            // Optional: Auto-click request if desired, but maybe safer to let user click
            // requestBtn.click(); 
        });
        searchResults.appendChild(div);
    });
    searchResults.classList.add('active');
}

requestBtn.addEventListener('click', async () => {
    const url = urlInput.value.trim();
    const isForFriend = forFriendToggle && forFriendToggle.checked;
    const friendName = friendNameInput ? friendNameInput.value.trim() : '';

    if (isForFriend && !friendName) {
        msgDiv.textContent = "Please enter your friend's name";
        msgDiv.className = 'request-status-msg error';
        if (friendNameInput) friendNameInput.focus();
        setTimeout(() => {
            msgDiv.textContent = "";
            msgDiv.className = 'request-status-msg';
        }, 3000);
        return;
    }

    if (!url) {
        msgDiv.textContent = "Please enter a URL";
        msgDiv.className = 'request-status-msg error';
        setTimeout(() => {
            msgDiv.textContent = "";
            msgDiv.className = 'request-status-msg';
        }, 3000);
        return;
    }

    const btnText = requestBtn.querySelector('.btn-text');
    const btnSpinner = requestBtn.querySelector('.btn-spinner');

    msgDiv.textContent = "Testing if video can be played...";
    msgDiv.className = 'request-status-msg muted';
    requestBtn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnSpinner) btnSpinner.style.display = 'inline-block';

    try {
        const res = await fetch('api/guest_add_song', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                song: { url: url },
                friendName: isForFriend ? friendName : null
            })
        });
        const data = await res.json();

        if (data.success) {
            msgDiv.textContent = "Successfully added!";
            msgDiv.className = 'request-status-msg success';
            urlInput.value = '';

            if (data.duplicateFound) {
                showCollisionModal(data.originalSingerName, data.originalSingerId, data.videoId);

                setTimeout(() => {
                    if (collisionModal && collisionModal.classList.contains('active')) {
                        window.location.reload();
                    }
                }, 30000);
            } else {
                setTimeout(() => {
                    msgDiv.textContent = "";
                    msgDiv.className = 'request-status-msg';
                    window.location.reload();
                }, 1000);
            }
        } else if (data.needsConfirmation) {
            msgDiv.textContent = "";
            msgDiv.className = 'request-status-msg';
            requestBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';
            showKaraokeConfirmModal(data.title, url, data.failedWord, data.filterType);
        } else {
            msgDiv.textContent = data.error || "Failed to add song";
            msgDiv.className = 'request-status-msg error';
            requestBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';
        }
    } catch (err) {
        console.error("Add Song Error:", err);
        msgDiv.textContent = "Error: " + err.message;
        msgDiv.className = 'request-status-msg error';
        requestBtn.disabled = false;
        if (btnText) btnText.style.display = 'inline-block';
        if (btnSpinner) btnSpinner.style.display = 'none';
    }
});

async function addConfirmedSong(url) {
    const btnText = requestBtn.querySelector('.btn-text');
    const btnSpinner = requestBtn.querySelector('.btn-spinner');

    msgDiv.textContent = "Testing if video can be played...";
    msgDiv.className = 'request-status-msg muted';
    requestBtn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnSpinner) btnSpinner.style.display = 'inline-block';

    try {
        const isForFriend = forFriendToggle && forFriendToggle.checked;
        const friendName = friendNameInput ? friendNameInput.value.trim() : '';
        
        const res = await fetch('api/guest_add_song', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                song: { url: url },
                force: true,
                friendName: isForFriend ? friendName : null
            })
        });
        const data = await res.json();

        if (data.success) {
            msgDiv.textContent = "Successfully added!";
            msgDiv.className = 'request-status-msg success';
            urlInput.value = '';
            setTimeout(() => {
                msgDiv.textContent = "";
                msgDiv.className = 'request-status-msg';
                // Reset lastDashboardData to force re-render on next poll
                lastDashboardData = null;
                lastPlaylistData = null;
                fetchDashboardData();
            }, 1000);
        } else {
            msgDiv.textContent = data.error || "Failed to add song";
            msgDiv.className = 'request-status-msg error';
            requestBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';
        }
    } catch (err) {
        console.error("Add Confirmed Song Error:", err);
        msgDiv.textContent = "Error: " + err.message;
        msgDiv.className = 'request-status-msg error';
        requestBtn.disabled = false;
        if (btnText) btnText.style.display = 'inline-block';
        if (btnSpinner) btnSpinner.style.display = 'none';
    }
}

// Enter key support
urlInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') requestBtn.click();
});

function renderSongs() {
    if (!window.guestSongs) return;

    const songListContainer = document.getElementById('guest-songs');
    if (!songListContainer) return;

    if (window.guestSongs.length === 0) {
        songListContainer.innerHTML = `
            <li class="empty-queue-msg">
                You haven't requested any songs yet.<br>
                Add a YouTube URL above to join the fun!
            </li>
        `;
        return;
    }

    // Show latest first
    const reversedSongs = [...window.guestSongs].reverse();
    
    let html = '';
    reversedSongs.forEach((song) => {
        const baseStatus = song.status || 'Waiting';
        const displayStatus = song.display_status || baseStatus;
        const isActuallyPlaying = !!song.is_playing;
        
        let statusClass = baseStatus.toLowerCase();
        if (displayStatus === 'Singing now') statusClass += ' singing-now';
        else if (displayStatus === 'Coming up') statusClass += ' coming-up';
        else if (displayStatus.includes('songs left')) statusClass += ' songs-left';
        else if (displayStatus === 'Done') statusClass = 'done';

        const isSinging = displayStatus === 'Singing now';
        const isDone = displayStatus === 'Done';

        const equalizerHtml = isActuallyPlaying ? `
            <div class="equalizer-overlay">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        ` : '';

        html += `
            <li class="song-item">
                <div class="thumbnail-container">
                    <img src="https://img.youtube.com/vi/${song.id}/mqdefault.jpg" class="video-thumbnail" alt="thumbnail">
                    ${equalizerHtml}
                </div>
                <div class="video-info">
                    <div class="video-title">${song.title}</div>
                    <div class="video-id">${song.id}</div>
                </div>
                <div class="song-status ${statusClass}">
                    ${displayStatus}
                </div>
                <button class="remove-song-btn" onclick="confirmRemoveSong(this, '${song.id}', '${song.title.replace(/'/g, "\\'").replace(/"/g, "&quot;")}')" title="Remove Song" ${(isSinging || isDone) ? 'disabled' : ''}>
                    <span class="btn-text">×</span>
                    <span class="btn-spinner" style="display: none;">⌛</span>
                </button>
            </li>
        `;
    });
    songListContainer.innerHTML = html;
}

function showCollisionModal(singerName, singerId, videoId) {
    if (!collisionModal || !collisionText || !collisionYes || !collisionNo) {
        window.location.reload();
        return;
    }

    collisionText.innerHTML = `<strong>${singerName}</strong> also requested this title.<br><br>Would you like to sing with them?`;
    collisionModal.classList.add('active');

    collisionYes.onclick = async () => {
        try {
            await fetch('api/guest_join', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ targetGuestId: singerId, videoId: videoId })
            });
        } catch (e) { console.error("[COLLISION] Join API Error:", e); }
        // Reset lastDashboardData to force re-render on next poll
        lastDashboardData = null;
        lastPlaylistData = null;
        fetchDashboardData();
        collisionModal.classList.remove('active');
    };

    collisionNo.onclick = async () => {
        try {
            await fetch('api/guest_remove_song', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ videoId: videoId })
            });
        } catch (e) { console.error("[COLLISION] Remove API Error:", e); }
        // Reset lastDashboardData to force re-render on next poll
        lastDashboardData = null;
        lastPlaylistData = null;
        fetchDashboardData();
        collisionModal.classList.remove('active');
    };
}

function showKaraokeConfirmModal(title, url, failedWord, filterType) {
    if (!karaokeModal || !karaokeText || !karaokeYes || !karaokeNo) return;

    let warningMessage = "";
    if (filterType === 'must_have') {
        warningMessage = `This video doesn't have the word <strong style="color: #9965f4;">${failedWord}</strong>.`;
    } else if (filterType === 'must_not_have') {
        warningMessage = `This video has the word <strong style="color: #9965f4;">${failedWord}</strong>.`;
    } else {
        warningMessage = `This video doesn't match the word filter settings.`;
    }

    karaokeText.innerHTML = `You are adding:<br><strong>"${title}"</strong><br><br>${warningMessage}<br>Are you sure you want to add it anyway?`;
    karaokeModal.classList.add('active');

    karaokeYes.onclick = async () => {
        karaokeModal.classList.remove('active');
        await addConfirmedSong(url);
    };

    karaokeNo.onclick = () => {
        karaokeModal.classList.remove('active');
        urlInput.value = '';
        msgDiv.textContent = "";
    };
}

let isShowingModal = false;
let lastDashboardData = null;

async function fetchDashboardData() {
    if (isShowingModal) return;

    try {
        const response = await fetch('api/guest_dashboard_data');
        const data = await response.json();
        
        const dataString = JSON.stringify(data);
        if (lastDashboardData === dataString) {
            if (fullPlaylistVisible) {
                fetchFullPlaylist();
            }
            return;
        }
        lastDashboardData = dataString;

        if (data.songs) {
            window.guestSongs = data.songs;
            renderSongs();
        }

        if (fullPlaylistVisible) {
            fetchFullPlaylist();
        }
        
        if (Array.isArray(data.notifications)) {
            window.activeNotifications = data.notifications;
            checkNotifications();
        }
    } catch (e) { console.error("[GUEST] Polling Error:", e); }
}

function checkNotifications() {
    if (window.activeNotifications && window.activeNotifications.length > 0 && !isShowingModal) {
        const notif = window.activeNotifications[0];
        if (notif.type === 'join_sing') {
            showSocialModal(notif);
        }
    }
}

function showSocialModal(notif) {
    if (!socialNotifModal || !socialNotifText) return;
    isShowingModal = true;

    const songName = notif.songTitle || "your song";
    socialNotifText.innerHTML = `🌟 <strong>${notif.from_name}</strong> wants to sing <br>"${songName}"<br> with you!`;
    socialNotifModal.classList.add('active');

    socialNotifOk.onclick = async () => {
        socialNotifModal.classList.remove('active');
        isShowingModal = false;
        try {
            await fetch('api/guest_dismiss_notification', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notificationId: notif.id })
            });
        } catch (e) { console.error("[SOCIAL] Dismiss API Error:", e); }

        window.activeNotifications = window.activeNotifications.filter(n => n.id !== notif.id);

        if (window.activeNotifications.length > 0) {
            setTimeout(() => checkNotifications(), 300);
        } else {
            // Reset lastDashboardData to force re-render on next poll
            lastDashboardData = null;
            lastPlaylistData = null;
            fetchDashboardData();
        }
    };
}

async function confirmRemoveSong(btn, videoId, title) {
    if (confirm(`Remove "${title}" from your list?`)) {
        const btnText = btn.querySelector('.btn-text');
        const btnSpinner = btn.querySelector('.btn-spinner');

        btn.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnSpinner) btnSpinner.style.display = 'inline-block';

        try {
            const res = await fetch('api/guest_remove_song', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ videoId: videoId })
            });
            const data = await res.json();
            if (data.success) {
                // Remove from DOM immediately
                const songItem = btn.closest('.song-item');
                if (songItem) {
                    songItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    songItem.style.opacity = '0';
                    songItem.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        songItem.remove();
                        // Reset lastDashboardData to force re-render on next poll
                        lastDashboardData = null;
                        // Update global array if exists
                        if (window.guestSongs) {
                            window.guestSongs = window.guestSongs.filter(s => s.id !== videoId);
                        }
                        // If queue is empty, show the empty message
                        const songListContainerInner = document.getElementById('guest-songs');
                        if (songListContainerInner && songListContainerInner.children.length === 0) {
                            songListContainerInner.innerHTML = `
                                <li class="empty-queue-msg">
                                    You haven't requested any songs yet.<br>
                                    Add a YouTube URL above to join the fun!
                                </li>
                            `;
                        }
                    }, 300);
                } else {
                    window.location.reload();
                }
            } else {
                alert(data.error || 'Failed to remove song');
                btn.disabled = false;
                if (btnText) btnText.style.display = 'inline-block';
                if (btnSpinner) btnSpinner.style.display = 'none';
            }
        } catch (err) {
            console.error('Remove error:', err);
            btn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';
        }
    }
}

// Global exposure for specific handlers
window.confirmRemoveSong = confirmRemoveSong;

// Initialization
checkNotifications();
setInterval(fetchDashboardData, 5000);
