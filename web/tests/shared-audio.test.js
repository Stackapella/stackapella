// The free tools' shared audio layer (docs/tools-plan.md, "Shared modules"): the just-intonation
// table (js/just.js), pitch maths (dsp.js), note names (notes.js), the metronome's sounds
// (js/public/click-sounds.js), the audio-clock ticker (js/public/ticker.js) and the tools' tones
// (js/public/tones.js).
import { chordTones, INTERVALS, intervalCents, pureOffset, pureRatio, RATIOS } from '/js/just.js';
import { chordTones as ringTones, pureOffset as ringOffset } from '/js/ring.js';
import { hzToMidi, midiToHz, nearestNote } from '/js/dsp.js';
import { pitchName } from '/js/notes.js';
import { CLICK_SOUNDS, clickSound } from '/js/public/click-sounds.js';
import { ticker } from '/js/public/ticker.js';
import { held, pluck, sound, strike } from '/js/public/tones.js';
import { audio, TIMBRES, voice } from '/js/public/voices.js';
import { assert, close, test } from './harness.js';

test('just: pure chord tones, the barbershop seventh, and ring.js gives the same table', () => {
    close(pureOffset(64, { root: 0, quality: 'maj' }), -13.7, 0.1, 'a major third');
    close(pureOffset(70, { root: 0, quality: 'dom7' }), -31.2, 0.1, 'the 7:4 seventh');
    assert(pureOffset(62, { root: 0, quality: 'maj' }) === null, 'a passing note has no target');
    assert(pureRatio(19, 'maj') === 3 / 2 && pureRatio(12, 'min') === 1 && pureRatio(1, 'maj') === null, 'ratios in any octave');
    assert(chordTones('dom7').join() === '0,4,7,10' && Object.keys(RATIOS).length === 14, 'tones');
    assert(ringTones === chordTones && ringOffset === pureOffset, 'ring.js re-exports just.js');
});

test('just: every pure interval in cents and against the piano, agreeing with the chord tones', () => {
    const table = Object.fromEntries(INTERVALS.map((i) => [i.name, intervalCents(i)]));
    close(table.minorSecond.cents, 111.7, 0.05, '16:15');
    close(table.minorSecond.offset, 11.7, 0.05, '16:15 against the piano');
    close(table.minorSixth.offset, 13.7, 0.05, '8:5');
    close(table.harmonicSeventh.offset, -31.2, 0.05, '7:4');
    close(table.minorSeventh.offset, 17.6, 0.05, '9:5');
    // Each chord tone's ratio is one of these intervals.
    const ratios = new Set(INTERVALS.map((i) => `${i.semis}:${i.ratio[0] / i.ratio[1]}`));
    for (const [quality, tones] of Object.entries(RATIOS)) {
        for (const [semis, ratio] of Object.entries(tones)) {
            if (Number(semis) < 12 && quality !== 'aug') assert(ratios.has(`${semis}:${ratio}`), `${quality} ${semis}`);
        }
    }
});

test('pitch: Hz and MIDI with any A4, and the nearest note in cents', () => {
    close(midiToHz(69), 440, 1e-9, 'A4');
    close(midiToHz(69, 415), 415, 1e-9, 'baroque A');
    close(midiToHz(60, 440, 100), midiToHz(61), 1e-9, 'cents');
    close(hzToMidi(415, 415), 69, 1e-9, 'A at A=415');
    const n = nearestNote(445, 440);
    assert(n.midi === 69, `445 Hz is an A: ${n.midi}`);
    close(n.cents, 19.56, 0.05, '445 Hz is 19.6 cents sharp');
    const flat = nearestNote(midiToHz(40, 440, -49));
    assert(flat.midi === 40 && flat.cents < -48.9, 'just under half a semitone flat stays on its note');
});

test('notes: a tuner names notes in letters, fixed do or German, sharps or flats', () => {
    const show = (m, o) => { const p = pitchName(m, o); return `${p.name}${p.octave}`; };
    assert(show(60) === 'C4' && show(61) === 'C♯4' && show(61, { flats: true }) === 'D♭4', 'letters');
    assert(show(67, { naming: 'fixed-do' }) === 'Sol4' && show(71, { naming: 'fixed-do' }) === 'Si4', 'fixed do');
    assert(show(71, { naming: 'german' }) === 'H4' && show(70, { naming: 'german', flats: true }) === 'B4' && show(63, { naming: 'german', flats: true }) === 'Es4', 'German');
    assert(show(28) === 'E1' && show(23) === 'B0', 'low octaves: E1 is the bass guitar\'s lowest string');
});

