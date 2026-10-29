// The admin's passkeys, with the browser's own WebAuthn JSON helpers (no library):
// PublicKeyCredential.parseCreationOptionsFromJSON / parseRequestOptionsFromJSON and toJSON().
// Every word comes from the page's data attributes, which the server translates.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const supported = () => typeof window.PublicKeyCredential !== 'undefined'
    && typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function';

async function json(url, failed, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        ...options,
    });
    const data = await response.json().catch(() => ({}));
    // 423: a fresh check with an existing factor comes first; the check brings you back here.
    if (response.status === 423 && data.confirm) {
        window.location.assign(data.confirm);
        return new Promise(() => {});
    }
    if (!response.ok) throw new Error(data.message || failed);
    return data;
}

function wire(root, run) {
    const button = root.querySelector('[data-passkey-start]');
    const error = root.querySelector('[data-passkey-error]');
    if (!supported()) {
        button.disabled = true;
        error.textContent = root.dataset.unsupported;
        return;
    }
    button.addEventListener('click', async () => {
        button.disabled = true;
        error.textContent = '';
        try {
            const { redirect } = await run();
            window.location.assign(redirect);
        } catch (e) {
            error.textContent = e.name === 'NotAllowedError' ? root.dataset.cancelled : (e.message || root.dataset.failed);
            button.disabled = false;
        }
    });
}

const register = document.querySelector('[data-passkey-register]');
if (register) {
    wire(register, async () => {
        const { options } = await json(register.dataset.optionsUrl, register.dataset.failed);
        const credential = await navigator.credentials.create({ publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options) });
        return json(register.dataset.storeUrl, register.dataset.failed, { method: 'POST', body: JSON.stringify({ credential: credential.toJSON() }) });
    });
}

const verify = document.querySelector('[data-passkey-verify]');
if (verify) {
    wire(verify, async () => {
        const { options } = await json(verify.dataset.optionsUrl, verify.dataset.failed);
        const credential = await navigator.credentials.get({ publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options) });
        return json(verify.dataset.verifyUrl, verify.dataset.failed, { method: 'POST', body: JSON.stringify({ credential: credential.toJSON() }) });
    });
}
