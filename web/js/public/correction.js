// Suggest a correction (components/site/correction.blade.php; docs/corrections-plan.md): opens the page's sheet, sends
// the reader's correction to POST /api/corrections with the page's type, path, title and language, and says how it went
// in the sheet: thanks (then it closes), a field's own message beside it (the server names the field on a 422), or a
// calm line when it's sent too many or the network fails. Nothing about the reader is sent.

for (const sheet of document.querySelectorAll('[data-correction]')) {
    const opener = document.querySelector(`[data-correction-open="${sheet.id}"]`);
    const form = sheet.querySelector('[data-correction-form]');
    const status = sheet.querySelector('[data-correction-status]');
    const send = sheet.querySelector('[data-correction-send]');
    const words = JSON.parse(sheet.querySelector('[data-correction-words]')?.textContent ?? '{}');

    const errors = () => [...sheet.querySelectorAll('[data-error]')];
    function clear() {
        for (const el of errors()) {
            el.hidden = true;
            el.textContent = '';
        }
        status.textContent = '';
        for (const field of form.querySelectorAll('[aria-invalid]')) field.removeAttribute('aria-invalid');
    }
    function fieldError(name, message) {
        const el = sheet.querySelector(`[data-error="${name}"]`);
        const field = form.elements.namedItem(name);
        if (!el) return false;
        el.textContent = message;
        el.hidden = false;
        if (field instanceof HTMLElement) field.setAttribute('aria-invalid', 'true');
        return true;
    }

    opener?.addEventListener('click', () => {
        clear();
        sheet.showModal();
        opener.setAttribute('aria-expanded', 'true');
        form.elements.namedItem('text')?.focus();
    });
    sheet.addEventListener('close', () => {
        opener?.setAttribute('aria-expanded', 'false');
        opener?.focus({ preventScroll: true });
    });
    // A tap on the dimmed page around the sheet closes it, as Cancel does.
    sheet.addEventListener('click', (event) => {
        if (event.target !== sheet) return;
        const r = sheet.getBoundingClientRect();
        if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) sheet.close();
    });
    sheet.querySelector('[data-correction-cancel]')?.addEventListener('click', () => sheet.close());

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clear();
        const text = String(form.elements.namedItem('text')?.value ?? '').trim();
        if (text === '') {
            fieldError('text', words.text_needed ?? '');
            form.elements.namedItem('text')?.focus();
            return;
        }
        const source = String(form.elements.namedItem('source')?.value ?? '').trim();
        const nickname = String(form.elements.namedItem('nickname')?.value ?? '');
        const body = {
            kind: form.elements.namedItem('kind')?.value ?? 'other',
            text,
            source: source === '' ? null : source,
            page: { type: sheet.dataset.type, path: sheet.dataset.path, title: sheet.dataset.title },
            lang: sheet.dataset.lang,
            // The trap: sent only when something filled it, and the server refuses it then.
            ...(nickname !== '' ? { nickname } : {}),
        };
        send.disabled = true;
        status.textContent = words.sending ?? '';
        try {
            const response = await fetch('/api/corrections', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(body),
            });
            if (response.status === 201) {
                form.reset();
                status.textContent = words.thanks ?? '';
                setTimeout(() => sheet.close(), 2200);
                return;
            }
            if (response.status === 422) {
                const json = await response.json().catch(() => ({}));
                const named = Object.keys(json?.errors ?? {});
                const shown = named.filter((name) => fieldError(name, words[`bad_${name}`] ?? words.bad ?? '')).length;
                status.textContent = shown > 0 ? '' : (words.bad ?? '');
                return;
            }
            status.textContent = response.status === 429 ? (words.too_many ?? '') : (words.failed ?? '');
        } catch {
            status.textContent = words.failed ?? '';
        } finally {
            send.disabled = false;
        }
    });
}
