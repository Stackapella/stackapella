// Metre changes (docs/meters-plan.md): a song whose time signature changes has its bar lines, accents, count-in, staff,
// printed score and exports where its bars fall. The fixture is the parked Here We Come A-wassailing: verse 1 in 6/8,
// two chorus phrases in 4/4, verse 2 begun in 6/8 again (web/tests/fixtures/wassail-meters.json). A song with no
// `meters` takes exactly the old path.
import { Engine } from '/engine/engine.js';
import { toMidi } from '/js/midi.js';
import { barsOf, DIVISIONS, quartersAt, toMusicXML } from '/js/musicxml.js';
import { withClicks } from '/js/practice.js';
import { printLayout } from '/js/print-score.js';
import { staffLayout } from '/js/sing.js';
import { validateTemplate } from '/js/templates.js';
import { barAt, bars, beatsPerBar, meterAt } from '/js/theory.js';
import { assert, test } from './harness.js';

const fixture = () => fetch('/tests/fixtures/wassail-meters.json').then((r) => r.json());
const oldDownbeat = (beat, perBar, pickup) => (((beat - pickup) % perBar) + perBar) % perBar === 0;

test('meters: a song in one metre has its bar lines where they always were, count-in included', () => {
    for (const timeSignature of ['4/4', '3/4', '6/8', '2/4']) {
        const perBar = beatsPerBar(timeSignature);
        for (const pickupBeats of [0, 1, perBar - 1].filter((p) => p >= 0 && p < perBar)) {
            for (let beat = -2 * perBar; beat < 6 * perBar; beat++) {
                const bar = barAt({ timeSignature, pickupBeats }, beat);
                assert(bar.downbeat === oldDownbeat(beat, perBar, pickupBeats), `${timeSignature} pickup ${pickupBeats} beat ${beat}: ${JSON.stringify(bar)}`);
                assert(bar.beats === perBar && bar.timeSignature === timeSignature, `${timeSignature} beat ${beat}: ${JSON.stringify(bar)}`);
            }
        }
    }
});

test('meters: two changes, the bars run in each metre from where it starts', async () => {
    const song = await fixture();
    const list = bars(song, 40).map((b) => `${b.start}:${b.beats}:${b.timeSignature}`);
    const want = [0, 2, 4, 6, 8, 10, 12, 14].map((s) => `${s}:2:6/8`).concat([16, 20, 24, 28].map((s) => `${s}:4:4/4`), [32, 34, 36, 38].map((s) => `${s}:2:6/8`));
    assert(list.join() === want.join(), list.join());
    assert(meterAt(song, 15.5) === '6/8' && meterAt(song, 16) === '4/4' && meterAt(song, 31) === '4/4' && meterAt(song, 32) === '6/8', 'meterAt');
    assert(barAt(song, 22).start === 20 && !barAt(song, 22).downbeat && barAt(song, 24).downbeat, 'a 4/4 bar');
    assert(barAt(song, -1).start === -2 && barAt(song, -2).downbeat, 'the count-in before the song runs in its first metre');
    // A pickup: the short first bar, then the metre's bars from the pickup.
    const pickup = { timeSignature: '3/4', pickupBeats: 1, meters: [{ beat: 7, timeSignature: '2/4' }] };
    assert(bars(pickup, 11).map((b) => `${b.start}:${b.beats}`).join() === '0:1,1:3,4:3,7:2,9:2', bars(pickup, 11).map((b) => `${b.start}:${b.beats}`).join());
});

test('meters: the song check takes the fixture, and refuses a change off a bar line, out of order or to the same metre', async () => {
    const song = await fixture();
    validateTemplate(structuredClone(song)); // throws on a problem
    const refuses = (meters, what) => {
        let error = null;
        try {
            validateTemplate({ ...structuredClone(song), meters });
        } catch (e) {
            error = e;
        }
        assert(error, `took ${what}`);
    };
    refuses([{ beat: 15, timeSignature: '4/4' }], 'a change mid-bar (6/8 bars at even beats)');
    refuses([{ beat: 16, timeSignature: '4/4' }, { beat: 10, timeSignature: '6/8' }], 'changes out of order');
    refuses([{ beat: 16, timeSignature: '6/8' }], 'a change to the same metre');
    refuses([{ beat: 16, timeSignature: '5/4' }], 'a metre the app has no beat for');
    refuses([], 'an empty list');
    refuses([{ beat: 16, timeSignature: '4/4' }, { beat: 30, timeSignature: '6/8' }], 'a change mid 4/4 bar');
});

test("meters: the engine accents each metre's bar lines, and counts in the metre it leads into", async () => {
    const song = await fixture();
    const grid = { beatsPerBar: 2, pickupBeats: 0, meters: song.meters, timeSignature: song.timeSignature };
    const accents = [];
    for (let beat = 0; beat < 40; beat++) if (Engine.prototype.isBarLine(beat, grid)) accents.push(beat);
    assert(accents.join() === [0, 2, 4, 6, 8, 10, 12, 14, 16, 20, 24, 28, 32, 34, 36, 38].join(), accents.join());
    // One metre: the old grid, untouched.
    const plain = { beatsPerBar: 3, pickupBeats: 1, meters: null };
    for (let beat = -3; beat < 12; beat++) assert(Engine.prototype.isBarLine(beat, plain) === oldDownbeat(beat, 3, 1), `plain ${beat}`);
    // Recording from the chorus (beat 16, a 4/4 bar) with a bar's count-in: four clicks, the first accented, as play() lays them.
    const bar = barAt(song, 16);
    const countGrid = { beatsPerBar: bar.beats, pickupBeats: bar.start, meters: null };
    const clicks = [12, 13, 14, 15].map((b) => (Engine.prototype.isBarLine(b, countGrid) ? 'A' : 'n')).join('');
    assert(bar.beats === 4 && clicks === 'Annn', `${bar.beats} ${clicks}`);
});

