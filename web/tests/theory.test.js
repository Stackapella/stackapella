// The song builder's music theory (js/theory.js; docs/song-builder-plan.md): numerals, every
// progression and blues form in tune with itself, choruses that start elsewhere and land home,
// bridges that end on V, and a key that suits a singer's range.
import { BLUES_FORMS, blues, chordName, chordOf, chordsFor, FAMILIES, FEELS, keyName, landHome, lastChorus, parseNumeral, PROGRESSIONS, suggestBridge, suggestChorus, chordRole, nextKey, suggestKey, suggestPreChorus, TURNAROUNDS, VOICE_RANGES } from '/js/theory.js';
import { assert, test } from './harness.js';

const isHome = (n) => n === 'I' || n === 'i' || n === 'I7';

test('theory: Roman numerals read as steps and qualities', () => {
    const cases = { I: [0, 'maj'], ii: [2, 'min'], iii: [4, 'min'], IV: [5, 'maj'], V: [7, 'maj'], vi: [9, 'min'], 'vii°': [11, 'dim'], bVII: [10, 'maj'], bVI: [8, 'maj'], bIII: [3, 'maj'], bII: [1, 'maj'], V7: [7, 'dom7'], ii7: [2, 'min7'], Imaj7: [0, 'maj7'], iv: [5, 'min'], VI7: [9, 'dom7'], i: [0, 'min'] };
    for (const [numeral, [step, quality]] of Object.entries(cases)) {
        const parsed = parseNumeral(numeral);
        assert(parsed?.step === step && parsed.quality === quality, `${numeral}: ${JSON.stringify(parsed)}`);
    }
    for (const bad of ['', 'VIII', 'x', 'Iv', 'bb', null]) assert(parseNumeral(bad) === null, `${bad} is not a numeral`);
});

test('theory: chords in a key are spelled for that key', () => {
    assert(chordName(chordOf('vi', 0)) === 'Am', 'vi in C is Am');
    assert(chordName(chordOf('IV', 10), { tonic: 10, mode: 'major' }) === 'Eb', 'IV in Bb is Eb');
    assert(chordName(chordOf('V7', 7), { tonic: 7, mode: 'major' }) === 'D7', 'V7 in G is D7');
    assert(chordName(chordOf('bVII', 2), { tonic: 2, mode: 'minor' }) === 'C', 'bVII in Dm is C');
    assert(chordName(chordOf('iii', 2), { tonic: 2, mode: 'major' }) === 'F#m', 'iii in D is F#m');
    assert(keyName({ tonic: 3, mode: 'major' }) === 'Eb' && keyName({ tonic: 6, mode: 'minor' }) === 'F#m', 'key names');
    // Spelled by letter: flats come out as flats, sharps as sharps.
    const spell = (numeral, tonic, mode = 'major') => chordName(chordOf(numeral, tonic), { tonic, mode });
    assert(spell('bVII', 0) === 'Bb' && spell('bVI', 0) === 'Ab' && spell('bIII', 0) === 'Eb' && spell('bII', 0) === 'Db', `flats in C: ${spell('bVII', 0)}`);
    assert(spell('bVI', 7) === 'Eb' && spell('bIII', 7) === 'Bb', 'flats in G');
    assert(spell('bVI', 9) === 'F' && spell('iii', 9) === 'C#m', 'A major: its bVI is F, its iii C#m');
    assert(spell('iv', 3, 'minor') === 'Abm' && spell('V7', 3, 'minor') === 'Bb7', `Ebm: ${spell('iv', 3, 'minor')} ${spell('V7', 3, 'minor')}`);
    assert(spell('#iv°7', 0) === 'F#dim7', 'a diminished seventh');
});

test('theory: every progression parses, belongs to a family, and starts where its mode says', () => {
    const families = new Set(FAMILIES.map((f) => f.id));
    const away = new Set(['sad-four', 'four-one-five-six', 'two-five-one', 'circle', 'rising-minor', 'minor-plea', 'royal-road', 'climb']); // these start away from home on purpose
    for (const p of PROGRESSIONS) {
        assert(families.has(p.family), `${p.id}: family ${p.family}`);
        for (const bar of p.bars) for (const n of bar) assert(parseNumeral(n), `${p.id}: ${n}`);
        if (!away.has(p.id)) assert(isHome(p.bars[0][0]) && (p.mode === 'minor') === (p.bars[0][0] === 'i'), `${p.id} starts on ${p.bars[0][0]}`);
        assert(p.name.startsWith('theory.prog.') && p.feel.startsWith('theory.prog.'), `${p.id}: words`);
    }
    assert(new Set(PROGRESSIONS.map((p) => p.id)).size === PROGRESSIONS.length, 'ids are unique');
});

