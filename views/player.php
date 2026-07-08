<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Screen</title>
    <base href="<?= htmlspecialchars($basePath) ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/player.css">
</head>
<body>

<div id="player-container" class="hidden">
    <div id="player"></div>
    <video id="html5-player" class="hidden" controls preload="auto"></video>
    <div id="pause-overlay" class="hidden">
        <h1>Paused</h1>
        <p>Click Play to Resume</p>
    </div>
</div>

<div id="animation-background">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="orb orb-4"></div>
</div>

<div id="overlay-message">
    <h2 id="party-name" class="hidden"></h2>
    <h1>Welcome to my Karaoke app!</h1>
    <p>Have you joined the party?</p>
    <div id="pairing-overlay">
        <div id="pairing-code-row">
            <div id="screen-code-display">------</div>
            <svg id="code-timer" viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
                <circle class="timer-track" cx="18" cy="18" r="15.9"/>
                <circle id="timer-arc" class="timer-arc" cx="18" cy="18" r="15.9"/>
            </svg>
        </div>
    </div>
</div>

<div id="next-song-overlay" class="hidden">
    <h1>Next Song</h1>
    <h2 id="next-song-title">[Title]</h2>
    <p>by <span id="next-song-user">[User]</span></p>
</div>

<div id="controls">
    <button id="fullscreenBtn">Enter Fullscreen</button>
</div>

<script>
    const BASE_PATH = <?= json_encode($basePath) ?>;
</script>
<script src="https://www.youtube.com/iframe_api"></script>
<script src="public/js/player.js"></script>

</body>
</html>
