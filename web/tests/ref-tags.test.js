// The ?ref= tag a device arrived with (stores/account.js): a campaign's tag, and an embed's with the framing site's host
// (dots and all, as RecordCounts::source counts it), kept for the counts; the sign-up gets only the plainer tags its
// request takes (letters, digits and hyphens, up to 40), so an embed's never fails a sign-up.
import { ref, rememberRef, signUpRef } from '/js/stores/account.js';
import { assert, test } from './harness.js';

test('ref tags: a campaign tag and an embed host are kept, the sign-up gets only the plain one', () => {
    const before = localStorage.getItem('stackapella.ref');
    try {
        localStorage.removeItem('stackapella.ref');
        rememberRef('?ref=tool-tuner');
        assert(ref() === 'tool-tuner' && signUpRef() === 'tool-tuner', `${ref()} / ${signUpRef()}`);
        rememberRef('?ref=embed-choir.example.org');
        assert(ref() === 'embed-choir.example.org', `an embed's host kept for the counts: ${ref()}`);
        assert(signUpRef() === null, `and left out of the sign-up: ${signUpRef()}`);
        // Never anything else: a space, a slash, an @ or past 60 leave the kept tag as it was.
        for (const bad of ['a b', 'x/y', 'me@example.org', 'embed-' + 'a'.repeat(60) + '.org']) rememberRef(`?ref=${encodeURIComponent(bad)}`);
        assert(ref() === 'embed-choir.example.org', `a bad tag is ignored: ${ref()}`);
    } finally {
        if (before === null) localStorage.removeItem('stackapella.ref');
        else localStorage.setItem('stackapella.ref', before);
    }
});