test('theory: the blues forms, turnarounds and feels', () => {
    const plain = blues({ form: 'twelve', turnaround: 'plain', feel: 'shuffle' });
    assert(plain.bars.length === 12 && plain.bars.map((b) => b.join(' ')).join('|') === 'I7|I7|I7|I7|IV7|IV7|I7|I7|V7|IV7|I7|V7', plain.bars.join('|'));
    assert(plain.swing > 0.6, 'a shuffle swings');
    const quick = blues({ form: 'quick' });
    assert(quick.bars[1][0] === 'IV7', 'a quick change visits IV in bar 2');
    const jazz = blues({ form: 'twelve', turnaround: 'jazz', feel: 'straight' });
    assert(jazz.bars.at(-2).join(' ') === 'I bIII7' && jazz.bars.at(-1).join(' ') === 'bVI7 bII7' && jazz.swing === 0.5, `jazz turns around: ${jazz.bars.slice(-2).join('|')}`);
    assert(jazz.bars[0][0] === 'I', 'straight blues is plain triads');
    const minor = blues({ form: 'minor', turnaround: 'walk' });
    assert(minor.mode === 'minor' && minor.bars[0][0] === 'i7' && minor.bars[8][0] === 'bVI7' && minor.bars.at(-1).join(' ') === 'bVI7 V7', `minor: ${minor.bars.join('|')}`);
    assert(JSON.stringify(blues({ form: 'minor', turnaround: 'jazz' }).bars) === JSON.stringify(minor.bars) && BLUES_FORMS.minor.fixed, 'a minor blues keeps its own turnaround');
    assert(blues({ form: 'sixteen' }).bars.length === 16 && blues({ form: 'eight' }).bars.length === 8, 'lengths');
    const gospel = blues({ form: 'twelve', feel: 'gospel' });
    assert(gospel.bars[9].join(' ') === 'IV iv' && gospel.bars[5].join(' ') === 'IV7 #iv°7', `gospel: ${gospel.bars[5]} / ${gospel.bars[9]}`);
    for (const form of Object.keys(BLUES_FORMS)) for (const turn of Object.keys(TURNAROUNDS)) for (const feel of Object.keys(FEELS)) {
        for (const bar of blues({ form, turnaround: turn, feel }).bars) for (const n of bar) assert(parseNumeral(n), `${form} ${turn} ${feel}: ${n}`);
    }
});

test("theory: a section's chords fall on their beats", () => {
    const canon = PROGRESSIONS.find((p) => p.id === 'canon');
    const chords = chordsFor(canon.bars, { tonic: 7, perBar: 4 });
    assert(chords.map((c) => c.beat).join() === '0,2,4,6,8,10,12,14', chords.map((c) => c.beat).join());
    assert(chords[2].root === 4 && chords[2].quality === 'min', 'vi in G is Em, on beat 4');
    const waltz = chordsFor([['I'], ['V']], { tonic: 0, perBar: 3, startBeat: 1 });
    assert(waltz[1].beat === 4, 'a pickup moves it along');
});

test('theory: a section ends leading on, never longer; the last chorus closes home', () => {
    const hook = [['I'], ['V'], ['vi'], ['IV']];
    assert(landHome(hook) === hook, 'ending on IV leads on: left as it is');
    const open = landHome([['I'], ['IV'], ['V'], ['vi']]);
    assert(open.length === 4 && open.at(-1)[0] === 'V', open.join('|'));
    assert(landHome([['i'], ['bVI'], ['bIII'], ['bVII']]).at(-1)[0] === 'bVII', 'modal bVII leads home too');
    const last = lastChorus(hook);
    assert(last.length === 4 && last.at(-2).join(' ') === 'IV V' && last.at(-1)[0] === 'I', last.join('|'));
    assert(lastChorus(hook, { triumphant: true }).at(-2).join(' ') === 'bVI bVII', 'the triumphant close');
    assert(lastChorus([['i'], ['iv'], ['bVI'], ['bVII']], { mode: 'minor' }).at(-1)[0] === 'i', 'a minor close');
});

