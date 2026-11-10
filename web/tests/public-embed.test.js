// A free tool embedded in another site (js/public/tools/embed.js): the credit's ref from the framing site's host, and
// the look from the link set for the frame only.
import { applyLook, creditUrl, framingHost } from '/js/public/tools/embed.js';
import { assert, test } from './harness.js';

test('embed: the credit carries the framing site\'s host, lowercase, and nothing when there\'s no referrer', () => {
    assert(framingHost('https://Choir.Example.org/page?x=1') === 'choir.example.org', framingHost('https://Choir.Example.org/page?x=1'));
    assert(framingHost('') === '' && framingHost('not a url') === '', 'no host');
    assert(creditUrl('https://app.stackapella.com/', 'embed-', 'choir.example.org') === 'https://app.stackapella.com/?ref=embed-choir.example.org', 'the ref');
    assert(creditUrl('https://app.stackapella.com/', 'embed-', '') === 'https://app.stackapella.com/', 'no ref without a host');
});

test('embed: ?theme= and ?skin= set the frame\'s look, and anything else is ignored', () => {
    const el = document.createElement('div');
    applyLook('?theme=warm-light&skin=serious', el);
    assert(el.dataset.theme === 'warm-light' && el.dataset.skin === 'serious', 'set');
    const other = document.createElement('div');
    applyLook('?theme=javascript:alert(1)&skin=pink', other);
    assert(other.dataset.theme === undefined && other.dataset.skin === undefined, 'ignored');
});
