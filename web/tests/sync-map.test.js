// A recording's sync map (js/sync-map.js) and the video's clock (js/video-clock.js): docs/youtube-sync-plan.md.
import { barLines, beatAt, centsAt, mapProblems, performanceBeats, placeOf, secondsAt, segments, unroll } from '/js/sync-map.js';
import { JITTER, STATE, videoClock } from '/js/video-clock.js';
import { assert, close, test } from './harness.js';

// A small song: one 12-beat verse in C, 3/4, two parts, a chord on G at beat 6.
const song = () => ({
    id: 'tiny', key: 'C', tempo: 60, timeSignature: '3/4', pickupBeats: 0,
    sections: [{ name: 'Verse 1', startBeat: 0, endBeat: 12, phrases: [[0, 6], [6, 12]] }],
    chords: [{ beat: 0, root: 0, quality: 'maj', bass: null }, { beat: 6, root: 7, quality: 'maj', bass: 11 }],
    parts: [
        { key: 'soprano', range: { low: 60, high: 67 }, guide: { type: 'notes', notes: [{ beat: 0, length: 6, pitch: 67, lyric: 'Si-' }, { beat: 6, length: 6, pitch: 60, lyric: 'lent' }] } },
        { key: 'bass', range: { low: 48, high: 55 }, guide: { type: 'notes', notes: [{ beat: 0, length: 12, pitch: 48 }] } },
    ],
});
// Three verses: the first two in Bb (down 2), an organ bar between, the last lifted to C (0), words only on the first.
const form = [
    { kind: 'section', section: 0, shift: -2, cents: -8 },
    { kind: 'section', section: 0, shift: -2, cents: -15, words: false },
    { kind: 'gap', beats: 3 },
    { kind: 'section', section: 0, shift: 0, cents: -20, words: false, name: 'Verse 3' },
];

test('sync map: the form lays its segments end to end', () => {
    const all = segments(song(), form);
    assert(all.map((s) => `${s.start}-${s.end}`).join(' ') === '0-12 12-24 24-27 27-39', 'places in the performance');
    assert(performanceBeats(song(), form) === 39, 'its length');
});

test('sync map: between anchors the beat moves evenly; outside them there is nothing of ours', () => {
    // A held note: bar 2 takes twice as long as bar 1 (a fermata, a ritardando), and no tempo is stored anywhere.
    const anchors = [[10, 0], [13, 3], [19, 6]];
    assert(beatAt(anchors, 9.9) === null, 'the intro');
    close(beatAt(anchors, 11.5), 1.5, 1e-9, 'halfway through bar 1');
    close(beatAt(anchors, 16), 4.5, 1e-9, 'halfway through the slow bar');
    assert(beatAt(anchors, 19.1) === null, 'the applause');
    close(secondsAt(anchors, 4.5), 16, 1e-9, 'and back, to seek');
    assert(secondsAt(anchors, 7) === null, 'past the last anchor');
});

test('sync map: a performance beat falls in its section, at its template beat, with its cents', () => {
    const s = song();
    assert(placeOf(s, form, 14).templateBeat === 2 && placeOf(s, form, 14).segment.index === 1, 'verse 2, beat 2');
    assert(placeOf(s, form, 25).templateBeat === null, 'the organ bar');
    assert(placeOf(s, form, 39) === null, 'past the end');
    assert(centsAt(s, form, 30) === -20 && centsAt(s, form, 25) === 0, 'the last verse sinks 20 cents; a gap has none');
});

test('sync map: unrolled, the performance is one template with each verse moved, shifted and worded as the form says', () => {
    const u = unroll(song(), form);
    assert(u.key === 'Bb', 'the first verse\'s key');
    const sop = u.parts[0].guide.notes;
    assert(sop.map((n) => `${n.beat}:${n.pitch}`).join(' ') === '0:65 6:58 12:65 18:58 27:67 33:60', 'beats and pitches');
    assert(sop[0].lyric === 'Si-' && sop[2].lyric === undefined && sop[4].lyric === undefined, 'words only where the form keeps them');
    assert(u.parts[0].range.low === 58 && u.parts[0].range.high === 67, 'the range spans every shift');
    assert(u.chords.map((c) => `${c.beat}:${c.root}/${c.bass}`).join(' ') === '0:10/null 6:5/9 12:10/null 18:5/9 27:0/null 33:7/11', 'chords as pitch classes');
    assert(u.sections.map((x) => `${x.name}@${x.startBeat}`).join(' ') === 'Verse 1@0 Verse 1@12 Verse 3@27', 'sections in place');
    assert(JSON.stringify(u.sections[2].phrases) === '[[27,33],[33,39]]', 'phrases move with them');
    assert(song().parts[0].guide.notes[0].pitch === 67, 'the template itself is untouched');
});

