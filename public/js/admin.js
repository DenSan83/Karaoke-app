document.addEventListener('DOMContentLoaded', () => {
    // Hamburger Menu Logic
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const navbarControls = document.querySelector('.navbar-controls');

    if (hamburgerBtn && navbarControls) {
        hamburgerBtn.addEventListener('click', () => {
            navbarControls.classList.toggle('active');
        });

        // Close menu when a control button is clicked on mobile
        const controlButtons = navbarControls.querySelectorAll('.nav-btn');
        controlButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    navbarControls.classList.remove('active');
                }
            });
        });
    }

    const addBtn = document.getElementById('addBtn');
    const videoUrlInput = document.getElementById('videoUrl');
    const messageDiv = document.getElementById('message');
    const playlistList = document.getElementById('playlist-items');

    // Modal Elements
    const modal = document.getElementById('addVideoModal');
    const openModalBtn = document.getElementById('openAddModalBtn');
    const closeModalBtn = document.querySelector('.close-modal');

    // Modal Logic
    if (openModalBtn) {
        openModalBtn.addEventListener('click', () => {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.add('visible');
                videoUrlInput.focus();
            }, 10);
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            closeModal();
        });
    }

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    function closeModal() {
        modal.classList.remove('visible');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    let currentActiveIndex = -1;
    let currentActiveVideoId = null;
    let reorderMode = false;
    let draggedItemIndex = null;

    // Remote Controls
    const playBtn = document.getElementById('playBtn');
    const pauseBtn = document.getElementById('pauseBtn');
    const nextBtn = document.getElementById('nextBtn');
    const restartBtn = document.getElementById('restartBtn');

    if (playBtn) playBtn.addEventListener('click', () => sendCommand('play'));
    if (pauseBtn) pauseBtn.addEventListener('click', () => sendCommand('pause'));
    if (nextBtn) nextBtn.addEventListener('click', () => sendCommand('next'));
    if (restartBtn) restartBtn.addEventListener('click', () => sendCommand('restart'));

    function sendCommand(command, payload = {}) {
        fetch('api/send_command', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ command: command, payload: payload })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    console.log(`Command ${command} sent.`);
                    showMessage(`Command ${command} sent.`, 'success');
                } else {
                    showMessage('Failed to send command', 'error');
                }
            })
            .catch(err => console.error("Command error", err));
    }

    function renderPlaylist(playlist) {
        playlistList.innerHTML = '';
        if (playlist.length === 0) {
            playlistList.innerHTML = '<li class="empty-queue-item">Queue is empty</li>';
            return;
        }

        playlist.forEach((video, index) => {
            const li = document.createElement('li');
            li.className = 'playlist-item';

            if (reorderMode) {
                li.setAttribute('draggable', true);
                li.classList.add('reorder-active-item');

                li.addEventListener('dragstart', (e) => {
                    draggedItemIndex = index;
                    e.dataTransfer.effectAllowed = 'move';
                    setTimeout(() => li.classList.add('dragging'), 0);
                });

                li.addEventListener('dragend', () => {
                    li.classList.remove('dragging');
                    draggedItemIndex = null;
                    removePlaceholder();
                });

                // Touch Support for Mobile
                li.addEventListener('touchstart', (e) => {
                    draggedItemIndex = index;
                    li.classList.add('dragging');
                    // Add placeholder immediately
                    const placeholder = getPlaceholder();
                    li.parentNode.insertBefore(placeholder, li);
                }, { passive: true });

                li.addEventListener('touchmove', (e) => {
                    if (draggedItemIndex !== null) {
                        e.preventDefault();
                        const touch = e.touches[0];
                        const placeholder = getPlaceholder();
                        const afterElement = getDragAfterElement(playlistList, touch.clientY);
                        if (afterElement == null) {
                            playlistList.appendChild(placeholder);
                        } else {
                            playlistList.insertBefore(placeholder, afterElement);
                        }
                    }
                }, { passive: false });

                li.addEventListener('touchend', (e) => {
                    li.classList.remove('dragging');
                    finalizeReorder();
                    draggedItemIndex = null;
                });
            }

            if (video.id === currentActiveVideoId) {
                li.classList.add('active-track');
            }

            const thumbImg = document.createElement('img');
            thumbImg.src = `https://img.youtube.com/vi/${video.id}/mqdefault.jpg`;
            thumbImg.className = 'video-thumbnail';
            thumbImg.alt = 'Thumbnail';
            li.appendChild(thumbImg);

            const infoDiv = document.createElement('div');
            infoDiv.className = 'video-info';

            const titleSpan = document.createElement('span');
            titleSpan.className = 'video-title';
            titleSpan.textContent = `${index + 1}. ${video.title}`;
            infoDiv.appendChild(titleSpan);

            if (video.downloading) {
                const badge = document.createElement('span');
                badge.textContent = ' ⏳';
                badge.title = 'Downloading...';
                badge.classList.add('status-badge');
                infoDiv.appendChild(badge);
            } else if (video.local_file) {
                const badge = document.createElement('span');
                badge.textContent = ' 💾';
                badge.title = 'Saved locally';
                badge.classList.add('status-badge');
                infoDiv.appendChild(badge);
            }

            if (video.downloading) {
                const progressBarContainer = document.createElement('div');
                progressBarContainer.className = 'download-progress-container';
                progressBarContainer.id = `progress-${video.id}`;

                const progressBarFill = document.createElement('div');
                progressBarFill.className = 'download-progress-fill';
                progressBarFill.style.width = '0%';

                progressBarContainer.appendChild(progressBarFill);
                li.appendChild(progressBarContainer);
            }

            const idDiv = document.createElement('div');
            idDiv.className = 'video-id';
            idDiv.textContent = `${video.user ? video.user : 'Unknown'} - ${video.id}`;
            infoDiv.appendChild(idDiv);

            const timeDiv = document.createElement('div');
            timeDiv.className = 'video-time';
            timeDiv.style.fontSize = '0.8rem';
            timeDiv.style.color = '#888';

            const addedAt = video.added_at ? parseInt(video.added_at) : Date.now() / 1000;
            timeDiv.textContent = getTimeAgo(addedAt);
            infoDiv.appendChild(timeDiv);

            li.appendChild(infoDiv);

            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'actions';

            const localPlayBtn = document.createElement('button');
            localPlayBtn.className = 'icon-btn play-icon';
            localPlayBtn.textContent = '▶';
            localPlayBtn.title = 'Play Now';
            localPlayBtn.onclick = () => jumpToVideo(index);
            actionsDiv.appendChild(localPlayBtn);

            const removeBtn = document.createElement('button');
            removeBtn.className = 'icon-btn remove-icon';
            removeBtn.textContent = '🗑';
            removeBtn.title = 'Remove';
            removeBtn.onclick = () => removeVideo(index);
            actionsDiv.appendChild(removeBtn);

            li.appendChild(actionsDiv);
            playlistList.appendChild(li);
        });
    }

    function fetchPlaylist() {
        fetch('api/get_playlist')
            .then(response => response.json())
            .then(data => {
                localStorage.setItem('currentPlaylist', JSON.stringify(data));
                renderPlaylist(data);
            })
            .catch(error => console.error('Error fetching playlist:', error));
    }

    function removeVideo(index) {
        if (!confirm('Remove this video?')) return;

        fetch('api/remove_video', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ index: index })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    fetchPlaylist();
                } else {
                    showMessage('Failed to remove video', 'error');
                }
            });
    }

    function jumpToVideo(index) {
        sendCommand('jump', { index: index });
    }

    function addVideo() {
        const url = videoUrlInput.value.trim();
        const userNameInput = document.getElementById('userName');
        const user = userNameInput ? userNameInput.value.trim() : '';
        const videoId = extractVideoID(url);

        if (!user) {
            showMessage('User name is required', 'error');
            return;
        }

        if (!videoId) {
            showMessage('Invalid YouTube URL', 'error');
            return;
        }

        closeModal();
        videoUrlInput.value = '';
        if (userNameInput) userNameInput.value = ''; // Clear name input too
        showMessage('Adding video to queue...', 'success');

        fetch('api/add_video', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url, user: user })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fetchPlaylist();
                } else {
                    showMessage(data.error || 'Failed to add video', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred', 'error');
            });
    }

    function showMessage(msg, type) {
        messageDiv.textContent = msg;
        messageDiv.className = type;
        messageDiv.classList.add('show');
        setTimeout(() => {
            messageDiv.classList.remove('show');
        }, 3000);
    }

    function extractVideoID(url) {
        const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
        const match = url.match(regExp);
        return (match && match[2].length === 11) ? match[2] : null;
    }

    function updateDownloadProgress() {
        const playlist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');
        playlist.forEach(video => {
            if (video.downloading) {
                fetch(`api/download_progress?id=${video.id}`)
                    .then(res => res.json())
                    .then(data => {
                        const progressContainer = document.getElementById(`progress-${video.id}`);
                        if (progressContainer && data.progress) {
                            const progressFill = progressContainer.querySelector('.download-progress-fill');
                            if (progressFill) {
                                const percent = parseFloat(data.progress);
                                progressFill.style.width = percent + '%';
                            }
                        }
                    })
                    .catch(e => console.error('Progress fetch error:', e));
            }
        });
    }

    // Interval timers
    setInterval(() => {
        if (!reorderMode) {
            fetchPlaylist();
        }
    }, 5000);
    setInterval(updateDownloadProgress, 2000);

    // Initial load
    fetchPlaylist();

    // Event listeners
    addBtn.addEventListener('click', addVideo);
    videoUrlInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addVideo();
        }
    });

    // Player status polling for highlighting
    setInterval(async () => {
        try {
            const res = await fetch('api/get_status');
            const status = await res.json();
            if (status.current_index !== undefined) {
                currentActiveIndex = parseInt(status.current_index);
                currentActiveVideoId = status.videoId || null;
                updateActiveTrackHighlight();
            }
        } catch (e) {
            console.error(e);
        }
    }, 1000);

    function updateActiveTrackHighlight() {
        const items = document.querySelectorAll('#playlist-items li');
        const playlist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');

        items.forEach((item, index) => {
            const video = playlist[index];
            if (video && video.id === currentActiveVideoId) {
                item.classList.add('active-track');
            } else {
                item.classList.remove('active-track');
            }
        });
    }

    // Expose functions globally for onclick handlers
    window.removeVideo = removeVideo;
    window.jumpToVideo = jumpToVideo;

    // Logout Confirmation
    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        logoutLink.addEventListener('click', (e) => {
            if (!confirm('Are you sure you want to log out?')) {
                e.preventDefault();
            }
        });
    }

    // Global Reorder Listeners (Desktop)
    playlistList.addEventListener('dragover', (e) => {
        if (!reorderMode || draggedItemIndex === null) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const placeholder = getPlaceholder();
        const afterElement = getDragAfterElement(playlistList, e.clientY);
        if (afterElement == null) {
            playlistList.appendChild(placeholder);
        } else {
            playlistList.insertBefore(placeholder, afterElement);
        }
    });

    playlistList.addEventListener('drop', (e) => {
        if (!reorderMode || draggedItemIndex === null) return;
        e.preventDefault();
        finalizeReorder();
    });

    // Reorder Logic
    const reorderBtn = document.getElementById('reorderBtn');
    if (reorderBtn) {
        reorderBtn.addEventListener('click', () => {
            reorderMode = !reorderMode;
            reorderBtn.classList.toggle('active');

            // Disable/Enable playback controls and Add button
            const controls = [playBtn, pauseBtn, nextBtn, restartBtn, openModalBtn];
            controls.forEach(btn => {
                if (btn) btn.disabled = reorderMode;
            });

            if (reorderMode) {
                showMessage('Reorder mode enabled. Drag items to rearrange.', 'success');
            } else {
                savePlaylistOrder();
                showMessage('Reorder mode disabled. Saving changes...', 'success');
            }

            // Re-render to apply/remove draggable attributes
            const currentPlaylist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');
            renderPlaylist(currentPlaylist);
        });
    }

    function savePlaylistOrder() {
        const currentPlaylist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');
        fetch('api/reorder_playlist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ playlist: currentPlaylist })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showMessage(data.error || 'Failed to save new order', 'error');
                }
            })
            .catch(err => console.error('Reorder error:', err));
    }

    // Helper functions for Reorder UI Feedback
    function getPlaceholder() {
        let placeholder = document.querySelector('.drag-placeholder');
        if (!placeholder) {
            placeholder = document.createElement('li');
            placeholder.className = 'drag-placeholder';
        }
        return placeholder;
    }

    function removePlaceholder() {
        const placeholder = document.querySelector('.drag-placeholder');
        if (placeholder) placeholder.remove();
    }

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.playlist-item:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
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
            removePlaceholder();
            return;
        }

        const allItemsWithPlaceholder = Array.from(playlistList.children);
        const targetIndex = allItemsWithPlaceholder.indexOf(placeholder);

        // Correcting index if placeholder is AFTER the original position
        let finalIndex = targetIndex;
        if (targetIndex > draggedItemIndex) {
            finalIndex--;
        }

        if (draggedItemIndex !== finalIndex) {
            const currentPlaylist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');
            const itemToMove = currentPlaylist.splice(draggedItemIndex, 1)[0];
            currentPlaylist.splice(finalIndex, 0, itemToMove);
            localStorage.setItem('currentPlaylist', JSON.stringify(currentPlaylist));
            renderPlaylist(currentPlaylist);
        } else {
            // Even if index is same, we must clean up placeholder
            removePlaceholder();
        }
        draggedItemIndex = null;
    }

    // Options Submenu Logic
    const optionsBtn = document.getElementById('optionsBtn');
    const optionsSubmenu = document.getElementById('optionsSubmenu');

    if (optionsBtn && optionsSubmenu) {
        optionsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            optionsSubmenu.classList.toggle('hidden');
        });

        // Close submenu when clicking an item
        const submenuItems = optionsSubmenu.querySelectorAll('.submenu-item');
        submenuItems.forEach(item => {
            item.addEventListener('click', () => {
                optionsSubmenu.classList.add('hidden');
            });
        });

        // Close submenu when clicking elsewhere
        window.addEventListener('click', () => {
            if (!optionsSubmenu.classList.contains('hidden')) {
                optionsSubmenu.classList.add('hidden');
            }
        });
    }

    function getTimeAgo(timestamp) {
        const now = Math.floor(Date.now() / 1000);
        const diff = now - timestamp;

        if (diff < 60) {
            return "Now";
        } else if (diff < 120) {
            return "One minute ago";
        } else {
            const minutes = Math.floor(diff / 60);
            return `${minutes} minutes ago`;
        }
    }
});
