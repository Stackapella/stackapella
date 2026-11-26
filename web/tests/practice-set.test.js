// Practise the set (js/practice-set.js, js/practice-set-render.js; docs/practice-playlist-plan.md): the one file's
// timeline (each song's start, Next and Previous as seeks), the part played louder in each song, Repeat my part's
// stretch, and each song's mix (your part louder or left out, every song at the same peak).
import { estimateMb, GAP_SECONDS, isFresh, KEEP_DAYS, nextStart, partFor, partSpan, previousStart, RESTART_SECONDS, setSignature, setSongs, songAt, timeline, upcoming } from '/js/practice-set.js';
import { mixSong } from '/js/practice-set-render.js';
import { assert, close, test } from './harness.js';

const template = {
    tempo: 120,
    parts: [
        { key: 'soprano', name: 'Soprano', guide: { notes: [{ beat: 0, length: 2, pitch: 72 }, { beat: 6, length: 2, pitch: 74 }] } },
        { key: 'alto', name: 'Alto', guide: { notes: [{ beat: 2, length: 4, pitch: 67 }] } },
    ],
};

test('practice set: each song starts after the one before and its gap; the last has no gap after it', () => {
    const { starts, total } = timeline([{ seconds: 60 }, { seconds: 30 }, { seconds: 45 }]);
    assert(starts[0] === 0 && starts[1] === 60 + GAP_SECONDS && starts[2] === 90 + 2 * GAP_SECONDS, starts);
    assert(total === 135 + 2 * GAP_SECONDS, total);
});

test('practice set: the song at a time (a gap is the song before), Next and Previous', () => {
    const { starts } = timeline([{ seconds: 60 }, { seconds: 30 }, { seconds: 45 }]);
    assert(songAt(starts, 0) === 0 && songAt(starts, 61) === 0 && songAt(starts, starts[1]) === 1 && songAt(starts, 500) === 2);
    assert(nextStart(starts, 10) === starts[1] && nextStart(starts, starts[2] + 1) === null, 'Next');
    assert(previousStart(starts, starts[1] + RESTART_SECONDS + 1) === starts[1], 'Previous restarts a song well under way');
    assert(previousStart(starts, starts[1] + 1) === 0, 'Previous near a start goes to the song before');
    assert(previousStart(starts, 1) === 0, 'Previous on the first song stays on it');
});

test('practice set: the part played louder is the assignment, else the pick by key or by name, else none', () => {
    assert(partFor(template, 'alto', { key: 'soprano', name: 'Soprano' }) === 'alto', 'the director\'s assignment wins');
    assert(partFor(template, null, { key: 'soprano', name: 'Soprano' }) === 'soprano');
    assert(partFor(template, null, { key: 's1', name: 'alto' }) === 'alto', 'the same name');
    assert(partFor(template, null, { key: 'tenor', name: 'Tenor' }) === null, 'not in this song: all parts even');
    assert(partFor(template, 'bass', null) === null, 'an assignment to a part the song lacks');
});

test('practice set: Repeat my part runs from the part\'s first note to the end of its last', () => {
    const [from, to] = partSpan(template, 'soprano', 0.25);
    close(from, 0.25, 1e-9);
    close(to, 0.25 + 4, 1e-9); // beat 8 at 120 bpm
    assert(partSpan(template, 'tenor') === null);
});

test('practice set: a song\'s mix has your part louder or left out, at one peak, from the earliest voice', () => {
    const rate = 100;
    const one = (value, n = 100) => new Float32Array(n).fill(value);
    const voices = [{ key: 'soprano', samples: one(0.5), offsetSeconds: 0 }, { key: 'alto', samples: one(0.5), offsetSeconds: -0.5, ai: true }];
    const louder = mixSong(voices, 'alto', 'louder', rate);
    close(louder.beatZero, 0.5, 1e-9);
    assert(louder.ai, 'an AI voice in the song is said');
    // Alone at the start (the alto came in early), then both: the alto's share is the larger.
    const altoAlone = louder.samples[10];
    const both = louder.samples[60];
    close(both / altoAlone, (1.4 + 0.5) / 1.4, 1e-3);
    close(Math.max(...louder.samples), 0.89, 1e-6);
    const without = mixSong(voices, 'alto', 'without', rate);
    close(without.samples[10], 0, 1e-9); // the alto's early bit is gone
    close(Math.max(...without.samples), 0.89, 1e-6);
    const even = mixSong(voices, null, 'louder', rate);
    close(even.samples[60] / even.samples[10], 2, 1e-6);
});

