document.addEventListener('DOMContentLoaded', () => {
    const guestsContainer = document.getElementById('guests-container');
    const loadingMsg = document.querySelector('.no-requests'); // Re-use this for loading/empty state

    // Load requests on start
    fetchRequests();

    // Poll every 10 seconds
    setInterval(fetchRequests, 10000);

    function fetchRequests() {
        console.log('Fetching requests...');
        fetch('../api/get_requests')
            .then(response => response.json())
            .then(data => {
                renderRequests(data);
            })
            .catch(error => {
                console.error('Error fetching requests:', error);
                guestsContainer.innerHTML = '<p class="error">Error loading requests.</p>';
            });
    }

    function renderRequests(data) {
        guestsContainer.innerHTML = '';

        if (!data || data.length === 0) {
            guestsContainer.innerHTML = '<p class="no-requests">No pending requests at the moment.</p>';
            return;
        }

        const ul = document.createElement('ul');
        ul.className = 'playlist'; // Re-use admin playlist styles
        ul.id = 'playlist-items'; // Re-use admin playlist items styles

        data.forEach(item => {
            const li = document.createElement('li');
            li.className = 'playlist-item'; // Re-use admin list styles

            // 1. Thumbnail
            const thumbDiv = document.createElement('div');
            thumbDiv.className = 'video-thumb';
            const img = document.createElement('img');
            img.src = `https://img.youtube.com/vi/${item.video.id}/default.jpg`;
            img.alt = 'thumbnail';
            thumbDiv.appendChild(img);
            li.appendChild(thumbDiv);

            // 2. Info (Title, user, time)
            const infoDiv = document.createElement('div');
            infoDiv.className = 'video-info';

            const titleDiv = document.createElement('div');
            titleDiv.className = 'video-title';
            titleDiv.textContent = item.video.title;
            infoDiv.appendChild(titleDiv);

            const userDiv = document.createElement('div');
            userDiv.className = 'video-id'; // Using same class for styling consisteny
            userDiv.textContent = `${item.guest_name} - ${item.video.id}`;
            infoDiv.appendChild(userDiv);

            const timeDiv = document.createElement('div');
            timeDiv.className = 'video-time';
            timeDiv.style.fontSize = '0.8rem';
            timeDiv.style.color = '#888';
            timeDiv.textContent = getTimeAgo(item.timestamp);
            infoDiv.appendChild(timeDiv);

            li.appendChild(infoDiv);

            // 3. Actions (Accept / Refuse)
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'actions';

            // Accept Button (Check)
            const acceptBtn = document.createElement('button');
            acceptBtn.className = 'icon-btn play-icon'; // Re-using play-icon for Teal color
            acceptBtn.innerHTML = '✅';
            acceptBtn.title = 'Accept Request';
            acceptBtn.onclick = () => acceptRequest(item);
            actionsDiv.appendChild(acceptBtn);

            // Refuse Button (Cross)
            const refuseBtn = document.createElement('button');
            refuseBtn.className = 'icon-btn remove-icon'; // Re-using remove-icon for Red color
            refuseBtn.innerHTML = '❌';
            refuseBtn.title = 'Refuse Request';
            refuseBtn.onclick = () => confirmRefuse(item);
            actionsDiv.appendChild(refuseBtn);

            li.appendChild(actionsDiv);
            ul.appendChild(li);
        });

        guestsContainer.appendChild(ul);
    }

    function acceptRequest(item) {
        if (confirm(`Accept "${item.video.title}" from ${item.guest_name}?`)) {
            fetch('../api/add_video', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    url: `https://www.youtube.com/watch?v=${item.video.id}`,
                    user: item.guest_name
                })
            })
                .then(response => response.json())
                .then(result => {
                    if (result.error) {
                        alert('Error accepting request: ' + result.error);
                    } else {
                        // Refresh list
                        fetchRequests();
                    }
                })
                .catch(err => console.error(err));
        }
    }

    function confirmRefuse(item) {
        if (confirm(`Are you sure you want to REFUSE "${item.video.title}" from ${item.guest_name}?`)) {
            fetch('../api/refuse_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    guestId: item.guest_id,
                    videoId: item.video.id
                })
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        fetchRequests();
                    } else {
                        alert('Failed to refuse request');
                    }
                })
                .catch(err => console.error(err));
        }
    }

    // Helper for time ago
    function getTimeAgo(timestamp) {
        const now = Math.floor(Date.now() / 1000);
        const diff = now - timestamp;

        if (diff < 60) return "Now";
        if (diff < 120) return "One minute ago";
        const minutes = Math.floor(diff / 60);
        return `${minutes} minutes ago`;
    }
});