test('click sounds: every sound has a beat and a higher accent, short and below clipping', () => {
    const rate = 48000;
    for (const kind of CLICK_SOUNDS) {
        const beat = clickSound(kind, rate);
        const accent = clickSound(kind, rate, { accent: true });
        const peak = (x) => x.reduce((m, v) => Math.max(m, Math.abs(v)), 0);
        assert(beat.length > 0 && beat.length <= rate * 0.3, `${kind}: ${beat.length} samples`);
        assert(peak(beat) > 0.2 && peak(beat) < 0.9 && peak(accent) < 0.9, `${kind}: peak ${peak(beat)}`);
        // Zero crossings count pitch: the accent is higher.
        const crossings = (x) => x.reduce((k, v, i) => k + (i && Math.sign(v) !== Math.sign(x[i - 1]) ? 1 : 0), 0);
        assert(crossings(accent.subarray(0, 960)) > crossings(beat.subarray(0, 960)), `${kind}: accent higher`);
        assert(Math.abs(beat[beat.length - 1]) < 0.05, `${kind}: dies away`);
    }
});

/** A pretend AudioContext whose clock the test moves, firing each silent pacer's `ended` as it passes. */
function fakeClock() {
    const pacers = [];
    const c = {
        currentTime: 0,
        destination: {},
        createConstantSource() {
            const node = { offset: { value: 1 }, connect() {}, start() {}, stop(at) { if (at !== undefined) node.end = at; }, onended: null };
            pacers.push(node);
            return node;
        },
        advance(to) {
            while (c.currentTime < to) {
                c.currentTime = Math.min(to, c.currentTime + 0.01);
                for (const p of pacers.splice(0)) {
                    if (p.end !== undefined && p.end <= c.currentTime) p.onended?.();
                    else pacers.push(p);
                }
            }
        },
    };
    return c;
}

test('ticker: steps come a little ahead, evenly on the clock, and a change lands on the next step', () => {
    const c = fakeClock();
    const times = [];
    let gap = 0.5;
    const t = ticker((time) => { times.push(time); return gap; }, { at: 1, ahead: 0.3, context: c });
    c.advance(3.05);
    assert(times.join() === '1,1.5,2,2.5,3', `scheduled ${times.join()}`);
    gap = 0.25;
    c.advance(4.05);
    // 3.5 was asked for (before 3.3 + 0.3) with the old gap still to come; from it on, the new one.
    assert(times.slice(5).join() === '3.5,3.75,4,4.25', `after the change ${times.slice(5).join()}`);
    t.stop();
    const before = times.length;
    c.advance(6);
    assert(times.length === before && !t.running, 'stops');
});

test('ticker: a step can end it, and steps missed while the clock was away come late and quiet', () => {
    const c = fakeClock();
    const late = [];
    let count = 0;
    const t = ticker((time, n, isLate) => { late.push(isLate); count = n + 1; return n < 9 ? 0.1 : null; }, { at: 0, context: c });
    assert(late.slice(0, 3).every((x) => !x), 'on time at the start');
    c.currentTime = 0.75; // the clock jumped (a phone woke up) without the pacer firing in between
    c.advance(2);
    assert(count === 10 && !t.running, `ended after the tenth step: ${count}`);
    assert(late.some((x) => x), 'the steps the clock skipped past were late');
});

test('tones: every timbre makes a voice, a held chord retunes and resizes, a fork and a sample sound', async () => {
    const c = audio();
    for (const timbre of TIMBRES) voice(220, { timbre, level: 0.001 }).stop();
    const chord = held([220, 275, 330], { level: 0.001, timbre: 'sine' });
    assert(chord.notes.length === 3, 'three voices');
    chord.setNotes([196, 245], [0, -13.7]);
    assert(chord.notes.join() === '196,245', `two after setNotes: ${chord.notes}`);
    chord.setNotes([196, 245, 294, 343]);
    assert(chord.notes.length === 4, 'four after growing');
    chord.stop();
    const fork = strike(440, { level: 0.001, decay: 0.2 });
    const pressed = strike(440, { level: 0.001, decay: 0.2, hold: true });
    pressed.ring(); // let go: rings out over its decay
    const string = pluck(110, { level: 0.001, decay: 0.3 });
    const tick = sound(new Float32Array(480).fill(0.001), { at: c.currentTime + 0.01, level: 0.5 });
    const guard = voice(20, { level: 0 });
    guard.stop(3);
    const ok = await Promise.race([Promise.all([fork.ended, pressed.ended, string.ended, tick.ended, chord.ended]).then(() => true), guard.ended.then(() => false)]);
    assert(ok, 'the fork dies away, the sample ends and the chord fades out on the audio clock');
});
