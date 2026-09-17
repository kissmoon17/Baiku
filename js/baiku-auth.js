/* Baiku Phase 4 - authentication client */

async function baikuAuthRequest(action, payload = {}) {
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    });

    let data;
    try {
        data = await response.json();
    } catch {
        throw new Error('The server returned an invalid response.');
    }

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Authentication request failed.');
    }

    return data;
}

async function baikuRegister(name, email, password) {
    return baikuAuthRequest('register', {name, email, password});
}

async function baikuLogin(email, password) {
    return baikuAuthRequest('login', {email, password});
}

async function baikuLogout() {
    return baikuAuthRequest('logout');
}

async function baikuSession() {
    const response = await fetch('api.php?action=session', {
        credentials: 'same-origin'
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Could not check session.');
    }

    return data;
}