test('theory: three choruses for every verse, each starting elsewhere and landing home', () => {
    for (const verse of [...PROGRESSIONS, blues({ form: 'twelve' }), blues({ form: 'minor' })]) {
        const choruses = suggestChorus(verse);
        assert(choruses.length === 3, `${verse.id}: ${choruses.length} choruses`);
        assert(new Set(choruses.map((c) => JSON.stringify(c.bars))).size === 3, `${verse.id}: the same chorus twice`);
        for (const c of choruses) {
            const step = (n) => parseNumeral(n).step;
            if (c.kind !== 'classic') assert(step(c.bars[0][0]) !== step(verse.bars[0][0]), `${verse.id} ${c.kind}: starts where the verse does (${c.bars[0][0]})`);
            assert(c.mode === verse.mode, `${verse.id} ${c.kind}: stays in the verse's mode`);
            assert([0, 5, 7, 10].includes(step(c.bars.at(-1).at(-1))), `${verse.id} ${c.kind}: ends on ${c.bars.at(-1).at(-1)}, which leads nowhere`);
            assert(JSON.stringify(c.bars) !== JSON.stringify(verse.bars), `${verse.id} ${c.kind}: the verse again`);
            assert(c.raise > 0 && c.why.startsWith('theory.why.'), `${verse.id} ${c.kind}: sung higher, with a reason`);
            for (const bar of c.bars) for (const n of bar) assert(parseNumeral(n), `${verse.id} ${c.kind}: ${n}`);
        }
    }
    const four = suggestChorus(PROGRESSIONS.find((p) => p.id === 'four-chords'));
    assert(four[0].kind === 'lift' && four[0].bars[0][0] === 'IV', `the four chords' lift starts on IV: ${four[0].bars[0]}`);
    // A major verse never gets a chorus in the parallel minor.
    for (const verse of PROGRESSIONS.filter((p) => p.mode === 'major')) {
        for (const c of suggestChorus(verse)) assert(!c.bars.flat().some((n) => /^i(?!i|v)/.test(n)), `${verse.id}: a minor tonic in its chorus`);
    }
    // A minor verse's contrast is its relative major, in its own numerals.
    const andalusian = suggestChorus(PROGRESSIONS.find((p) => p.id === 'andalusian'));
    assert(andalusian.find((c) => c.kind === 'contrast')?.bars[0][0] === 'bIII', 'the relative major');
    assert(suggestPreChorus(PROGRESSIONS[0]).every((p) => p.bars.at(-1)[0] === 'V'), 'a pre-chorus climbs to V');
});

test('theory: bridges start somewhere new and end on V', () => {
    for (const verse of PROGRESSIONS) {
        const chorus = suggestChorus(verse)[0];
        const bridges = suggestBridge(verse, chorus);
        assert(bridges.length >= 2, `${verse.id}: ${bridges.length} bridges`);
        for (const b of bridges) {
            assert(b.bars.at(-1).at(-1) === 'V' && b.bars.at(-2).at(-1) === 'V' && b.bars.length === 8, `${verse.id}: a bridge is eight bars, V held at the end`);
            const step = (n) => parseNumeral(n).step;
            assert(step(b.bars[0][0]) !== step(verse.bars[0][0]) && step(b.bars[0][0]) !== step(chorus.bars[0][0]), `${verse.id}: a bridge starts where the verse or chorus does`);
        }
    }
});

test('theory: the key for a singer puts a typical melody inside their range, with Higher and Lower', () => {
    for (const [type, range] of Object.entries(VOICE_RANGES)) {
        for (const mode of ['major', 'minor']) {
            const key = suggestKey(range, { mode });
            assert(key.fits, `${type} ${mode}: ${key.name} doesn't fit`);
            assert(key.tonicMidi - 5 >= range.low + 1 && key.tonicMidi + 7 <= range.high - 1, `${type} ${mode}: ${key.tonicMidi} leaves the range`);
            assert(key.higher.tonicMidi > key.tonicMidi && key.lower.tonicMidi < key.tonicMidi, `${type}: Higher and Lower`);
        }
    }
    const song = suggestKey(VOICE_RANGES.alto, { sections: ['verse', 'chorus'] });
    assert(song.fits && song.tonicMidi - 5 >= 56 && song.tonicMidi + 9 <= 71, `one key for verse and chorus: ${song.name}`);
    // Untrained singers (a woman A3 to C5, a man A2 to D4) get a key, a friendly one where it can be.
    for (const range of [{ low: 57, high: 72 }, { low: 45, high: 62 }]) {
        const key = suggestKey(range);
        assert(key.fits, `${range.low}-${range.high}: ${key.name}`);
    }
    assert(['C', 'G', 'D', 'A', 'E', 'F', 'Bb'].includes(suggestKey(VOICE_RANGES.alto).name), `a friendly key for an alto: ${suggestKey(VOICE_RANGES.alto).name}`);
    const narrow = suggestKey({ low: 60, high: 66 });
    assert(!narrow.fits && narrow.name, 'a narrow range still gets a key, said not to fit');
});

