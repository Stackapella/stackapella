// The way back to the website (the consultant's UX review, 2026-11-22): an arrival is "from the site" only when the
// referrer is the public site's own origin and the site is another host than the app.
import { siteReferrer } from '/js/hosts.js';
import { assert, test } from './harness.js';

test('site referrer: the site on its own host', () => {
    assert(siteReferrer('https://stackapella.com/', 'https://stackapella.com/', 'https://app.stackapella.com'), 'the landing page');
    assert(siteReferrer('https://stackapella.com/songs/silent-night', 'https://stackapella.com/', 'https://app.stackapella.com'), 'a song page');
    assert(!siteReferrer('https://www.google.com/', 'https://stackapella.com/', 'https://app.stackapella.com'), 'a search engine');
    assert(!siteReferrer('https://app.stackapella.com/learn', 'https://stackapella.com/', 'https://app.stackapella.com'), 'the app itself');
    assert(!siteReferrer('', 'https://stackapella.com/', 'https://app.stackapella.com'), 'no referrer');
    assert(!siteReferrer('not a url', 'https://stackapella.com/', 'https://app.stackapella.com'), 'a broken referrer');
});

test('site referrer: one host for both is never "from the site"', () => {
    assert(!siteReferrer('http://localhost:8000/', '/?landing', 'http://localhost:8000'), 'one host');
});

test('site help: the page on the site, Spanish under /es', async () => {
    const { siteHelpUrl } = await import('/js/hosts.js');
    assert(siteHelpUrl('your-choir').endsWith('/help/your-choir') && !siteHelpUrl('your-choir').includes('/es/'), siteHelpUrl('your-choir'));
    assert(siteHelpUrl('your-choir', 'es').endsWith('/es/help/your-choir'), siteHelpUrl('your-choir', 'es'));
});
