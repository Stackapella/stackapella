// Feedback (docs/feedback-plan.md; contract/api.md support.create, the feedback2 switch): the pure parts of Contact us
// and its ways in. The form's address and how it's read back (every value checked, so a link can't carry anything
// else in), where Back goes (an app path on this host, else Settings), the device as a kind and a browser family
// (never the user agent), and the choir ask's rule (the first two AI choir songs heard, once each). Pure, no imports.

/** Where the form was opened from (support.create's `moment`). */
export const MOMENTS = ['settings', 'help', 'error', 'choir_ask', 'mic', 'own_choir', 'boot', 'site_404'];
/** The chips' topics as a link may preselect them (contact.js's TOPICS). */
export const TOPIC_IDS = ['recording', 'broken', 'idea', 'account', 'other'];
/** What's off, from the choir ask (support.create's context.choirIssue). */
export const CHOIR_ISSUES = ['voice', 'timing', 'notes', 'other'];

const ID = /^[\w-]{1,64}$/;

/** An app path on this host (no other site, no server address), else Settings: where Back and the done button go. */
export function backPath(from) {
    return typeof from === 'string' && from.length <= 300 && /^\/(?!\/)(?!(?:api|auth|dev)(?:[/?#]|$))[\w\-/?=&%.#]*$/.test(from) && !from.startsWith('/contact') ? from : '/settings';
}

/**
 * The form's address for a way in: `from` (the page to go back to), `moment`, a topic to start on, and what the moment
 * has (`templateId`, `partKey`, `choirRequestId`, `errorCode`, `page` for the site's 404, `ask: 'choir'` for the choir
 * ask's chips). Anything not checkable is left out.
 */
export function contactPath({ from = null, moment = null, topic = null, templateId = null, partKey = null, choirRequestId = null, errorCode = null, page = null, ask = null } = {}) {
    const q = new URLSearchParams();
    if (from && backPath(from) === from) q.set('from', from);
    if (MOMENTS.includes(moment)) q.set('moment', moment);
    if (TOPIC_IDS.includes(topic)) q.set('topic', topic);
    for (const [key, value] of [['template', templateId], ['part', partKey], ['choir', choirRequestId], ['error', errorCode]]) {
        if (typeof value === 'string' && ID.test(value)) q.set(key, value);
    }
    if (typeof page === 'string' && page.startsWith('/') && page.length <= 300) q.set('page', page);
    if (ask === 'choir') q.set('ask', 'choir');
    const query = q.toString();
    return query ? `/contact?${query}` : '/contact';
}

/** The form's address read back: every value checked again (a link typed by anyone), or null. */
export function readContact(search) {
    const q = new URLSearchParams(search);
    const id = (key) => (ID.test(q.get(key) ?? '') ? q.get(key) : null);
    const from = q.get('from');
    const page = q.get('page');
    return {
        from: from && backPath(from) === from ? from : null,
        back: backPath(from),
        moment: MOMENTS.includes(q.get('moment')) ? q.get('moment') : null,
        topic: TOPIC_IDS.includes(q.get('topic')) ? q.get('topic') : null,
        templateId: id('template'),
        partKey: id('part'),
        choirRequestId: id('choir'),
        errorCode: id('error'),
        page: page && page.startsWith('/') && page.length <= 300 ? page : null,
        choirAsk: q.get('ask') === 'choir' && ID.test(q.get('choir') ?? ''),
        diagnostics: q.has('diagnostics'),
    };
}

/** The device as a kind: `phone`, `tablet` or `computer` (an iPad that says it's a Mac has touch). */
export function deviceKind(ua = '', touchPoints = 0) {
    if (/iPad|Tablet|PlayBook|Silk/.test(ua) || (/Android/.test(ua) && !/Mobile/.test(ua))) return 'tablet';
    if (/Macintosh/.test(ua) && touchPoints > 1) return 'tablet';
    if (/iPhone|iPod|Android|Mobile|IEMobile|Opera Mini/.test(ua)) return 'phone';
    return 'computer';
}

/** The browser as a family: `chrome`, `safari`, `firefox`, `edge`, `samsung`, `opera` or `other`. */
export function browserFamily(ua = '') {
    if (/Edg(?:e|A|iOS)?\//.test(ua)) return 'edge';
    if (/SamsungBrowser\//.test(ua)) return 'samsung';
    if (/OPR\/|OPiOS\/|Opera/.test(ua)) return 'opera';
    if (/Firefox\/|FxiOS\//.test(ua)) return 'firefox';
    if (/Chrome\/|CriOS\/|Chromium\//.test(ua)) return 'chrome';
    if (/Safari\//.test(ua) && /Version\/|Mobile\//.test(ua)) return 'safari';
    return 'other';
}

/** A browser family's own name, as shown in "Sent with it" (a product name, the same in every language). */
export const BROWSER_NAMES = { chrome: 'Chrome', safari: 'Safari', firefox: 'Firefox', edge: 'Edge', samsung: 'Samsung Internet', opera: 'Opera', other: '' }; // i18n-ignore: product names

/**
 * The choir ask (docs/feedback-plan.md, section 4): due on one of the first two AI choir songs heard, once each.
 * `asked` is the songs it has shown on (this device's), `id` the choir song now. A song it has shown on isn't asked
 * again, and after two it's never asked.
 */
export function choirAskDue(asked, id) {
    const list = Array.isArray(asked) ? asked : [];
    return typeof id === 'string' && id !== '' && !list.includes(id) && list.length < 2;
}
