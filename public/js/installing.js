let currentMethod = null;

function selectMethod(method) {
    currentMethod = method;
    document.getElementById('options-view').classList.add('hidden');
    document.getElementById('progress-view').classList.remove('hidden');

    if (method === 'manual') {
        showManualInstructions();
    } else {
        startAutomatedInstall(method);
    }
}

function showOptions() {
    document.getElementById('options-view').classList.remove('hidden');
    document.getElementById('progress-view').classList.add('hidden');
    document.getElementById('manual-instructions').classList.add('hidden');
    document.getElementById('auto-install').classList.add('hidden');
    document.getElementById('install-log').classList.add('hidden');
    document.getElementById('install-result').classList.add('hidden');
    currentMethod = null;
}

async function showManualInstructions() {
    document.getElementById('manual-instructions').classList.remove('hidden');

    try {
        const response = await fetch('api/install_instructions');
        const data = await response.json();

        const stepsList = document.getElementById('instruction-steps');
        stepsList.innerHTML = '';

        data.steps.forEach(step => {
            const li = document.createElement('li');
            if (step.startsWith('sudo ') || step.startsWith('yt-dlp ')) {
                li.innerHTML = `<code>${step}</code>`;
            } else {
                li.textContent = step;
            }
            stepsList.appendChild(li);
        });
    } catch (error) {
        console.error('Error fetching instructions:', error);
    }
}

async function startAutomatedInstall(method) {
    document.getElementById('auto-install').classList.remove('hidden');
    document.getElementById('install-log').classList.remove('hidden');

    const titleMap = {
        'project': 'Installing to Project Directory...',
        'system': 'Installing System-wide...'
    };

    document.getElementById('install-title').textContent = titleMap[method];

    try {
        const formData = new FormData();
        formData.append('method', method);

        const response = await fetch('api/install', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        showResult(result);
    } catch (error) {
        showResult({
            success: false,
            error: 'Network error: ' + error.message
        });
    }
}

async function testInstallation() {
    document.getElementById('auto-install').classList.remove('hidden');
    document.getElementById('install-log').classList.remove('hidden');
    document.getElementById('progress-message').textContent = 'Testing installation...';

    try {
        const formData = new FormData();
        formData.append('method', 'test');

        const response = await fetch('api/install', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        showResult(result);
    } catch (error) {
        showResult({
            success: false,
            error: 'Network error: ' + error.message
        });
    }
}

function showResult(result) {
    document.getElementById('auto-install').classList.add('hidden');
    document.getElementById('install-result').classList.remove('hidden');

    if (result.success) {
        document.getElementById('success-message').classList.remove('hidden');
        document.getElementById('error-message').classList.add('hidden');

        if (result.version) {
            document.getElementById('version-info').textContent = `yt-dlp version ${result.version} is ready to use.`;
        } else if (result.message) {
            document.getElementById('version-info').textContent = result.message;
        }
    } else {
        document.getElementById('success-message').classList.add('hidden');
        document.getElementById('error-message').classList.remove('hidden');
        document.getElementById('error-info').textContent = result.error || 'Unknown error occurred';
    }
}

function redirectToApp() {
    window.location.href = './';
}
