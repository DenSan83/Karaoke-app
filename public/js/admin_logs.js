function openTab(tabName) {
    // Hide all tab content
    const tabContents = document.getElementsByClassName('tab-content');
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove('active');
    }

    // Deactivate all tab buttons
    const tabBtns = document.getElementsByClassName('tab-btn');
    for (let i = 0; i < tabBtns.length; i++) {
        tabBtns[i].classList.remove('active');
    }

    // Show the specific tab and activate the button
    document.getElementById(tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

async function deleteUser(guestId, name) {
    if (!confirm(`Are you sure you want to delete guest "${name}"? This will log them out immediately.`)) {
        return;
    }

    try {
        const response = await fetch('../api/delete_guest', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ guestId })
        });

        const result = await response.json();
        if (result.success) {
            location.reload(); // Refresh to update list
        } else {
            alert('Error: ' + result.error);
        }
    } catch (err) {
        console.error(err);
        alert('Failed to delete guest.');
    }
}

function downloadTrackList() {
    window.location.href = '../admin/logs/download_tracks';
}
