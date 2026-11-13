// Be the voice (js/be-the-voice.js): only a usable take is invited (the whole part, the song's key, the quiet checks
// passed, no speakers); Not now quiets that part for 14 days; one invitation a session.
import { claimInvitation, later, LATER_DAYS, saidLater, usableTake, USABLE_PERCENT } from '/js/be-the-voice.js';
import { session } from '/js/storage.js';
import { assert, test } from './harness.js';

const take = (over = {}) => ({ seconds: 60, partSeconds: 62, transpose: 0, score: { status: 'scored', percent: 82 }, bleed: false, speakers: false, ...over });

test('be the voice: a take is invited only when it could be the guide', () => {
    assert(usableTake(take()), 'whole, in key, scored well');
    assert(!usableTake(take({ seconds: 30 })), 'half the part');
    assert(!usableTake(take({ transpose: -2 })), 'moved to the singer’s range');
    assert(!usableTake(take({ speakers: true })) && !usableTake(take({ bleed: true })), 'over speakers, or bleed');
    assert(!usableTake(take({ score: { status: 'scored', percent: USABLE_PERCENT - 1 } })), 'a weak take is never praised');
    assert(!usableTake(take({ score: { status: 'unheard', percent: null } })) && !usableTake(take({ score: null })), 'not scored');
});

test('be the voice: Not now quiets that part for 14 days', () => {
    const now = Date.now();
    later('song-x', 'alto', now);
    assert(saidLater('song-x', 'alto', now + 86400000), 'the next day');
    assert(!saidLater('song-x', 'tenor', now), 'another part still asks');
    assert(!saidLater('song-x', 'alto', now + LATER_DAYS * 86400000 + 1), 'after 14 days');
});

test('be the voice: one invitation a session, kept by the part that asked first', () => {
    session.remove('stackapella.voiceAsked');
    assert(claimInvitation('song-y', 'alto') && claimInvitation('song-y', 'alto'), 'the first part, and again');
    assert(!claimInvitation('song-y', 'tenor'), 'another part that session');
    session.remove('stackapella.voiceAsked');
});
