<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary SEO -->
    <title>How to Host a Karaoke Party at Home | Montreal Karaoke App</title>
    <meta name="description" content="Step-by-step guide to hosting your own karaoke party in Montreal. Set up the screen, invite guests with a code or QR, manage the song queue and sing all night.">
    <meta name="keywords" content="karaoke Montreal, soirée karaoké Montréal, karaoke party at home, karaoké maison, how to host karaoke, karaoke app Montreal, karaoké application, fête karaoké, karaoke night Montreal, karaoke party guide">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://devdensan.com/en/howto">

    <!-- Open Graph (Facebook, LinkedIn, WhatsApp) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="How to Host a Karaoke Party at Home | Montreal Karaoke App">
    <meta property="og:description" content="Everything you need to run a karaoke night from home. Pair a screen, invite friends with a QR code, and manage the queue — all from your phone.">
    <meta property="og:url" content="https://devdensan.com/en/howto">
    <meta property="og:image" content="https://devdensan.com/public/media/karaoke_logo.png">
    <meta property="og:locale" content="en_CA">
    <meta property="og:site_name" content="Party Time Karaoke">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="How to Host a Karaoke Party at Home | Montreal Karaoke App">
    <meta name="twitter:description" content="Everything you need to run a karaoke night from home. Pair a screen, invite friends with a QR code, and manage the queue — all from your phone.">
    <meta name="twitter:image" content="https://devdensan.com/public/media/karaoke_logo.png">

    <!-- Structured Data (FAQ schema for search snippets) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "HowTo",
        "name": "How to Host a Karaoke Party at Home",
        "description": "Step-by-step guide to hosting your own karaoke party using the Party Time app in Montreal.",
        "step": [
            {
                "@type": "HowToStep",
                "position": 1,
                "name": "Log in as Admin",
                "text": "On your portable device, go to the login page and enter your Admin Username and 6-digit PIN."
            },
            {
                "@type": "HowToStep",
                "position": 2,
                "name": "Set Up the Party Screen",
                "text": "Open the /screen URL on your main display, note the Screen Code, and pair it from your Admin Dashboard."
            },
            {
                "@type": "HowToStep",
                "position": 3,
                "name": "Invite Your Friends",
                "text": "Share an invite code or QR code from Options > Access Codes so guests can join and request songs."
            },
            {
                "@type": "HowToStep",
                "position": 4,
                "name": "Manage Song Requests",
                "text": "Accept or refuse guest song requests from the Request section of your Admin Dashboard."
            },
            {
                "@type": "HowToStep",
                "position": 5,
                "name": "Control the Show",
                "text": "Play, pause, skip, reorder, or remove songs from the queue at any time."
            }
        ]
    }
    </script>

    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/howto.css">
