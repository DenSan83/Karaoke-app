<?php
// $selected is a discovered test entry, or null when the whole suite is requested.
$targetName = $selected === null ? null : $selected['name'];
$streamUrl = 'superadmin/tests/stream' . ($targetName === null ? '' : '?file=' . urlencode($targetName));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Running <?= htmlspecialchars($targetName === null ? 'all tests' : $targetName) ?></title>
    <base href="<?= htmlspecialchars($basePath ?? '') ?>/">
    <link rel="icon" type="image/png" href="public/media/karaoke_logo.png">
    <link rel="stylesheet" href="public/css/admin.css">
    <link rel="stylesheet" href="public/css/superadmin.css">
    <link rel="stylesheet" href="public/css/superadmin_tests.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                Running
                <span class="tests-target"><?= htmlspecialchars($targetName === null ? 'all tests' : $targetName) ?></span>
            </h1>
            <div class="tests-actions">
                <button type="button" class="btn btn-primary" id="rerunBtn">Re-run</button>
                <a href="superadmin/tests" class="btn btn-secondary">&larr; Back to Tests</a>
            </div>
        </div>

        <div class="tests-status">
            <span class="tests-pill running" id="statusPill">running</span>
            <span class="tests-elapsed" id="elapsed">0.0s</span>
            <span class="tests-binary" id="binaryLabel"></span>
        </div>

        <div class="terminal" id="terminal" role="log" aria-live="polite"></div>
    </div>

    <script>
        const streamUrl = <?= json_encode($streamUrl) ?>;
        const terminal = document.getElementById('terminal');
        const statusPill = document.getElementById('statusPill');
        const elapsedLabel = document.getElementById('elapsed');
        const binaryLabel = document.getElementById('binaryLabel');

        const startedAt = Date.now();
        const timer = setInterval(function () {
            elapsedLabel.textContent = ((Date.now() - startedAt) / 1000).toFixed(1) + 's';
        }, 100);

        // Follow the output unless the reader has scrolled up to look at something.
        let stickToBottom = true;
        terminal.addEventListener('scroll', function () {
            const distance = terminal.scrollHeight - terminal.scrollTop - terminal.clientHeight;
            stickToBottom = distance < 40;
        });

        /**
         * Which colour a line of runner output gets. Kept to the shapes tests/run.php
         * actually prints, so anything unrecognised stays plain rather than being
         * mislabelled.
         */
        function classifyLine(text) {
            const trimmed = text.trim();
            if (trimmed === '') return 'blank';
            if (trimmed.startsWith('[pass]')) return 'pass';
            if (trimmed.startsWith('[FAIL]')) return 'fail';
            if (trimmed.startsWith('[skip]')) return 'skip';
            if (/^-+$/.test(trimmed)) return 'rule';
            if (/^\d+ passed, \d+ failed/.test(trimmed)) {
                return /,\s*0 failed/.test(trimmed) ? 'summary-ok' : 'summary-bad';
            }
            if (trimmed === 'Failures:') return 'fail';
            if (/Test\.php$/.test(trimmed)) return 'file';
            // Continuation of a [FAIL] block: the runner indents the reason.
            if (/^ {9}\S/.test(text) || /^ {4}\S/.test(text)) return 'detail';
            return 'plain';
        }

        // Output is arbitrary text from another program — file paths, error messages,
        // whatever a failing assertion printed. It is only ever written as text.
        function appendLine(text, className) {
            const line = document.createElement('div');
            line.className = 'tline ' + className;
            line.textContent = text === '' ? ' ' : text;
            terminal.appendChild(line);
            if (stickToBottom) {
                terminal.scrollTop = terminal.scrollHeight;
            }
        }

        function finish(code) {
            clearInterval(timer);
            elapsedLabel.textContent = ((Date.now() - startedAt) / 1000).toFixed(1) + 's';
            statusPill.classList.remove('running');
            if (code === 0) {
                statusPill.classList.add('ok');
                statusPill.textContent = 'passed';
            } else {
                statusPill.classList.add('bad');
                statusPill.textContent = 'failed';
            }
            appendLine('', 'blank');
            appendLine('exit code ' + code, code === 0 ? 'summary-ok' : 'summary-bad');
        }

        function handleEvent(event) {
            if (event.type === 'start') {
                binaryLabel.textContent = event.binary || '';
                appendLine('$ php tests/run.php' + (event.target === 'all tests' ? '' : ' ' + event.target), 'command');
                return;
            }
            // Keep-alive from the server during a quiet stretch; nothing to show.
            if (event.type === 'ping') {
                return;
            }
            if (event.type === 'done') {
                finish(event.code);
                return;
            }
            if (event.type === 'error') {
                appendLine(event.text, 'error');
                return;
            }
            if (event.type === 'stderr') {
                appendLine(event.text, 'stderr');
                return;
            }
            appendLine(event.text, classifyLine(event.text));
        }

        function consume(chunkText, buffer) {
            buffer += chunkText;
            let newline;
            while ((newline = buffer.indexOf('\n')) !== -1) {
                const raw = buffer.slice(0, newline);
                buffer = buffer.slice(newline + 1);
                if (raw.trim() === '') continue;
                try {
                    handleEvent(JSON.parse(raw));
                } catch (e) {
                    appendLine(raw, 'plain');
                }
            }
            return buffer;
        }

        async function run() {
            let response;
            try {
                response = await fetch(streamUrl, { headers: { 'Accept': 'application/x-ndjson' } });
            } catch (e) {
                appendLine('The test runner could not be reached: ' + e.message, 'error');
                finish(1);
                return;
            }

            if (!response.ok) {
                appendLine('The test runner returned HTTP ' + response.status + '.', 'error');
                finish(1);
                return;
            }

            // No EventSource here on purpose: it reconnects when a stream ends, which
            // would quietly start the whole suite again.
            if (!response.body || !response.body.getReader) {
                // Old browser with no streaming support: still correct, just not live.
                const text = await response.text();
                consume(text.endsWith('\n') ? text : text + '\n', '');
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer = consume(decoder.decode(value, { stream: true }), buffer);
            }
            buffer = consume(decoder.decode(), buffer);
            if (buffer.trim() !== '') {
                consume(buffer + '\n', '');
            }

            // A stream that ends without a done event means the process died on us.
            if (statusPill.classList.contains('running')) {
                appendLine('The stream ended before the run finished.', 'error');
                finish(1);
            }
        }

        document.getElementById('rerunBtn').addEventListener('click', function () {
            window.location.reload();
        });

        run();
    </script>
</body>
</html>
