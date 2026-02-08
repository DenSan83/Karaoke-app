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
const reorderBtn = document.getElementById('reorder-btn');
const songList = document.getElementById('guest-songs');

let reorderMode = false;
let draggedItemIndex = null;

// guestSongs and activeNotifications are expected to be defined globally in the view
// let guestSongs = ...
// let activeNotifications = ...

requestBtn.addEventListener('click', async () => {
    const url = urlInput.value.trim();
    if (!url) {
        msgDiv.textContent = "Please enter a URL";
        msgDiv.className = 'request-status-msg error';
        return;
    }

    msgDiv.textContent = "Adding to your list...";
    msgDiv.className = 'request-status-msg muted';
    requestBtn.disabled = true;

    try {
        const res = await fetch('api/guest_add_song', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                song: { url: url }
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
                setTimeout(() => window.location.reload(), 1000);
            }
        } else {
            msgDiv.textContent = data.error || "Failed to add song";
            msgDiv.className = 'request-status-msg error';
            requestBtn.disabled = false;
        }
    } catch (err) {
        console.error("Add Song Error:", err);
        msgDiv.textContent = "Error: " + err.message;
        msgDiv.className = 'request-status-msg error';
        requestBtn.disabled = false;
    }
});

// Enter key support
urlInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') requestBtn.click();
});

reorderBtn.addEventListener('click', () => {
    reorderMode = !reorderMode;
    reorderBtn.classList.toggle('active');
    reorderBtn.textContent = reorderMode ? 'Done' : 'Reorder';

    requestBtn.disabled = reorderMode;
    urlInput.disabled = reorderMode;

    renderSongs();

    if (!reorderMode) {
        saveOrder();
    }
});

function renderSongs() {
    if (!window.guestSongs || window.guestSongs.length === 0) return;

    songList.innerHTML = '';
    window.guestSongs.forEach((song, index) => {
        const li = document.createElement('li');
        li.className = 'song-item';
        if (reorderMode) {
            li.draggable = true;
            li.addEventListener('dragstart', () => {
                draggedItemIndex = index;
                li.classList.add('dragging');
            });
            li.addEventListener('dragend', () => {
                li.classList.remove('dragging');
            });

            li.addEventListener('touchstart', (e) => {
                draggedItemIndex = index;
                li.classList.add('dragging');
            }, { passive: true });
            li.addEventListener('touchend', (e) => {
                li.classList.remove('dragging');
                finalizeReorder();
            });
        }

        li.innerHTML = `
            <img src="https://img.youtube.com/vi/${song.id}/mqdefault.jpg" class="video-thumbnail" alt="thumbnail">
            <div class="video-info">
                <div class="video-title">${song.title}</div>
                <div class="video-id">${song.id}</div>
            </div>
            <div class="song-status pending"></div>
            <button class="remove-song-btn" onclick="confirmRemoveSong('${song.id}', '${song.title.replace(/'/g, "\\'")}')" title="Remove Song">×</button>
        `;
        songList.appendChild(li);
    });
}

// Shared Reorder Logic
songList.addEventListener('dragover', (e) => {
    if (!reorderMode || draggedItemIndex === null) return;
    e.preventDefault();
    const placeholder = getPlaceholder();
    const afterElement = getDragAfterElement(songList, e.clientY);
    if (afterElement == null) {
        songList.appendChild(placeholder);
    } else {
        songList.insertBefore(placeholder, afterElement);
    }
});

songList.addEventListener('drop', (e) => {
    if (!reorderMode || draggedItemIndex === null) return;
    e.preventDefault();
    finalizeReorder();
});

songList.addEventListener('touchmove', (e) => {
    if (!reorderMode || draggedItemIndex === null) return;
    const touch = e.touches[0];
    const placeholder = getPlaceholder();
    const afterElement = getDragAfterElement(songList, touch.clientY);
    if (afterElement == null) {
        songList.appendChild(placeholder);
    } else {
        songList.insertBefore(placeholder, afterElement);
    }
}, { passive: true });

function getPlaceholder() {
    let p = document.querySelector('.drag-placeholder');
    if (!p) {
        p = document.createElement('li');
        p.className = 'drag-placeholder';
    }
    return p;
}

function getDragAfterElement(container, y) {
    const draggables = [...container.querySelectorAll('.song-item:not(.dragging)')];
    return draggables.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function finalizeReorder() {
    const placeholder = document.querySelector('.drag-placeholder');
    if (!placeholder || draggedItemIndex === null) {
        if (placeholder) placeholder.remove();
        return;
    }

    const allItems = Array.from(songList.children);
    const targetIndex = allItems.indexOf(placeholder);

    let finalIndex = targetIndex;
    if (targetIndex > draggedItemIndex) finalIndex--;

    if (draggedItemIndex !== finalIndex) {
        const item = window.guestSongs.splice(draggedItemIndex, 1)[0];
        window.guestSongs.splice(finalIndex, 0, item);
        renderSongs();
    } else {
        placeholder.remove();
    }
    draggedItemIndex = null;
}

async function saveOrder() {
    try {
        const res = await fetch('api/guest_reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ songs: window.guestSongs })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Failed to save order');
        }
    } catch (err) {
        console.error('Save order error:', err);
    }
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
        window.location.reload();
    };

    collisionNo.onclick = async () => {
        try {
            await fetch('api/guest_remove_song', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ videoId: videoId })
            });
        } catch (e) { console.error("[COLLISION] Remove API Error:", e); }
        window.location.reload();
    };
}

let isShowingModal = false;

async function fetchNewNotifications() {
    if (isShowingModal) return;

    try {
        const response = await fetch('api/guest_notifications');
        const notifications = await response.json();
        if (Array.isArray(notifications) && notifications.length > 0) {
            window.activeNotifications = notifications;
            checkNotifications();
        }
    } catch (e) { console.error("[SOCIAL] Polling Error:", e); }
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
            window.location.reload();
        }
    };
}

async function confirmRemoveSong(videoId, title) {
    if (confirm(`Remove "${title}" from your list?`)) {
        try {
            const res = await fetch('api/guest_remove_song', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ videoId: videoId })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            }
        } catch (err) {
            console.error('Remove error:', err);
        }
    }
}

// Global exposure for specific handlers
window.confirmRemoveSong = confirmRemoveSong;

// Initialization
checkNotifications();
setInterval(fetchNewNotifications, 5000);