test('sync map: a verse sung again starts on a bar line, pickup and all, though the song is not whole bars', () => {
    // A one-beat pickup and four bars of 3/4: 13 beats, so a second verse starts at 13, not on 12 or 15.
    const pickup = { ...song(), pickupBeats: 1, sections: [{ name: 'Verse 1', startBeat: 0, endBeat: 13, phrases: [[0, 13]] }] };
    const twice = [{ kind: 'section', section: 0 }, { kind: 'section', section: 0 }];
    assert(barLines(pickup, twice).join() === '0,1,4,7,10,13,14,17,20,23,26', `got ${barLines(pickup, twice).join()}`);
    const gap = [{ kind: 'section', section: 0 }, { kind: 'gap', beats: 6 }, { kind: 'section', section: 0 }];
    assert(barLines(pickup, gap).join() === '0,1,4,7,10,13,16,19,20,23,26,29,32', `with a two-bar interlude, got ${barLines(pickup, gap).join()}`);
});

test('sync map: problems are named, a sound map has none', () => {
    const s = song();
    assert(mapProblems(s, { form, anchors: [[1, 0], [2, 3]] }).length === 0, 'sound');
    assert(mapProblems(s, { form: [], anchors: [] }).join() === 'form.empty,anchors.few', 'empty');
    assert(mapProblems(s, { form: [{ kind: 'section', section: 3 }], anchors: [[1, 0], [2, 1]] }).includes('form.section'), 'no such section');
    assert(mapProblems(s, { form, anchors: [[1, 0], [1, 3]] }).includes('anchors.order'), 'time must move on');
    assert(mapProblems(s, { form, anchors: [[1, 0], [2, 40]] }).includes('anchors.value'), 'past the performance');
    assert(mapProblems(s, { form: [{ kind: 'section', section: 0, cents: 70 }], anchors: [[1, 0], [2, 1]] }).includes('form.cents'), 'cents beyond a half step');
    assert(mapProblems(s, { form: [{ kind: 'gap', beats: 6 }], anchors: [[1, 0], [2, 1]] }).includes('form.sections'), 'nothing of ours to show');
});

// A fake player whose reading is posted every `post` seconds, as the iframe's is.
function fakePlayer({ post = 0.25 } = {}) {
    const p = { ms: 0, video: 0, state: STATE.UNSTARTED, rate: 1, posted: 0 };
    p.player = { getCurrentTime: () => p.posted, getPlayerState: () => p.state, getPlaybackRate: () => p.rate };
    p.advance = (seconds, { moves = true } = {}) => {
        const steps = Math.round(seconds / 0.01);
        for (let i = 0; i < steps; i++) {
            p.ms += 10;
            if (moves && p.state === STATE.PLAYING) p.video += 0.01 * p.rate;
            if (Math.round(p.ms) % Math.round(post * 1000) === 0) p.posted = p.video;
        }
    };
    p.now = () => p.ms;
    return p;
}

test('video clock: smooth between the player\'s posts, and close to the real position', () => {
    const f = fakePlayer();
    const clock = videoClock(f.player, { now: f.now });
    f.state = STATE.PLAYING;
    clock.stateChanged(STATE.PLAYING);
    let worst = 0;
    let last = -1;
    for (let i = 0; i < 400; i++) {
        f.advance(0.01);
        const t = clock.read();
        if (f.ms > 600) worst = Math.max(worst, Math.abs(t - f.video));
        assert(t >= last - 1e-9, 'never runs backwards while playing');
        last = t;
    }
    assert(worst < 0.27, `within a post of the truth (worst ${worst.toFixed(3)} s)`);
});

test('video clock: holds still while paused, buffering or showing an ad, then picks up where the video is', () => {
    const f = fakePlayer();
    const clock = videoClock(f.player, { now: f.now });
    f.state = STATE.PLAYING;
    clock.stateChanged(STATE.PLAYING);
    for (let i = 0; i < 200; i++) { f.advance(0.01); clock.read(); }
    const before = clock.read();
    // An ad: the state says playing, the video's time stands still.
    for (let i = 0; i < 300; i++) { f.advance(0.01, { moves: false }); clock.read(); }
    assert(Math.abs(clock.read() - f.video) < 0.05, 'waits at the video\'s own place');
    assert(clock.read() - before < 0.7, 'never runs on through the ad');
    for (let i = 0; i < 100; i++) { f.advance(0.01); clock.read(); }
    assert(Math.abs(clock.read() - f.video) < 0.27, 'and carries on with it');
    f.state = STATE.PAUSED;
    clock.stateChanged(STATE.PAUSED);
    const paused = clock.read();
    f.advance(1);
    close(clock.read(), paused, 1e-9, 'paused is still');
});

