document.addEventListener('DOMContentLoaded', () => {
    // Hamburger Menu Logic
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const navbarControls = document.querySelector('.navbar-controls');

    if (hamburgerBtn && navbarControls) {
        hamburgerBtn.addEventListener('click', () => {
            navbarControls.classList.toggle('active');
            hamburgerBtn.classList.toggle('active');
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

    // Pair to Screen Modal Elements
    const pairScreenModal = document.getElementById('pairScreenModal');
    const pairScreenBtn = document.getElementById('pairScreenBtn');
    const closePairScreenModal = document.getElementById('closePairScreenModal');
    const pairScreenSubmitBtn = document.getElementById('pairScreenSubmitBtn');
    const screenCodeInput = document.getElementById('screenCodeInput');

    const openPairScreenModal = () => {
        pairScreenModal.classList.remove('hidden');
        setTimeout(() => {
            pairScreenModal.classList.add('visible');
            screenCodeInput.value = '';
            screenCodeInput.focus();
        }, 10);
    };

    const closePairScreen = () => {
        pairScreenModal.classList.remove('visible');
        setTimeout(() => pairScreenModal.classList.add('hidden'), 300);
    };

    if (pairScreenBtn) pairScreenBtn.addEventListener('click', openPairScreenModal);
    if (closePairScreenModal) closePairScreenModal.addEventListener('click', closePairScreen);

    window.addEventListener('click', (e) => {
        if (e.target === pairScreenModal) closePairScreen();
    });

    if (pairScreenSubmitBtn) {
        pairScreenSubmitBtn.addEventListener('click', async () => {
            const code = (screenCodeInput.value || '').trim();
            if (!/^\d{6}$/.test(code)) {
                showMessage('Please enter a valid 6-digit code.', 'error');
                return;
            }
            const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/pair_screen';
            try {
                const resp = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });
                const data = await resp.json();
                if (data.success) {
                    showMessage('Screen paired successfully!', 'success');
                    closePairScreen();
                } else {
                    showMessage(data.error || 'Failed to pair screen.', 'error');
                }
            } catch (e) {
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }

    // List and Clean Modal Elements
    const listCleanModal = document.getElementById('listCleanModal');
    const listCleanBtn = document.getElementById('listCleanBtn');
    const closeListCleanModal = document.getElementById('closeListCleanModal');
    const cancelListCleanBtn = document.getElementById('cancelListCleanBtn');
    const executeBtn = document.getElementById('executeBtn');
    const downloadListCheckbox = document.getElementById('downloadList');
    const cleanListCheckbox = document.getElementById('cleanList');

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

    // List and Clean Modal Logic
    if (listCleanBtn) {
        listCleanBtn.addEventListener('click', () => {
            listCleanModal.classList.remove('hidden');
            setTimeout(() => {
                listCleanModal.classList.add('visible');
            }, 10);
        });
    }

    const closeListClean = () => {
        listCleanModal.classList.remove('visible');
        setTimeout(() => {
            listCleanModal.classList.add('hidden');
        }, 300);
    };

    if (closeListCleanModal) closeListCleanModal.addEventListener('click', closeListClean);
    if (cancelListCleanBtn) cancelListCleanBtn.addEventListener('click', closeListClean);

    window.addEventListener('click', (e) => {
        if (e.target === listCleanModal) {
            closeListClean();
        }
    });

    if (executeBtn) {
        executeBtn.addEventListener('click', () => {
            const download = downloadListCheckbox.checked;
            const clean = cleanListCheckbox.checked;

            if (!download && !clean) {
                showMessage('Please select at least one option', 'error');
                return;
            }

            if (clean) {
                if (!confirm('Are you sure you want to clear the whole list?')) {
                    return;
                }
            }

            const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/export_clean';
            
            if (download) {
                // To handle download, we can use a hidden form or just window.location
                const downloadUrl = `${apiUrl}?download=1${clean ? '&clean=1' : ''}`;
                
                // If we also need to clean, the server will handle both in one request
                // and we'll need to refresh the UI after the download starts.
                window.location.href = downloadUrl;
                
                if (clean) {
                    showMessage('List downloaded and cleared', 'success');
                    setTimeout(fetchPlaylist, 1000);
                } else {
                    showMessage('List download started', 'success');
                }
            } else if (clean) {
                // Just clean
                fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ clean: 1 })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showMessage('List cleared', 'success');
                        fetchPlaylist();
                    } else {
                        showMessage('Failed to clear list', 'error');
                    }
                });
            }

            closeListClean();
        });
    }

    // Word Filter Modal Logic
    const wordFilterModal = document.getElementById('wordFilterModal');
    const wordFilterBtn = document.getElementById('wordFilterBtn');
    const closeWordFilterModal = document.getElementById('closeWordFilterModal');
    const cancelWordFilterBtn = document.getElementById('cancelWordFilterBtn');
    const saveWordFilterBtn = document.getElementById('saveWordFilterBtn');
    const mustHaveWordsTextarea = document.getElementById('mustHaveWords');
    const mustNotHaveWordsTextarea = document.getElementById('mustNotHaveWords');

    if (wordFilterBtn) {
        wordFilterBtn.addEventListener('click', () => {
            // Load current values
            mustHaveWordsTextarea.value = (typeof window.MUST_HAVE_WORDS !== 'undefined' && window.MUST_HAVE_WORDS !== null) ? window.MUST_HAVE_WORDS : '';
            mustNotHaveWordsTextarea.value = (typeof window.MUST_NOT_HAVE_WORDS !== 'undefined' && window.MUST_NOT_HAVE_WORDS !== null) ? window.MUST_NOT_HAVE_WORDS : '';

            wordFilterModal.classList.remove('hidden');
            setTimeout(() => {
                wordFilterModal.classList.add('visible');
                mustHaveWordsTextarea.focus();
            }, 10);
        });
    }

    const closeWordFilter = () => {
        wordFilterModal.classList.remove('visible');
        setTimeout(() => {
            wordFilterModal.classList.add('hidden');
        }, 300);
    };

    if (closeWordFilterModal) closeWordFilterModal.addEventListener('click', closeWordFilter);
    if (cancelWordFilterBtn) cancelWordFilterBtn.addEventListener('click', closeWordFilter);

    window.addEventListener('click', (e) => {
        if (e.target === wordFilterModal) {
            closeWordFilter();
        }
    });

    if (saveWordFilterBtn) {
        saveWordFilterBtn.addEventListener('click', async () => {
            const mustHaveWords = mustHaveWordsTextarea.value;
            const mustNotHaveWords = mustNotHaveWordsTextarea.value;

            const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/update_word_filter';
            try {
                const resp = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mustHaveWords, mustNotHaveWords })
                });
                const data = await resp.json();
                if (data.success) {
                    showMessage('Word filter updated successfully!', 'success');
                    // Update the global constants so if they reopen it's current
                    window.MUST_HAVE_WORDS = mustHaveWords;
                    window.MUST_NOT_HAVE_WORDS = mustNotHaveWords;
                    closeWordFilter();
                } else {
                    showMessage(data.error || 'Failed to update word filter.', 'error');
                }
            } catch (e) {
                showMessage('Network error. Please try again.', 'error');
            }
        });
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
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/send_command';
        fetch(apiUrl, {
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
                badge.id = `download-status-${video.id}`;
                badge.classList.add('status-badge');
                infoDiv.appendChild(badge);
            } else if (video.download_failed) {
                const badge = document.createElement('span');
                badge.textContent = ' ❌';
                badge.title = video.download_error
                    ? `Download failed: ${video.download_error}`
                    : 'Download failed';
                badge.classList.add('status-badge', 'failed-badge');
                infoDiv.appendChild(badge);
            } else if (video.local_path) {
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
            if (reorderMode) {
                localPlayBtn.disabled = true;
            } else {
                localPlayBtn.onclick = () => jumpToVideo(index);
            }
            actionsDiv.appendChild(localPlayBtn);

            if (video.download_failed) {
                const retryBtn = document.createElement('button');
                retryBtn.className = 'icon-btn retry-icon';
                retryBtn.textContent = '↻';
                retryBtn.title = 'Retry download';
                if (reorderMode) {
                    retryBtn.disabled = true;
                } else {
                    retryBtn.onclick = () => retryDownload(video.id);
                }
                actionsDiv.appendChild(retryBtn);
            }

            const removeBtn = document.createElement('button');
            removeBtn.className = 'icon-btn remove-icon';
            removeBtn.textContent = '🗑';
            removeBtn.title = 'Remove';
            if (reorderMode) {
                removeBtn.disabled = true;
            } else {
                removeBtn.onclick = () => removeVideo(index);
            }
            actionsDiv.appendChild(removeBtn);

            li.appendChild(actionsDiv);
            playlistList.appendChild(li);
        });
    }

    function fetchPlaylist() {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/get_playlist';
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                localStorage.setItem('currentPlaylist', JSON.stringify(data));
                renderPlaylist(data);
            })
            .catch(error => console.error('Error fetching playlist:', error));
    }

    function removeVideo(index) {
        if (!confirm('Remove this video?')) return;

        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/remove_video';
        fetch(apiUrl, {
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

    function retryDownload(videoId) {
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/retry_download';
        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ videoId: videoId })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.already_downloaded ? 'Already downloaded' : 'Download restarted', 'success');
                } else {
                    showMessage(data.error || 'Could not restart the download', 'error');
                }
                fetchPlaylist();
            })
            .catch(error => {
                console.error('Retry error:', error);
                showMessage('Could not restart the download', 'error');
            });
    }

    function jumpToVideo(index) {
        sendCommand('jump', { index: index });
    }

    function addVideo() {
        const url = videoUrlInput.value.trim();
        const userNameInput = document.getElementById('userName');
        const user = userNameInput ? userNameInput.value.trim() : '';

        if (!user) {
            showMessage('User name is required', 'error');
            return;
        }

        if (!url) {
            showMessage('Please enter a URL', 'error');
            return;
        }

        closeModal();
        videoUrlInput.value = '';
        if (userNameInput) userNameInput.value = '';
        showMessage('Adding to queue...', 'success');

        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/add_video';
        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url, user: user })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.is_playlist) {
                        // Playlist response
                        const msg = data.total_in_playlist > 20
                            ? `Added first 20 of ${data.total_in_playlist} videos from playlist`
                            : `Added ${data.added_count} videos from playlist`;
                        showMessage(msg, 'success');
                    } else {
                        // Single video response
                        showMessage('Video added to queue', 'success');
                    }
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
                const progressApiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + `api/download_progress?id=${video.id}`;
                fetch(progressApiUrl)
                    .then(res => res.json())
                    .then(data => {
                        const statusBadge = document.getElementById(`download-status-${video.id}`);
                        if (statusBadge) {
                            const isAdaptive = data.status === 'adaptive';
                            statusBadge.textContent = isAdaptive ? ' ⏳⏳' : ' ⏳';
                            statusBadge.title = isAdaptive
                                ? 'Downloading separate video and audio streams...'
                                : 'Downloading...';
                        }
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
            const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/get_status';
            const res = await fetch(apiUrl);
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

    // Requests badge polling
    const updateRequestsBadge = async () => {
        try {
            const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/get_requests';
            const res = await fetch(apiUrl);
            const requests = await res.json();
            const badge = document.getElementById('requestsBadge');
            if (badge) {
                const count = requests.length;
                badge.textContent = count;
                if (count > 0) {
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        } catch (e) {
            console.error('Error fetching requests:', e);
        }
    };
    setInterval(updateRequestsBadge, 3000);
    updateRequestsBadge();

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

            // Disable/Enable playback controls, Add button and Options button
            const controls = [playBtn, pauseBtn, nextBtn, restartBtn, openModalBtn, optionsBtn];
            controls.forEach(btn => {
                if (btn) btn.disabled = reorderMode;
            });

            if (reorderMode && optionsSubmenu) {
                optionsSubmenu.classList.add('hidden');
            }

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
        const apiUrl = (typeof BASE_PATH !== 'undefined' ? BASE_PATH + '/' : '') + 'api/reorder_playlist';
        fetch(apiUrl, {
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
            if (reorderMode) return;
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