</head>
<body>
    <div class="howto-page">
        <div class="howto-card">
            <div class="howto-logo">
                <img src="public/media/karaoke_logo.png" alt="Karaoke Logo">
                <span>Party Time</span>
            </div>
            <h1>How to Use Your Karaoke App</h1>
            <p class="howto-intro">Welcome! Your party has already been created? Yay! </p>
            <p class="howto-intro">Follow these steps to get the music started and gather your friends for a great karaoke night.</p>

            <div class="tabs">
                <button class="tab-btn active" data-tab="admin">For the Admin</button>
                <button class="tab-btn" data-tab="guest">For the Guests</button>
            </div>

            <!-- ADMIN TAB -->
            <div id="admin" class="tab-content active">
                <ol class="howto-steps">
                    <li>
                        <h2>Log in as Admin</h2>
                        <ul>
                            <li>On your portable device, go to the login page of the app.</li>
                            <li>Use your <strong>Admin Username</strong> and the <strong>6-digit PIN</strong> provided by the superadmin.</li>
                        </ul>
                    </li>

                    <li>
                        <h2>Set Up the "Party Screen"</h2>
                        <ul>
                            <li>On the device you want to use as the main karaoke screen (like a laptop connected to a big screen), open the <code>/screen</code> URL of the app.</li>
                            <li>You will see a 6-digit <strong>Screen Code</strong> displayed on that screen.</li>
                            <li>In your Admin Dashboard, go to <strong>Options &gt; Pair to Screen</strong> and enter that 6-digit code.</li>
                            <li>Once paired, the screen will show "Welcome to my Karaoke app!" and wait for songs to be added to the queue.</li>
                        </ul>
                    </li>

                    <li>
                        <h2>Invite Your Friends</h2>
                        <ul>
                            <li>To let your friends join the party, you need to give them an <strong>Invite Code</strong>.</li>
                            <li>In your Admin Dashboard, go to <strong>Options &gt; Access Codes</strong>.</li>
                            <li>Here you can see the existing code or generate a new one (e.g., "KARAOKE2026").</li>
                            <li>You can also enable a <strong>QR Code</strong> that friends can scan to join instantly.</li>
                            <li>Your friends should go to the app's main URL, enter the invite code, and then enter their name.</li>
                            <li>Now they're ready to make requests.</li>
                        </ul>
                    </li>

                    <li>
                        <h2>Manage the Requests</h2>
                        <ul>
                            <li>Go to <strong>Request</strong> and accept (or refuse) the guests' requests.</li>
                        </ul>
                    </li>

                    <li>
                        <h2>Manage the Show</h2>
                        <p>As the admin, you have full control over the playlist from your dashboard.</p>
                        <ul>
                            <li><strong>Play / Pause / Restart</strong> — Control the current song.</li>
                            <li><strong>Next Track</strong> — Move to the next singer in the queue.</li>
                            <li><strong>Reorder</strong> — Change the order of songs if someone is not ready to sing yet.</li>
                            <li><strong>Remove</strong> — If a song shouldn't be played, you can remove it from the queue.</li>
                        </ul>
                    </li>

                    <li>
                        <h2>Additional Options</h2>
                        <ul>
                            <li><strong>Word Filter</strong> — You can set "Must have" words (like "karaoke") or "Must not have" words to ensure only the right types of videos are added. Guests' requests will not be blocked, only warned. As an admin, you have the last word to accept or refuse.</li>
                            <li><strong>List and Clean</strong> — Select <strong>Download list</strong> if you want to keep a record of the existing queue. Select <strong>Clean list</strong> if you want to remove all songs from the queue. Click on <strong>Execute</strong>.</li>
                        </ul>
                    </li>
                </ol>
            </div>

            <!-- GUEST TAB -->
            <div id="guest" class="tab-content">
                <ol class="howto-steps">
                    <li>
                        <h2>Retrieve a Song</h2>
                        <ul>
                            <li>Find your favorite track on <strong>YouTube</strong>.</li>
                            <li>Click on the <strong>Share</strong> button and copy the <strong>URL</strong>.</li>
                            <li>Head back to the app's <a href="<?= htmlspecialchars($basePath) ?>/" class="howto-link">Welcome page</a>.</li>
                        </ul>
                    </li>

                    <li>
                        <h2>Request a Song</h2>
                        <ul>
                            <li>Insert the Access code given by the Admin, or scan the QR code to join the party.</li>
                            <li>Insert your Stage Name</li>
                            <li>Add your URL in the <strong>Request a Song</strong> field and hit the <strong>Send (&#x279C;)</strong> button.</li>
                            <li>The song will be added to the "Current Queue" in your Admin's Dashboard and will later appear on the Party Screen.</li>
                        </ul>
                    </li>
                </ol>

                <p class="howto-outro">Enjoy the party and sing your heart out!</p>
            </div>
        </div>
    </div>

    <script>
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');

        function openTab(tabName) {
            contents.forEach(c => c.classList.remove('active'));
            tabs.forEach(b => b.classList.remove('active'));
            const target = document.getElementById(tabName);
            const btn = document.querySelector('[data-tab="' + tabName + '"]');
            if (target) target.classList.add('active');
            if (btn) btn.classList.add('active');
        }

        tabs.forEach(btn => {
            btn.addEventListener('click', function () {
                const tab = this.dataset.tab;
                history.replaceState(null, '', window.location.pathname + '#' + tab);
                openTab(tab);
            });
        });

        // Load tab from hash on page load
        const hash = window.location.hash.slice(1);
        if (hash === 'admin' || hash === 'guest') {
            openTab(hash);
        }

        // Support back/forward navigation through the hash
        window.addEventListener('hashchange', function () {
            const h = window.location.hash.slice(1);
            if (h === 'admin' || h === 'guest') openTab(h);
        });
    </script>
</body>
</html>