test('video clock: a seek is followed at once; a late post isn\'t a jump', () => {
    const f = fakePlayer();
    const clock = videoClock(f.player, { now: f.now });
    f.state = STATE.PLAYING;
    clock.stateChanged(STATE.PLAYING);
    for (let i = 0; i < 100; i++) { f.advance(0.01); clock.read(); }
    f.video = 42;
    f.posted = 42;
    close(clock.read(), 42, 1e-9, 'jumped with the seek');
    f.video = 10;
    f.posted = 10;
    close(clock.read(), 10, 1e-9, 'and back');
    assert(JITTER > 0.1 && JITTER < 0.25, 'jitter is a post\'s worth');
});

test('video clock: follows the player\'s rate, and the singer\'s nudge moves it', () => {
    const f = fakePlayer();
    const clock = videoClock(f.player, { now: f.now, nudge: 0.1 });
    f.state = STATE.PLAYING;
    clock.stateChanged(STATE.PLAYING);
    f.rate = 0.5;
    clock.rateChanged(0.5);
    for (let i = 0; i < 400; i++) { f.advance(0.01); clock.read(); }
    assert(Math.abs(clock.read() + 0.1 - f.video) < 0.15, 'at half speed, 100 ms earlier');
    assert(f.video > 1.9 && f.video < 2.1, 'the fake really ran at half speed');
});

test('video clock: a player that throws (torn down) keeps the last time', () => {
    const f = fakePlayer();
    const clock = videoClock(f.player, { now: f.now });
    f.player.getCurrentTime = () => { throw new Error('gone'); };
    assert(clock.read() === 0, 'no crash, the last value');
});

test('youtube: a link of any usual shape, or a bare id, gives the video id; anything else none', async () => {
    const { videoIdFrom } = await import('/js/youtube-player.js');
    for (const link of ['dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', 'https://youtu.be/dQw4w9WgXcQ?si=x', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://m.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ']) {
        assert(videoIdFrom(link) === 'dQw4w9WgXcQ', `from ${link}`);
    }
    assert(videoIdFrom('https://example.com/watch?x=1') === null && videoIdFrom('') === null && videoIdFrom('short') === null, 'none');
});

test('youtube sync tool: the mic step tries the song\'s sections one to five times, later verses without words', async () => {
    const { candidateForms } = await import('/js/admin/song-videos.js');
    const two = { sections: [{ name: 'Verse', startBeat: 0, endBeat: 12 }, { name: 'Refrain', startBeat: 12, endBeat: 24 }] };
    const forms = candidateForms(two);
    assert(forms.length === 5 && forms[0].length === 2 && forms[4].length === 10, 'five forms of whole verses');
    assert(forms[1][2].section === 0 && forms[1][2].words === false && forms[1][2].name === 'Verse 2', 'the second time through');
    assert(forms[0][0].words === undefined && forms[0][1].section === 1, 'the first keeps its words');
});

test('real recordings: the key is the first section\'s, and the other words are known by beat', async () => {
    const { otherWordsAt, recordingKey } = await import('/js/views/recordings.js');
    assert(recordingKey({ key: 'C' }, [{ kind: 'gap', beats: 3 }, { kind: 'section', section: 0, shift: -2 }]) === 'Bb', 'a gap first, then down two');
    assert(recordingKey({ key: 'Em' }, [{ kind: 'section', section: 0, shift: 0 }]) === 'Em', 'its own key');
    assert(otherWordsAt([[48, 96]], 50) && !otherWordsAt([[48, 96]], 96) && !otherWordsAt([[48, 96]], null) && !otherWordsAt(undefined, 10), 'inside the range only');
});

test('youtube sync tool: no template helper shares a name with what the tool keeps on itself (the video clock once hid m:ss)', async () => {
    const { SongVideosSection } = await import('/js/admin/song-videos.js');
    const helpers = Object.keys(SongVideosSection.setup());
    const kept = ['clock', 'player', 'painter', 'raf', 'ticket', 'listening', 'tapping', 'onKey'];
    const clash = helpers.filter((h) => kept.includes(h) || h in SongVideosSection.data());
    assert(clash.length === 0, `shadowed: ${clash.join(', ')}`);
    assert(typeof SongVideosSection.setup().minutes === 'function', 'the length formatter is there');
});
