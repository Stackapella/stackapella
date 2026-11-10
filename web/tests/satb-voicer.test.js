// The four-part harmony voicer (js/public/tools/satb-voicer.js): chords read as the arranger's
// qualities, a smooth soprano, and alto, tenor and bass from js/arrange.js, checked as a part-writing
// teacher would: in range, no crossing, the third there, no parallel fifths or octaves between any two parts.
import { keyOf, mountSatbVoicer, readChords, sopranoLine, voiceSatb, WORDS } from '/js/public/tools/satb-voicer.js';
import { TONES } from '/js/arrange.js';
import { assert, test } from './harness.js';

const pc = (n) => ((n % 12) + 12) % 12;
const RANGES = { soprano: [60, 79], alto: [53, 74], tenor: [45, 69], bass: [38, 62] };

function check(progression, style = 'simple') {
    const { chords } = readChords(progression);
    const v = voiceSatb(chords, { style });
    const parts = ['soprano', 'alto', 'tenor', 'bass'];
    chords.forEach((c, i) => {
        const notes = parts.map((p) => v[p][i]);
        notes.forEach((n, k) => assert(n >= RANGES[parts[k]][0] && n <= RANGES[parts[k]][1], `${progression}: ${parts[k]} ${n} in range at ${i}`));
        assert(notes[0] >= notes[1] && notes[1] >= notes[2] && notes[2] >= notes[3], `${progression}: no crossing at ${i}: ${notes}`);
        const tones = TONES[c.quality].map((t) => pc(c.root + t));
        assert(notes.every((n) => tones.includes(pc(n))), `${progression}: only chord tones at ${i}: ${notes}`);
        assert(notes.some((n) => pc(n) === tones[1]), `${progression}: the third at ${i}: ${notes}`);
        // Four voices always singing (the arranger's four): a triad complete, a seventh never doubled.
        if (tones.length === 3) assert(tones.every((t) => notes.some((n) => pc(n) === t)), `${progression}: a complete triad at ${i}: ${notes}`);
        if (tones.length === 4) assert(notes.filter((n) => pc(n) === tones[3]).length <= 1, `${progression}: the seventh not doubled at ${i}: ${notes}`);
        if (i) {
            for (let a = 0; a < 4; a++) {
                for (let b = a + 1; b < 4; b++) {
                    const was = pc(v[parts[a]][i - 1] - v[parts[b]][i - 1]);
                    const now = pc(v[parts[a]][i] - v[parts[b]][i]);
                    const moved = v[parts[a]][i] !== v[parts[a]][i - 1] && v[parts[b]][i] !== v[parts[b]][i - 1];
                    assert(!(moved && was === now && (now === 0 || now === 7)), `${progression}: parallel ${now ? 'fifths' : 'octaves'} between ${parts[a]} and ${parts[b]} into ${i}`);
                }
            }
        }
    });
    return v;
}

test('four-part voicer: chords read as the arranger\'s qualities, the key from the last chord', () => {
    const { chords, unknown } = readChords('C | Am, F G7/B  Cmaj7 Dm7 Bø E9 hello');
    assert(chords.map((c) => c.quality).join() === 'maj,min,maj,dom7,maj7,min7,hdim7,dom7' && unknown.join() === 'hello', chords.map((c) => c.quality).join());
    assert(chords[3].bass === 11, 'the slash bass is read');
    assert(JSON.stringify(keyOf(readChords('Am Dm E7 Am').chords)) === '{"tonic":9,"mode":"minor"}', 'A minor');
    const s = sopranoLine(readChords('C F G C').chords);
    assert(s.every((p) => p >= 62 && p <= 77) && pc(s.at(-1)) === 0, `the soprano in range, ending on C: ${s}`);
    assert(s.slice(1).every((p, i) => Math.abs(p - s[i]) <= 5), `smooth: ${s}`);
    // The top line resolves: G7's seventh steps down, and B (the leading tone) rises to C.
    const g7 = readChords('C G7 C').chords;
    const top = sopranoLine(g7);
    assert(!(pc(top[1]) === 5) || pc(top[2]) === 4, `a seventh on top steps down: ${top}`);
    assert(!(pc(top[1]) === 11) || top[2] === top[1] + 1, `the leading tone on top rises: ${top}`);
    const forced = readChords('F G7 C').chords;
    const f = sopranoLine(forced);
    assert(f.every((p, i) => !i || !(pc(f[i - 1]) === 5 && forced[i - 1].quality === 'dom7') || pc(p) === 4), `F G7 C: ${f}`);
});

test('four-part voicer: common progressions voice cleanly, as a part-writing teacher checks them', () => {
    check('C Am F G7 C');
    check('G Em C D7 G');
    check('Am Dm E7 Am');
    check('F Bb C7 F', 'warm');
    const roots = check('C Am F G7 C');
    assert(roots.bass.every((b, i) => pc(b) === readChords('C Am F G7 C').chords[i].root), `every root in the bass: ${roots.bass}`);
    check('D G A7 D Bm Em A7 D');
    // A slash chord keeps its bass: the line walks down C, B, A, A (the arranger's slashBass).
    const walk = check('C G/B Am F/A G7 C');
    assert(pc(walk.bass[1]) === 11 && pc(walk.bass[3]) === 9, `G/B and F/A keep their bass: ${walk.bass}`);
    const v = check('C F G7 C');
    assert(pc(v.soprano.at(-1)) === 0 && pc(v.bass.at(-1)) === 0, 'it ends home, on C top and bottom');
});

test('four-part voicer: the screen draws the staff, names the notes, leaves a part out, and says what it couldn\'t read', () => {
    try {
        localStorage.removeItem('stackapella.tools.four-part-harmony');
    } catch (error) {
        console.warn('storage', error);
    }
    const words = { ...Object.fromEntries(WORDS.map((k) => [k, `[${k}]`])), sample: 'C F G7 C', unknown: 'not a chord: :chord', inKey: 'in :key', staffLabel: ':n chords' };
    const el = document.createElement('div');
    document.body.append(el);
    const voicer = mountSatbVoicer(el, { words });
    const card = el.querySelector('[data-ui=tool]');
    const $ = (s) => card.querySelector(s);
    assert(card.dataset.voiced === 'true' && $('[data-staff] svg [data-column]') && card.querySelectorAll('[data-staff] [data-column]').length === 4, 'the sample, voiced');
    assert(card.querySelectorAll('[data-staff] ellipse').length === 16 && card.querySelectorAll('[data-rows] tr').length === 4, 'sixteen notes, four parts');
    assert($('[data-ui=tool-stat]').textContent === 'in C', $('[data-ui=tool-stat]').textContent);
    $('[data-part="alto"]').click();
    assert(card.querySelectorAll('[data-staff] ellipse').length === 12 && $('[data-part="alto"]').getAttribute('aria-pressed') === 'false', 'the alto left out');
    voicer.set('C Xyz G');
    assert($('[data-ui=tool-status]').textContent.includes('not a chord: Xyz'), $('[data-ui=tool-status]').textContent);
    voicer.stop();
    el.remove();
});
