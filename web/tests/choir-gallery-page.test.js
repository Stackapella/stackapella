// The choir gallery page's Report (server/resources/views/site/choir-gallery.blade.php, js/public/harmony.js): a press on
// an item's Report opens the one form, and sending it posts reports.create with that item's choirGalleryId and the reason
// as chosen. "It isn't the singer's own voice" is the gallery's own reason, not_own (contract/api.md), its note as typed.

import { useTransport } from '/js/api.js';
import { mockFetch } from '/js/api-mock.js';
import { assert, test } from './harness.js';

test('choir gallery page: Report sends the item and not_own for "It isn\'t the singer\'s own voice"', async () => {
    const sent = [];
    useTransport(async (url, init) => {
        sent.push({ url: String(url), body: init?.body ? JSON.parse(init.body) : null });
        return new Response(JSON.stringify({ data: {} }), { status: String(url).includes('/api/reports') ? 201 : 200, headers: { 'Content-Type': 'application/json' } });
    });
    const root = document.createElement('div');
    root.innerHTML = `<button type="button" class="hidden" data-report-open data-gallery="g7"></button>
        <dialog data-report><form method="dialog" data-report-form data-harmony="" data-api="">
            <input type="radio" name="reason" value="not_own" checked><input type="radio" name="reason" value="my_voice">
            <textarea name="note">Someone else sings it</textarea><input type="text" name="website" value="">
            <div class="hidden" data-turnstile></div><p class="hidden" data-report-status data-sent="Sent" data-failed="Failed"></p>
            <button type="button" data-report-close></button><button type="submit" data-report-send></button>
        </form></dialog>`;
    document.body.append(root);
    try {
        await import('/js/public/harmony.js?gallery-report');
        const open = root.querySelector('[data-report-open]');
        assert(!open.classList.contains('hidden'), 'Report shows once the script is here');
        open.click();
        await new Promise((resolve) => setTimeout(resolve, 50));
        root.querySelector('[data-report-form]').requestSubmit();
        await new Promise((resolve) => setTimeout(resolve, 100));
        const report = sent.find((call) => call.url.includes('/api/reports'));
        assert(report, `reports.create was posted: ${JSON.stringify(sent)}`);
        assert(report.body.choirGalleryId === 'g7' && !('harmonyId' in report.body), `the item, not a share: ${JSON.stringify(report.body)}`);
        assert(report.body.reason === 'not_own' && report.body.note === 'Someone else sings it', `not_own, its note as typed: ${JSON.stringify(report.body)}`);
    } finally {
        root.querySelector('dialog')?.close();
        root.remove();
        useTransport(mockFetch);
    }
});