test('practice set: the rehearsals still to come, soonest first (one without an end counts for 3 hours)', () => {
    const now = Date.parse('2026-11-26T19:00:00Z');
    const events = [
        { id: 'later', startsAt: '2026-12-12T18:00:00Z', endsAt: null },
        { id: 'past', startsAt: '2026-11-20T19:00:00Z', endsAt: '2026-11-20T21:00:00Z' },
        { id: 'now', startsAt: '2026-11-26T17:30:00Z', endsAt: null },
        { id: 'ended', startsAt: '2026-11-26T15:00:00Z', endsAt: '2026-11-26T17:00:00Z' },
    ];
    assert(upcoming(events, now).map((e) => e.id).join() === 'now,later');
});

test('practice set: a rehearsal plays its own songs in its order, else every group song', () => {
    const group = [{ templateId: 'a' }, { templateId: 'b' }, { templateId: 'c' }];
    const mine = (id) => (id === 'a' ? 'alto' : null);
    const event = { songs: [{ templateId: 'c', partKey: 'tenor' }, { templateId: 'x', partKey: 'alto' }, { templateId: 'a', partKey: null }, { templateId: 'c', partKey: 'tenor' }] };
    const set = setSongs(group, event, mine);
    assert(set.fromEvent && set.list.map((s) => `${s.song.templateId}:${s.assigned}`).join() === 'c:tenor,a:alto', JSON.stringify(set.list));
    const none = setSongs(group, { songs: [] }, mine);
    assert(!none.fromEvent && none.list.map((s) => s.song.templateId).join() === 'a,b,c');
    assert(setSongs(group, null, mine).list.length === 3, 'no rehearsal: all the songs');
});

test('practice set: a kept set opens again only for the same songs, parts and sound, within its 14 days', () => {
    const songs = [{ templateId: 'a', template: { version: 3 }, partKey: 'alto' }, { templateId: 'b', template: { version: 1 }, partKey: null }];
    const sig = setSignature(songs, 'louder', '44100:128');
    const now = Date.parse('2026-11-26T12:00:00Z');
    const kept = { blob: new Blob(['x']), sig, savedAt: now - 864e5 };
    assert(isFresh(kept, sig, now), 'a day old, the same set');
    assert(!isFresh({ ...kept, savedAt: now - KEEP_DAYS * 864e5 }, sig, now), 'past its days');
    assert(!isFresh(kept, setSignature(songs, 'without', '44100:128'), now), 'another sound');
    assert(!isFresh(kept, setSignature([{ ...songs[0], template: { version: 4 } }, songs[1]], 'louder', '44100:128'), now), 'a song changed');
    assert(!isFresh(kept, setSignature([songs[1], songs[0]], 'louder', '44100:128'), now), 'another order');
    assert(!isFresh(null, sig, now));
});

test('practice set: the size said before a phone makes it', () => {
    const minute = { tempo: 60, parts: [{ guide: { notes: [{ beat: 0, length: 59 }] } }] }; // 59 s, and 1 s to ring
    // Six one-minute songs and five gaps at 128 kbps: (360 + 12.5) s x 16 kB/s, about 6 MB.
    assert(estimateMb(Array(6).fill(minute), 128) === 6, estimateMb(Array(6).fill(minute), 128));
    assert(estimateMb([], 128) === 1, 'never "0 MB"');
});