test("meters: the practice track's clicks accent the bar lines as they change", async () => {
    const song = await fixture();
    const sampleRate = 1000;
    const beatSeconds = 0.1; // 100 samples a beat
    const silent = { channels: [new Float32Array(40 * 100)], sampleRate };
    const out = withClicks(silent, { beatSeconds, beatsPerBar: 2, pickupBeats: 0, meters: song.meters, timeSignature: song.timeSignature, accent: new Float32Array([1]), normal: new Float32Array([0.25]) });
    const lead = 2 * 100; // a bar of count-in before the song
    const accented = [];
    for (let beat = 0; beat < 40; beat++) if (out.channels[0][lead + beat * 100] > 0.4) accented.push(beat);
    assert(accented.join() === [0, 2, 4, 6, 8, 10, 12, 14, 16, 20, 24, 28, 32, 34, 36, 38].join(), accented.join());
});

test('meters: the staff draws bar lines where the bars fall, and writes the new time signature', async () => {
    const song = await fixture();
    const notes = song.parts[0].guide.notes.filter((n) => n.beat >= 12 && n.beat < 22);
    const laid = staffLayout(notes, { key: song.key, beatsPerBar: 2, pickupBeats: 0, timeSignature: song.timeSignature, meters: song.meters });
    assert(laid.bars.join() === '14,16,20', laid.bars.join());
    assert(laid.changes.length === 1 && laid.changes[0].beat === 16 && laid.changes[0].timeSignature === '4/4', JSON.stringify(laid.changes));
    // Without meters: every second beat, as before (18 among them, where the chorus has none), and nothing written.
    const plain = staffLayout(notes, { key: song.key, beatsPerBar: 2, pickupBeats: 0 });
    assert(plain.bars.every((b, i) => b === 14 + 2 * i) && plain.bars.includes(18) && plain.changes.length === 0, plain.bars.join());
});

test('meters: MusicXML writes each bar in its own metre, with the time signature and tempo where it changes', async () => {
    const song = await fixture();
    assert(quartersAt(song, 16) === 24 && quartersAt(song, 32) === 40 && quartersAt(song, 34) === 43, `${quartersAt(song, 16)} ${quartersAt(song, 32)} ${quartersAt(song, 34)}`);
    const list = barsOf(song);
    assert(list.length === 16, `${list.length} bars`);
    assert(list.slice(0, 8).every((b) => b.length === 3 * DIVISIONS) && list.slice(8, 12).every((b) => b.length === 4 * DIVISIONS) && list.slice(12).every((b) => b.length === 3 * DIVISIONS), JSON.stringify(list.map((b) => b.length)));
    const xml = toMusicXML(song);
    const doc = new DOMParser().parseFromString(xml, 'application/xml');
    const first = doc.querySelector('part');
    const times = [...first.querySelectorAll('measure')].map((m, i) => (m.querySelector('time') ? `${i}:${m.querySelector('beats').textContent}/${m.querySelector('beat-type').textContent}` : null)).filter(Boolean);
    assert(times.join() === '0:6/8,8:4/4,12:6/8', times.join());
    assert(first.querySelectorAll('metronome').length === 3, 'a tempo mark at the start and each change');
    // Every measure's notes fill it exactly (no note spills over a changed bar line).
    for (const part of doc.querySelectorAll('part')) {
        [...part.querySelectorAll('measure')].forEach((m, i) => {
            const sum = [...m.querySelectorAll('note')].filter((n) => !n.querySelector('chord')).reduce((s, n) => s + Number(n.querySelector('duration').textContent), 0);
            assert(sum === list[i].length, `${part.id} measure ${i}: ${sum} of ${list[i].length}`);
        });
    }
});

test('meters: MIDI marks each change with its time signature and tempo', async () => {
    const song = await fixture();
    const bytes = toMidi(song);
    const found = [];
    for (let i = 0; i + 3 < bytes.length; i++) if (bytes[i] === 0xff && bytes[i + 1] === 0x58 && bytes[i + 2] === 4) found.push(`${bytes[i + 3]}/${2 ** bytes[i + 4]}`);
    assert(found.join() === '6/8,4/4,6/8', found.join());
    let tempos = 0;
    for (let i = 0; i + 2 < bytes.length; i++) if (bytes[i] === 0xff && bytes[i + 1] === 0x51 && bytes[i + 2] === 3) tempos++;
    assert(tempos === 3, `${tempos} tempo events`);
});

test("meters: the printed score writes the new time signature at the changed bars, and what the beat was", async () => {
    const song = await fixture();
    const layout = printLayout(song, { partKeys: ['soprano'] });
    const changed = layout.systems.flatMap((s) => s.bars.filter((b) => b.time).map((b) => `${b.index}:${b.time.beats}/${b.time.beatType}:${b.time.from}>${b.time.to}`));
    assert(changed.join() === '8:4/4:dotted>plain,12:6/8:plain>dotted', changed.join());
    // Each note sits inside its bar, after the room for a time signature.
    for (const sys of layout.systems) {
        for (const st of sys.staves) {
            for (const item of st.items) {
                const bar = sys.bars.find((b) => item.x >= b.x && item.x <= b.x + b.width);
                assert(bar, `a note at ${item.x} outside every bar`);
                if (bar.time) assert(item.x > bar.x + 18, `a note on the time signature at bar ${bar.index}`);
            }
        }
    }
});
