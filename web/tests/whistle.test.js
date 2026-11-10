// Whistle mode (js/whistle.js): the tracker over a whistle's range, the fold onto the written line, a slip that still
// costs, the live dot's octave, the whistle note found from a held whistle, steps laid from it, and the clips whistled.
import { rangeFor, trackTake, WHISTLE_RANGE } from '/js/pitchtrack.js';
import { asWhistled, foldLive, foldTrack, octavesAbove, whistleBase, whistleNoteOf, WHISTLE_HEIGHT } from '/js/whistle.js';
import { layout, scorePlayTake } from '/js/components/exercise.js';
import { PLAY_SCORERS } from '/js/lesson-scores.js';
import { renderClip } from '/js/ear-rounds.js';
import { assert, close, test } from './harness.js';

const hzOf = (midi) => 440 * 2 ** ((midi - 69) / 12);
const median = (xs) => [...xs].sort((a, b) => a - b)[xs.length >> 1];

/** A whistle: a near-sine with a trace of its second harmonic and a little breath noise, notes `[midi, seconds]`. */
function whistle(notes, rate = 48000) {
    let seed = 3;
    const noise = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1;
    const total = notes.reduce((a, [, s]) => a + s, 0) + 0.4;
    const out = new Float32Array(Math.round(total * rate));
    let phase = 0;
    let t0 = 0.2;
    for (const [midi, seconds] of notes) {
        for (let i = Math.round(t0 * rate); i < Math.round((t0 + seconds) * rate); i++) {
            phase += (2 * Math.PI * hzOf(midi)) / rate;
            const env = Math.min(1, (i / rate - t0) / 0.03, (t0 + seconds - i / rate) / 0.03);
            out[i] = env * (0.3 * Math.sin(phase) + 0.02 * Math.sin(2 * phase) + 0.01 * noise());
        }
        t0 += seconds;
    }
    return out;
}

/** A track at 100 frames a second from `[midi, seconds]` runs (0: silence). */
function track(parts) {
    const hz = [];
    for (const [midi, seconds] of parts) for (let i = 0; i < Math.round(seconds * 100); i++) hz.push(midi ? hzOf(midi) : 0);
    return { rate: 100, hz: Float32Array.from(hz), loudness: Float32Array.from(hz, (v) => (v ? -20 : -Infinity)) };
}

test('whistle: the tracker hears a whistle where it is in the whistle range, and a voice\'s range reads it an octave low', () => {
    const rate = 48000;
    const take = whistle([[86, 0.8], [88, 0.8], [90, 0.8], [93, 0.8]], rate); // D6 to A6, 1.2 to 1.8 kHz
    assert(rangeFor({ whistle: true }) === WHISTLE_RANGE && rangeFor({}).maxHz === undefined, 'a whistle step\'s range, and a voice\'s otherwise');
    const whistled = trackTake(take, rate, WHISTLE_RANGE);
    const voiced = trackTake(take, rate);
    [86, 88, 90, 93].forEach((midi, k) => {
        const mid = (from) => median(Array.from(from.hz.slice(Math.round((0.4 + 0.8 * k) * 100), Math.round((0.75 + 0.8 * k) * 100))).filter(Boolean).map((hz) => 1200 * Math.log2(hz / hzOf(midi))));
        close(mid(whistled), 0, 5, `whistle range, note ${midi}`);
        // A voice's range stops at 1100 Hz: past about 1.4 kHz it reads a whistle an octave low.
        if (midi >= 90) assert(Math.round(mid(voiced) / 1200) === -1, `a voice's range reads ${midi} an octave low (${Math.round(mid(voiced))} cents)`);
    });
});

test('whistle: a take is folded onto the line by its median whole octaves; a note that slips an octave still costs', () => {
    const target = [0, 1, 2, 3].map((i) => ({ start: i * 0.5, end: (i + 1) * 0.5, pitch: 60 + i * 2 }));
    const up2 = track(target.map((n) => [n.pitch + 24, 0.5]));
    assert(octavesAbove(up2, target) === 2, 'two octaves up');
    const folded = foldTrack(up2, target);
    assert(folded.octaves === 2 && folded.slips === 0 && Math.abs(folded.track.hz[75] - hzOf(62)) < 0.01, 'moved down two octaves onto the line');
    assert(foldTrack(track(target.map((n) => [n.pitch, 0.5])), target).octaves === 0, 'a line where it\'s written stays');
    const slipped = foldTrack(track(target.map((n, i) => [n.pitch + 24 + (i === 3 ? 12 : 0), 0.5])), target);
    const cents = 1200 * Math.log2(slipped.track.hz[175] / hzOf(66));
    assert(slipped.octaves === 2 && slipped.slips === 1 && Math.abs(cents - 1200 * Math.round(cents / 1200)) > 500, `the slipped note reads a tritone off any octave: ${Math.round(cents)} cents`);
    assert(foldTrack(up2, target, { slips: false }).slips === 0, 'a ribbon (wave steps) isn\'t marked');
});

test('whistle: the live dot moves to the note\'s octave', () => {
    assert(foldLive(86, 62) === 62 && foldLive(87.3, 62) === 63.3 && foldLive(null, 62) === null && foldLive(70, null) === 70, 'folded to the nearest octave of the note now');
});