test('theory: the mobile app\'s test vectors (tests/vectors/theory.json) still match the module', async () => {
    const { vectors } = await (await fetch('/tests/vectors/theory.json')).json();
    // Key order aside (the file's keys are sorted).
    const sorted = (x) => (Array.isArray(x) ? x.map(sorted) : x && typeof x === 'object' ? Object.fromEntries(Object.keys(x).sort().map((k) => [k, sorted(x[k])])) : x);
    const same = (a, b) => JSON.stringify(sorted(a)) === JSON.stringify(sorted(b));
    const find = (id) => PROGRESSIONS.find((p) => p.id === id);
    for (const v of vectors.parseNumeral) assert(same(parseNumeral(v.input), v.output), `parseNumeral ${v.input}: run web/tool/vectors.py`);
    for (const v of vectors.suggestChorus) assert(same(JSON.parse(JSON.stringify(suggestChorus(find(v.input)))), v.output), `suggestChorus ${v.input}: run web/tool/vectors.py`);
    for (const v of vectors.suggestKey) assert(same(JSON.parse(JSON.stringify(suggestKey(v.input.range, v.input))), v.output), `suggestKey ${JSON.stringify(v.input)}: run web/tool/vectors.py`);
    for (const v of vectors.blues) assert(same(JSON.parse(JSON.stringify(blues(v.input))), v.output), `blues ${JSON.stringify(v.input)}: run web/tool/vectors.py`);
    for (const v of vectors.chordRole) assert(same(chordRole(v.input.numeral, v.input.mode), v.output), `chordRole ${JSON.stringify(v.input)}: run web/tool/vectors.py`);
    for (const v of vectors.nextKey) assert(same(JSON.parse(JSON.stringify(nextKey(v.input.range, v.input.from, v.input.direction, v.input))), v.output), `nextKey ${JSON.stringify(v.input)}: run web/tool/vectors.py`);
    for (const v of vectors.suggestKeyOptions) assert(same(JSON.parse(JSON.stringify(suggestKey(v.input.range, v.input))), v.output), `suggestKey ${JSON.stringify(v.input)}: run web/tool/vectors.py`);
    for (const v of vectors.lastChorus) assert(same(lastChorus(v.input.bars, v.input), v.output), `lastChorus: run web/tool/vectors.py`);
    for (const v of vectors.suggestPreChorus) assert(same(JSON.parse(JSON.stringify(suggestPreChorus(find(v.input)))), v.output), `suggestPreChorus ${v.input}: run web/tool/vectors.py`);
    assert(vectors.chordsFor.length && vectors.suggestBridge.length && vectors.chordName.length, 'every function has vectors');
});

test('theory: Lower and Higher step from the key chosen now, so twice goes twice as far (31\'s port)', () => {
    const range = VOICE_RANGES.alto;
    const best = suggestKey(range, { sections: ['verse', 'chorus'] });
    const once = nextKey(range, best.tonicMidi, -1, { sections: ['verse', 'chorus'] });
    assert(once && once.tonicMidi === best.lower.tonicMidi, 'the first step is the suggestion\'s lower');
    const twice = nextKey(range, once.tonicMidi, -1, { sections: ['verse', 'chorus'] });
    assert(twice && twice.tonicMidi < once.tonicMidi, `pressed twice: lower again (${once.name}, then ${twice.name})`);
    const up = nextKey(range, twice.tonicMidi, 1, { sections: ['verse', 'chorus'] });
    assert(up.tonicMidi > twice.tonicMidi, 'and back up');
});

test('theory: each chord\'s job in its key, for the builder\'s chord shapes (86.png)', () => {
    const job = (n, mode) => { const r = chordRole(n, mode); return `${r.role}${r.distance}`; };
    assert(job('I') === 'home0' && job('vi') === 'home1' && job('iii') === 'home1', 'home and its relatives');
    assert(job('IV') === 'lift2' && job('ii') === 'lift2' && job('bVII') === 'lift2', 'the lifts');
    assert(job('V') === 'pull3' && job('V7') === 'pull3' && job('vii°') === 'pull3', 'the pulls home');
    assert(job('i', 'minor') === 'home0' && job('bIII', 'minor') === 'home1' && job('v', 'minor') === 'pull3' && job('iv', 'minor') === 'lift2', 'in minor');
    assert(chordRole('x') === null, 'not a numeral');
});
