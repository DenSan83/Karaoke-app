<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Screen</title>
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
    <h1>Welcome to my Karaoke app!</h1>
    <p>Have you joined the party?</p>
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
    const GROUP_ID = <?= json_encode($groupId) ?>;
</script>
<script src="https://www.youtube.com/iframe_api"></script>
<script src="public/js/player.js"></script>

</body>
</html>