test('whistle note: a steady second finds it, the scoop at the start left out; a breathy one too; a shake or too little doesn\'t', () => {
    const scooped = { rate: 100, hz: Float32Array.from({ length: 200 }, (_, i) => hzOf(i < 15 ? 84 + (4 * i) / 15 : 88 + 0.1 * Math.sin(i / 5))) };
    assert(whistleNoteOf(scooped, { from: 0, to: 2 })?.midi === 88, `E6, past the scoop: ${JSON.stringify(whistleNoteOf(scooped, { from: 0, to: 2 }))}`);
    const breathy = { rate: 100, hz: Float32Array.from({ length: 250 }, (_, i) => (i % 5 < 2 ? 0 : hzOf(86))) };
    assert(whistleNoteOf(breathy, { from: 0, to: 2.5 })?.midi === 86, 'a breathy whistle, 60% read, is found');
    assert(whistleNoteOf(track([[86, 0.8]]), { from: 0, to: 0.8 }) === null, 'under a second isn\'t enough');
    const waving = (cents, hz) => ({ rate: 100, hz: Float32Array.from({ length: 300 }, (_, i) => hzOf(88 + (cents / 100) * Math.sin((2 * Math.PI * hz * i) / 100))) });
    assert(whistleNoteOf(waving(20, 5.5), { from: 0, to: 3 })?.midi === 88, 'a whistle\'s own small vibrato is still steady');
    assert(whistleNoteOf(waving(60, 3), { from: 0, to: 3 }) === null, 'a 3 Hz shake of 60 cents isn\'t a steady whistle');
    const found = PLAY_SCORERS.whistleNote({ track: track([[62, 1.6]]), heard: track([[86, 1.6]]), target: [{ start: 0, end: 1.6, pitch: 62 }] });
    assert(found.percent === 100 && found.personal.whistle === 86 && found.line.key === 'styles.whistle.found' && found.line.values.note === 'D6', `read from the take as heard, not folded: ${JSON.stringify(found)}`);
    const none = PLAY_SCORERS.whistleNote({ track: track([[0, 2]]), target: [{ start: 0, end: 2, pitch: 62 }] });
    assert(none.percent === null && none.line.key === 'styles.whistle.unheard', 'nothing heard: no score, and what to try');
});

test('whistle base: a step keeps its place in the whistle range, at the octave nearest the voice; the voice\'s note until found', () => {
    assert(whistleBase(88, 57) === 52 && whistleBase(88, 60) === 64, 'E6 laid as the E nearest A3, and nearest C4');
    assert(whistleBase(88, 57, 2) === 54, 'moved by the key');
    assert(whistleBase(undefined, 57) === 57, 'not found yet');
});

test('whistle: a play step whistled two octaves up scores as sung where written; a slip halfway costs; its 80 ms scoop is clean', () => {
    const exercise = { tempo: 80, whistle: true, notes: [0, 2, 4, 5, 4, 2].map((step, i) => ({ beat: i, length: 1, step })) };
    const laid = layout(exercise, 57);
    const bs = 60 / 80;
    const take = (shift, { slip = false, scoop = 0 } = {}) => {
        const hz = new Float32Array(Math.round((laid.end * bs + 0.5) * 100));
        laid.target.forEach((n, i) => {
            // Joined notes, 50 ms behind the beat (a legato line).
            for (let f = Math.ceil((n.beat * bs + 0.05) * 100); f < Math.min(hz.length, ((n.beat + n.length) * bs + 0.05) * 100); f++) {
                const tIn = f / 100 - n.beat * bs - 0.05;
                hz[f] = hzOf(n.pitch + shift + (slip && i >= 3 ? 12 : 0) - (tIn < scoop ? 1.5 * (1 - tIn / scoop) : 0));
            }
        });
        return { rate: 100, hz, loudness: Float32Array.from(hz, (v) => (v ? -18 : -Infinity)) };
    };
    for (const score of [undefined, 'landing', 'legato']) {
        const ex = { ...exercise, score };
        const at = (t) => scorePlayTake({ track: t, laid, exercise: ex, beatSeconds: bs, strictness: 'relaxed' });
        const written = at(take(0));
        const high = at(take(24));
        // (Legato's own bar is the guard's, with its singer's glides: this line jumps between notes.)
        assert((score === 'legato' || high.percent >= 90) && high.percent === written.percent && high.whistled === 2 && written.whistled === 0, `${score ?? 'default'}: two octaves up ${high.percent}, as written ${written.percent}`);
        assert(at(take(24, { slip: true })).percent < 90, `${score ?? 'default'}: a slip halfway costs (${at(take(24, { slip: true })).percent})`);
        if (score === 'landing') assert(at(take(24, { scoop: 0.08 })).percent >= 90, `an 80 ms scoop is a clean start (${at(take(24, { scoop: 0.08 })).percent})`);
    }
});

test('whistle: a choose step\'s clip is whistled at whistle height, every line by one shift', () => {
    const notes = asWhistled([{ pitch: 57 }, { pitch: 61 }, { pitch: 64 }]);
    assert(notes.every((n) => n.voice === 'whistle') && notes[1].pitch === 61 + 24 && Math.abs(notes[1].pitch - WHISTLE_HEIGHT) <= 6, JSON.stringify(notes));
    assert(asWhistled([{ pitch: 57 }], 93)[0].pitch === 93, 'around the singer\'s own whistle note');
    const clip = { notes: [{ beat: 0, length: 1, step: 0 }] };
    const rate = 48000;
    const whistled = renderClip(clip, 57, { tempo: 60, sampleRate: rate, exercise: { whistle: true } });
    const sung = renderClip(clip, 57, { tempo: 60, sampleRate: rate });
    // Zero crossings over the middle of the note: two octaves up is about four times as many.
    const crossings = (x) => { let n = 0; for (let i = 0.3 * rate; i < 0.7 * rate; i++) if (x[i - 1] < 0 && x[i] >= 0) n++; return n; };
    assert(Math.abs(crossings(whistled) / crossings(sung) - 4) < 0.3, `whistled ${crossings(whistled)}, sung ${crossings(sung)}`);
});
