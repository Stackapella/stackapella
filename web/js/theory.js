// Music theory for the song builder (docs/song-builder-plan.md) and the arranger (js/arrange.js):
// progressions as Roman numerals by family, the blues (forms, turnarounds, feels), chorus and bridge
// suggestions that belong with a verse, the chords of a section in a key, and a key that suits a
// singer's range. Data plus small pure functions, with no imports, so the mobile app ports it as it
// is and checks itself against the same test vectors (web/tool/vectors.py). Words are i18n keys
// (`name`, `feel`, `why`), worded by the screens.

const tk = (key) => key; // marks a word key for the i18n check (this module imports nothing)

const pc = (n) => ((n % 12) + 12) % 12;
const DEGREES = { I: 0, II: 2, III: 4, IV: 5, V: 7, VI: 9, VII: 11 };
// A chord's tones by quality, in semitones from its root.
export const QUALITIES = {
    maj: [0, 4, 7], min: [0, 3, 7], dim: [0, 3, 6], aug: [0, 4, 8], sus2: [0, 2, 7], sus4: [0, 5, 7],
    dim7: [0, 3, 6, 9], hdim7: [0, 3, 6, 10], dom7: [0, 4, 7, 10], min7: [0, 3, 7, 10], maj7: [0, 4, 7, 11],
    maj6: [0, 4, 7, 9], min6: [0, 3, 7, 9], add9: [0, 4, 7, 14],
};

/**
 * A Roman numeral as `{ step, quality }`: `step` in semitones from the tonic (degrees of the major
 * scale, b or # moving it), the quality from its case and suffix (I major, ii minor, vii°
 * diminished, #iv°7 diminished seventh, V7 dominant, ii7 minor seventh, Imaj7 major seventh, Isus2,
 * Vsus4, I6 major sixth, ii6 minor sixth). Null when it isn't one.
 */
export function parseNumeral(numeral) {
    const m = /^([b#]?)(VII|VI|IV|V|III|II|I|vii|vi|iv|v|iii|ii|i)(°)?(maj7|7|sus2|sus4|6)?$/.exec(numeral ?? '');
    if (!m) return null;
    const [, accidental, roman, dim, seventh] = m;
    const minor = roman === roman.toLowerCase();
    const step = pc(DEGREES[roman.toUpperCase()] + (accidental === 'b' ? -1 : accidental === '#' ? 1 : 0));
    let quality = minor ? 'min' : 'maj';
    if (dim) quality = seventh === '7' ? 'dim7' : 'dim';
    else if (seventh === 'maj7') quality = 'maj7';
    else if (seventh === '7') quality = minor ? 'min7' : 'dom7';
    else if (seventh === 'sus2' || seventh === 'sus4') quality = seventh;
    else if (seventh === '6') quality = minor ? 'min6' : 'maj6';
    return { step, quality };
}

/**
 * A chord's job in its key, for the song builder's chord shapes (Hillel's 86.png): `{ role,
 * distance }`. `role` is 'home' (the tonic, and its relative: they rest), 'pull' (the dominant
 * family: it wants to go home) or 'lift' (everything else: it moves away). `distance` is how far
 * from home it feels, 0 (home) to 3 (the strongest pull), for the shape's height. By scale step
 * from the tonic: I 0, vi and iii 1, IV, ii, bVI, bVII and the rest 2, V and vii 3. In a minor
 * key, i is home, III (the relative major) 1, v and V and the leading-tone chords 3. Null for a
 * numeral that isn't one.
 */
export function chordRole(numeral, mode = 'major') {
    const parsed = parseNumeral(numeral);
    if (!parsed) return null;
    const { step } = parsed;
    if (step === 0) return { role: 'home', distance: 0 };
    if (step === 7 || step === 11) return { role: 'pull', distance: 3 };
    const near = mode === 'minor' ? [3] : [9, 4];
    if (near.includes(step)) return { role: 'home', distance: 1 };
    return { role: 'lift', distance: 2 };
}

/** A numeral in a key (`tonic` a pitch class): `{ root, quality, numeral }`. */
export function chordOf(numeral, tonic) {
    const parsed = parseNumeral(numeral);
    if (!parsed) throw new Error(`Not a Roman numeral: ${numeral}`);
    return { root: pc(tonic + parsed.step), quality: parsed.quality, numeral };
}

const FLATS = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];
const LETTERS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
const NATURAL = [0, 2, 4, 5, 7, 9, 11];
const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];
const SUFFIX = { maj: '', min: 'm', dim: 'dim', aug: 'aug', sus2: 'sus2', sus4: 'sus4', dim7: 'dim7', hdim7: 'm7b5', dom7: '7', min7: 'm7', maj7: 'maj7', maj6: '6', min6: 'm6', add9: 'add9' };

/**
 * A chord's name, spelled by letter for the key it's in (the key's letter plus the numeral's
 * degree), so C's bVII is Bb and A's iii is C#m; a double sharp or flat falls back to the plain
 * flat name. Without its numeral, the flat names.
 */
export function chordName(chord, key = { tonic: 0, mode: 'major' }) {
    const roman = /^[b#]?([ivIV]+)/.exec(chord.numeral ?? '')?.[1]?.toUpperCase();
    const degree = ROMAN.indexOf(roman);
    if (degree < 0) return FLATS[chord.root] + SUFFIX[chord.quality];
    const keyLetter = LETTERS.indexOf(keyName(key)[0]);
    const letter = (keyLetter + degree) % 7;
    const off = pc(chord.root - NATURAL[letter]);
    const accidental = { 0: '', 1: '#', 11: 'b' }[off];
    return (accidental === undefined ? FLATS[chord.root] : LETTERS[letter] + accidental) + SUFFIX[chord.quality];
}

/** A key's name: "Bb", "F#", "Ebm". */
export function keyName(key) {
    const majorNames = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];
    const minorNames = ['Cm', 'C#m', 'Dm', 'Ebm', 'Em', 'Fm', 'F#m', 'Gm', 'G#m', 'Am', 'Bbm', 'Bm']; // i18n-ignore: note and key names
    return (key.mode === 'minor' ? minorNames : majorNames)[pc(key.tonic)];
}

// ---- Progressions ----------------------------------------------------------------------------------

/** The families, in the order the builder shows them. */
export const FAMILIES = [
    { id: 'foundations', name: tk('theory.family.foundations') },
    { id: 'pop', name: tk('theory.family.pop') },
    { id: 'soulful', name: tk('theory.family.soulful') },
    { id: 'minor', name: tk('theory.family.minor') },
    { id: 'classic', name: tk('theory.family.classic') },
    { id: 'folk', name: tk('theory.family.folk') },
    { id: 'blues', name: tk('theory.family.blues') },
];

/**
 * The progressions: `bars` is a list of bars, each the chords sung in it (sharing it equally).
 * `tags` say what each is like, for the chorus and bridge suggestions: bright, sad, warm, open,
 * classic, modal, jazzy.
 */
export const PROGRESSIONS = [
    { id: 'one-four-five', name: tk('theory.prog.oneFourFive.name'), feel: tk('theory.prog.oneFourFive.feel'), family: 'foundations', mode: 'major', bars: [['I'], ['IV'], ['V'], ['I']], tags: ['bright', 'classic'] },
    { id: 'one-four-one-five', name: tk('theory.prog.oneFourOneFive.name'), feel: tk('theory.prog.oneFourOneFive.feel'), family: 'foundations', mode: 'major', bars: [['I'], ['IV'], ['I'], ['V']], tags: ['bright', 'open'] },
    { id: 'one-five-one', name: tk('theory.prog.oneFiveOne.name'), feel: tk('theory.prog.oneFiveOne.feel'), family: 'foundations', mode: 'major', bars: [['I'], ['V'], ['V'], ['I']], tags: ['classic'] },
    { id: 'four-chords', name: tk('theory.prog.fourChords.name'), feel: tk('theory.prog.fourChords.feel'), family: 'pop', mode: 'major', bars: [['I'], ['V'], ['vi'], ['IV']], tags: ['bright', 'open'] },
    { id: 'sad-four', name: tk('theory.prog.sadFour.name'), feel: tk('theory.prog.sadFour.feel'), family: 'pop', mode: 'major', bars: [['vi'], ['IV'], ['I'], ['V']], tags: ['sad', 'open'] },
    { id: 'doo-wop', name: tk('theory.prog.dooWop.name'), feel: tk('theory.prog.dooWop.feel'), family: 'pop', mode: 'major', bars: [['I'], ['vi'], ['IV'], ['V']], tags: ['warm', 'classic'] },
    { id: 'one-four-six-five', name: tk('theory.prog.oneFourSixFive.name'), feel: tk('theory.prog.oneFourSixFive.feel'), family: 'pop', mode: 'major', bars: [['I'], ['IV'], ['vi'], ['V']], tags: ['bright', 'open'] },
    { id: 'four-one-five-six', name: tk('theory.prog.fourOneFiveSix.name'), feel: tk('theory.prog.fourOneFiveSix.feel'), family: 'pop', mode: 'major', bars: [['IV'], ['I'], ['V'], ['vi']], tags: ['bright', 'open'] },
    { id: 'minor-four-ache', name: tk('theory.prog.minorFourAche.name'), feel: tk('theory.prog.minorFourAche.feel'), family: 'soulful', mode: 'major', bars: [['I'], ['iii'], ['IV'], ['iv']], tags: ['warm', 'sad'] },
    { id: 'gospel', name: tk('theory.prog.gospel.name'), feel: tk('theory.prog.gospel.feel'), family: 'soulful', mode: 'major', bars: [['I'], ['I7'], ['IV'], ['iv']], tags: ['warm'] },
    { id: 'two-five-one', name: tk('theory.prog.twoFiveOne.name'), feel: tk('theory.prog.twoFiveOne.feel'), family: 'soulful', mode: 'major', bars: [['ii'], ['V'], ['I'], ['I']], tags: ['jazzy', 'warm'] },
    { id: 'turnaround', name: tk('theory.prog.turnaround.name'), feel: tk('theory.prog.turnaround.feel'), family: 'soulful', mode: 'major', bars: [['I', 'vi'], ['ii', 'V']], tags: ['jazzy', 'warm'] },
    { id: 'andalusian', name: tk('theory.prog.andalusian.name'), feel: tk('theory.prog.andalusian.feel'), family: 'minor', mode: 'minor', bars: [['i'], ['bVII'], ['bVI'], ['V']], tags: ['sad', 'classic'] },
    { id: 'epic-minor', name: tk('theory.prog.epicMinor.name'), feel: tk('theory.prog.epicMinor.feel'), family: 'minor', mode: 'minor', bars: [['i'], ['bVI'], ['bIII'], ['bVII']], tags: ['sad', 'open'] },
    { id: 'minor-home', name: tk('theory.prog.minorHome.name'), feel: tk('theory.prog.minorHome.feel'), family: 'minor', mode: 'minor', bars: [['i'], ['iv'], ['V'], ['i']], tags: ['sad'] },
    { id: 'minor-lift', name: tk('theory.prog.minorLift.name'), feel: tk('theory.prog.minorLift.feel'), family: 'minor', mode: 'minor', bars: [['i'], ['bVI'], ['bVII'], ['i']], tags: ['sad', 'bright'] },
    { id: 'rising-minor', name: tk('theory.prog.risingMinor.name'), feel: tk('theory.prog.risingMinor.feel'), family: 'minor', mode: 'minor', bars: [['bVI'], ['bVII'], ['i'], ['i']], tags: ['bright', 'open'] },
    { id: 'minor-plea', name: tk('theory.prog.minorPlea.name'), feel: tk('theory.prog.minorPlea.feel'), family: 'minor', mode: 'minor', bars: [['iv'], ['bVII'], ['bIII'], ['bVI']], tags: ['sad', 'warm'] },
    { id: 'minor-sway', name: tk('theory.prog.minorSway.name'), feel: tk('theory.prog.minorSway.feel'), family: 'minor', mode: 'minor', bars: [['i'], ['bVII'], ['bVI'], ['bVII']], tags: ['sad', 'open'] },
    { id: 'minor-bright', name: tk('theory.prog.minorBright.name'), feel: tk('theory.prog.minorBright.feel'), family: 'minor', mode: 'minor', bars: [['i'], ['bIII'], ['bVII'], ['IV']], tags: ['bright', 'modal'] },
    { id: 'royal-road', name: tk('theory.prog.royalRoad.name'), feel: tk('theory.prog.royalRoad.feel'), family: 'pop', mode: 'major', bars: [['IV'], ['V'], ['iii'], ['vi']], tags: ['bright', 'open'] },
    { id: 'climb', name: tk('theory.prog.climb.name'), feel: tk('theory.prog.climb.feel'), family: 'pop', mode: 'major', bars: [['IV'], ['V'], ['I'], ['I']], tags: ['bright'] },
    { id: 'party', name: tk('theory.prog.party.name'), feel: tk('theory.prog.party.feel'), family: 'foundations', mode: 'major', bars: [['I'], ['IV'], ['V'], ['IV']], tags: ['bright'] },
    { id: 'barbershop', name: tk('theory.prog.barbershop.name'), feel: tk('theory.prog.barbershop.feel'), family: 'classic', mode: 'major', bars: [['I'], ['VI7'], ['II7'], ['V7']], tags: ['classic', 'jazzy', 'warm'] },
    { id: 'canon', name: tk('theory.prog.canon.name'), feel: tk('theory.prog.canon.feel'), family: 'classic', mode: 'major', bars: [['I', 'V'], ['vi', 'iii'], ['IV', 'I'], ['IV', 'V']], tags: ['classic', 'warm'] },
    { id: 'circle', name: tk('theory.prog.circle.name'), feel: tk('theory.prog.circle.feel'), family: 'classic', mode: 'major', bars: [['vi'], ['ii'], ['V'], ['I']], tags: ['classic', 'jazzy'] },
    { id: 'mixolydian', name: tk('theory.prog.mixolydian.name'), feel: tk('theory.prog.mixolydian.feel'), family: 'folk', mode: 'major', bars: [['I'], ['bVII'], ['IV'], ['I']], tags: ['modal', 'open'] },
    { id: 'dorian-vamp', name: tk('theory.prog.dorianVamp.name'), feel: tk('theory.prog.dorianVamp.feel'), family: 'folk', mode: 'minor', bars: [['i'], ['IV'], ['i'], ['IV']], tags: ['modal'] },
];

/** A progression by id (a blues one too, made from its choices: see blues()). */
export const progression = (id) => PROGRESSIONS.find((p) => p.id === id) ?? null;

// ---- The blues ---------------------------------------------------------------------------------------

/** The blues forms, a chord a bar. A quick change visits IV in bar 2. */
export const BLUES_FORMS = {
    twelve: { name: tk('theory.blues.form.twelve'), mode: 'major', bars: ['I', 'I', 'I', 'I', 'IV', 'IV', 'I', 'I', 'V', 'IV', 'I', 'V'] },
    quick: { name: tk('theory.blues.form.quick'), mode: 'major', bars: ['I', 'IV', 'I', 'I', 'IV', 'IV', 'I', 'I', 'V', 'IV', 'I', 'V'] },
    eight: { name: tk('theory.blues.form.eight'), mode: 'major', bars: ['I', 'V', 'IV', 'IV', 'I', 'V', 'I', 'V'] },
    // The "Thrill Is Gone" shape; it turns around on its own chords (no choice of turnaround or gospel).
    minor: { name: tk('theory.blues.form.minor'), mode: 'minor', bars: ['i', 'i', 'i', 'i', 'iv', 'iv', 'i', 'i', 'bVI7', 'V7', 'i', 'V7'], fixed: true },
    sixteen: { name: tk('theory.blues.form.sixteen'), mode: 'major', bars: ['I', 'I', 'I', 'I', 'IV', 'IV', 'I', 'I', 'V', 'IV', 'V', 'IV', 'I', 'I', 'I', 'V'] },
};

/** The last two bars' turnarounds (each bar's chords sharing it), leading back to the top. */
export const TURNAROUNDS = {
    plain: { name: tk('theory.blues.turn.plain'), bars: [['I'], ['V']] },
    walk: { name: tk('theory.blues.turn.walk'), bars: [['I', 'IV'], ['I', 'V']] },
    sixTwo: { name: tk('theory.blues.turn.sixTwo'), bars: [['I', 'vi'], ['ii', 'V']] },
    dominant: { name: tk('theory.blues.turn.dominant'), bars: [['I', 'VI7'], ['ii', 'V']] },
    chromatic: { name: tk('theory.blues.turn.chromatic'), bars: [['I', 'I7', 'IV', 'iv'], ['I', 'V']] },
    jazz: { name: tk('theory.blues.turn.jazz'), bars: [['I', 'bIII7'], ['bVI7', 'bII7']] },
};
// A minor blues turns around on its own chords.
const MINOR_TURNAROUND = [['i'], ['bVI7', 'V7']];

/** The feels: `swing` is where the off-beat falls (0.5 straight, 2/3 a triplet shuffle). */
export const FEELS = {
    shuffle: { name: tk('theory.blues.feel.shuffle'), swing: 2 / 3, tempo: 96, sevenths: true },
    straight: { name: tk('theory.blues.feel.straight'), swing: 0.5, tempo: 110, sevenths: false }, // rock and roll's plain triads
    slow: { name: tk('theory.blues.feel.slow'), swing: 2 / 3, tempo: 60, sevenths: true },
    gospel: { name: tk('theory.blues.feel.gospel'), swing: 2 / 3, tempo: 84, sevenths: true, gospel: true },
};

/**
 * A blues progression from its choices: the form's bars, the last two turned around, and (the
 * blues' colour) a dominant seventh on I, IV and V (a minor blues: i7 and iv7). Gospel passes
 * from IV7 through #iv°7 in bar 6 and sighs from IV to iv in bar 10. A minor blues keeps its own
 * turnaround whatever's chosen.
 */
export function blues({ form = 'twelve', turnaround = 'plain', feel = 'shuffle' } = {}) {
    const shape = BLUES_FORMS[form];
    const style = FEELS[feel];
    if (!shape || !style) throw new Error(`No such blues: ${form} ${feel}`);
    const minor = shape.mode === 'minor';
    const turn = minor ? MINOR_TURNAROUND : TURNAROUNDS[turnaround]?.bars ?? TURNAROUNDS.plain.bars;
    const colour = (numeral) => (style.sevenths && ['I', 'IV', 'V', 'i', 'iv'].includes(numeral) ? `${numeral}7` : numeral);
    const bars = shape.bars.map((numeral) => [colour(numeral)]);
    bars.splice(bars.length - 2, 2, ...turn.map((bar) => bar.map(colour)));
    if (style.gospel && !minor) {
        bars[5] = ['IV7', '#iv°7'];
        bars[bars.length - 3] = ['IV', 'iv'];
    }
    return { id: `blues-${form}-${turnaround}-${feel}`, family: 'blues', mode: shape.mode, bars, tags: ['jazzy', 'warm'], swing: style.swing, tempo: style.tempo, name: shape.name, feel: style.name };
}

// ---- Sections -----------------------------------------------------------------------------------------

/**
 * A section's chords in a key, in the template's form: `[{ beat, root, quality, numeral }]`, each
 * bar `perBar` beats long and its chords sharing it equally, from `startBeat`.
 */
export function chordsFor(bars, { tonic, perBar = 4, startBeat = 0 }) {
    const out = [];
    bars.forEach((bar, b) => {
        bar.forEach((numeral, i) => {
            out.push({ beat: startBeat + b * perBar + (i * perBar) / bar.length, ...chordOf(numeral, tonic) });
        });
    });
    return out;
}

const firstOf = (bars) => bars[0][0];
const lastOf = (bars) => bars.at(-1).at(-1);
const stepOf = (numeral) => parseNumeral(numeral)?.step ?? null;
// Chords a section may end on and still lead on: home, IV, V (with or without its seventh) or modal bVII.
const LEADS_ON = new Set([0, 5, 7, 10]);

/**
 * Ends a section so it leads somewhere, never changing its length: one ending on I, IV, V or bVII
 * stays as it is; otherwise its last chord becomes V.
 */
export function landHome(bars) {
    if (LEADS_ON.has(stepOf(lastOf(bars)))) return bars;
    const out = bars.map((bar) => [...bar]);
    out.at(-1)[out.at(-1).length - 1] = 'V';
    return out;
}

/**
 * The last chorus's ending: its last two bars become a full close home, IV V then I (major) or the
 * triumphant bVI bVII I; a minor song closes iv V then i. The length stays the same.
 */
export function lastChorus(bars, { mode = 'major', triumphant = false } = {}) {
    const out = bars.map((bar) => [...bar]);
    const close = mode === 'minor' ? [['iv', 'V'], ['i']] : triumphant ? [['bVI', 'bVII'], ['I']] : [['IV', 'V'], ['I']];
    if (out.length < 2) return [...close.slice(-1)];
    out.splice(out.length - 2, 2, ...close);
    return out;
}

/** Each bar's chords, `times` as many to the bar (2: twice as fast) or half (0.5: a chord over two bars). */
function rhythm(bars, times) {
    if (times === 2) {
        const flat = bars.flat();
        const out = [];
        for (let i = 0; i < flat.length; i += 2) out.push(flat.slice(i, i + 2));
        return out;
    }
    if (times === 0.5) return bars.flatMap((bar) => bar.flatMap((numeral) => [[numeral], [numeral]]));
    return bars;
}

// The pairings choirs and songwriters reach for: a verse's progression and a chorus often after it.
const CLASSIC = {
    'one-four-five': 'royal-road',
    'one-four-one-five': 'four-chords',
    'one-five-one': 'one-four-five',
    'four-chords': 'four-one-five-six',
    'sad-four': 'four-chords',
    'doo-wop': 'four-chords',
    'one-four-six-five': 'climb',
    'four-one-five-six': 'four-chords',
    'minor-four-ache': 'royal-road',
    gospel: 'climb',
    party: 'climb',
    'two-five-one': 'turnaround',
    turnaround: 'circle',
    barbershop: 'doo-wop',
    'royal-road': 'four-chords',
    climb: 'doo-wop',
    andalusian: 'rising-minor',
    'rising-minor': 'andalusian',
    'minor-plea': 'minor-lift',
    'epic-minor': 'minor-sway',
    'minor-home': 'andalusian',
    'minor-lift': 'epic-minor',
    'minor-sway': 'rising-minor',
    'minor-bright': 'minor-plea',
    canon: 'four-chords',
    circle: 'doo-wop',
    mixolydian: 'one-four-five',
    'dorian-vamp': 'minor-bright',
};
// A minor verse's contrast: its relative major's colour, written in the minor key's own numerals.
const RELATIVE_MAJOR = [['bIII'], ['bVII'], ['bVI'], ['bVII']];

/**
 * Three choruses for a verse, ranked: a lift (its own mode, starting brighter: on IV, or bVI in
 * minor, else vi or bIII), a contrast (the relative minor's colour for a major verse, the relative
 * major's for a minor one, the harmony moving at another pace) and a classic pairing. Each stays in
 * the verse's mode, starts on a different chord (the classic may share the start if its chords
 * differ), keeps its length and ends leading on (landHome). `raise` (semitones) nudges the melody
 * up. `{ kind, bars, mode, from, why, raise }`.
 */
export function suggestChorus(verse) {
    const start = stepOf(firstOf(verse.bars));
    const minor = verse.mode === 'minor';
    const out = [];
    const add = (kind, source, bars, why, { times = 1, sameStart = false } = {}) => {
        if (!bars || (!sameStart && stepOf(firstOf(bars)) === start)) return;
        const landed = landHome(rhythm(bars, times));
        if (JSON.stringify(landed) === JSON.stringify(verse.bars) || out.some((o) => JSON.stringify(o.bars) === JSON.stringify(landed))) return;
        out.push({ kind, from: source?.id ?? null, mode: verse.mode, bars: landed, why, raise: 3 });
    };
    const kin = PROGRESSIONS.filter((p) => p.mode === verse.mode && p.id !== verse.id);
    const lift = kin.find((p) => p.family === verse.family && firstOf(p.bars) === (minor ? 'bVI' : 'IV')) ??
        kin.find((p) => firstOf(p.bars) === (minor ? 'bVI' : 'IV')) ??
        kin.find((p) => firstOf(p.bars) === (minor ? 'bIII' : 'vi'));
    add('lift', lift, lift?.bars, tk('theory.why.lift'));
    const fast = verse.bars.some((bar) => bar.length > 1);
    const contrast = minor ? { id: 'relative-major', bars: RELATIVE_MAJOR } : kin.find((p) => firstOf(p.bars) === 'vi');
    add('contrast', contrast, contrast?.bars, tk('theory.why.contrast'), { times: fast ? 0.5 : 1 });
    const classic = progression(CLASSIC[verse.id] ?? '');
    if (classic?.mode === verse.mode) add('classic', classic, classic.bars, tk('theory.why.classic'), { sameStart: true });
    // Never fewer than three: more of the same mode's chorus starters fill in.
    const fillers = minor ? ['rising-minor', 'minor-plea', 'minor-sway', 'epic-minor', 'minor-bright'] : ['royal-road', 'four-one-five-six', 'climb', 'four-chords', 'doo-wop'];
    for (const id of fillers) {
        if (out.length >= 3) break;
        const p = progression(id);
        if (p.id !== verse.id) add('classic', p, p.bars, tk('theory.why.classic'));
    }
    return out.slice(0, 3);
}

/** A pre-chorus to build into the chorus: two bars climbing to V (or bVI bVII in minor), repeated. */
export function suggestPreChorus(verse) {
    const options = verse.mode === 'minor'
        ? [{ id: 'climb-minor', bars: [['bVI'], ['bVII'], ['bVI'], ['bVII']] }]
        : [{ id: 'two-four-five', bars: [['ii'], ['IV'], ['V'], ['V']] }, { id: 'four-five', bars: [['IV'], ['IV'], ['V'], ['V']] }];
    return options.map((o) => ({ kind: 'prechorus', from: o.id, mode: verse.mode, bars: o.bars, why: tk('theory.why.prechorus'), raise: 2 }));
}

// Bridges: somewhere new for eight bars, then V held for the last two to set up the last chorus.
const BRIDGES = {
    major: [
        { id: 'relative-minor', bars: [['vi'], ['IV'], ['vi'], ['IV'], ['ii'], ['ii'], ['V'], ['V']], why: tk('theory.why.bridgeMinor') },
        { id: 'subdominant', bars: [['IV'], ['IV'], ['I'], ['I'], ['IV'], ['IV'], ['V'], ['V']], why: tk('theory.why.bridgeFour') },
        { id: 'flat-seven', bars: [['bVII'], ['bVII'], ['IV'], ['IV'], ['I'], ['I'], ['V'], ['V']], why: tk('theory.why.bridgeFlatSeven') },
        { id: 'minor-four', bars: [['IV'], ['IV'], ['iv'], ['iv'], ['I'], ['I'], ['V'], ['V']], why: tk('theory.why.bridgeMinorFour') },
        { id: 'two', bars: [['ii'], ['ii'], ['IV'], ['IV'], ['ii'], ['ii'], ['V'], ['V']], why: tk('theory.why.bridgeTwo') },
        { id: 'three', bars: [['iii'], ['iii'], ['vi'], ['vi'], ['IV'], ['IV'], ['V'], ['V']], why: tk('theory.why.bridgeThree') },
    ],
    minor: [
        { id: 'relative-major', bars: [['bIII'], ['bIII'], ['bVII'], ['bVII'], ['bVI'], ['bVI'], ['V'], ['V']], why: tk('theory.why.bridgeRelative') },
        { id: 'subdominant-minor', bars: [['iv'], ['iv'], ['bVII'], ['bVII'], ['bIII'], ['bIII'], ['V'], ['V']], why: tk('theory.why.bridgeFour') },
        { id: 'sixth', bars: [['bVI'], ['bVI'], ['bVII'], ['bVII'], ['i'], ['i'], ['V'], ['V']], why: tk('theory.why.bridgeSixth') },
        { id: 'seventh', bars: [['bVII'], ['bVII'], ['bIII'], ['bIII'], ['iv'], ['iv'], ['V'], ['V']], why: tk('theory.why.bridgeFlatSeven') },
    ],
    blues: [{ id: 'rhythm-and-blues', bars: [['IV7'], ['IV7'], ['I7'], ['I7'], ['IV7'], ['IV7'], ['V7'], ['V7']], why: tk('theory.why.bridgeBlues') }],
};

/** Two or three bridges for a song: each starts somewhere neither the verse nor the chorus did, and ends on V. A blues gets the rhythm and blues bridge first. */
export function suggestBridge(verse, chorus = null) {
    const used = new Set([stepOf(firstOf(verse.bars)), chorus ? stepOf(firstOf(chorus.bars)) : null]);
    const pool = [...(verse.family === 'blues' && verse.mode !== 'minor' ? BRIDGES.blues : []), ...BRIDGES[verse.mode === 'minor' ? 'minor' : 'major']];
    return pool
        .filter((b) => !used.has(stepOf(firstOf(b.bars))))
        .slice(0, 3)
        .map((b) => ({ kind: 'bridge', from: b.id, mode: verse.mode, bars: b.bars, why: b.why, raise: 2 }));
}

// ---- A key for the singer ---------------------------------------------------------------------------

// Where a melody sits around its tonic (semitones): a verse from a fourth below to a fifth above, a
// chorus or bridge from a minor third below to a sixth above.
const SPANS = { verse: [-5, 7], chorus: [-3, 9], bridge: [-3, 9], prechorus: [-3, 7] };
// Keys a guitar or ukulele plays easily: C G D A E F Bb major, Am Em Dm minor.
const FRIENDLY = { major: new Set([0, 7, 2, 9, 4, 5, 10]), minor: new Set([9, 4, 2]) };
// A comfortable range by voice type (MIDI) for an untrained adult who hasn't shown theirs.
export const VOICE_RANGES = { soprano: { low: 62, high: 76 }, alto: { low: 55, high: 72 }, tenor: { low: 48, high: 65 }, baritone: { low: 45, high: 62 }, bass: { low: 41, high: 58 } };

/**
 * The key that suits a range (`{ low, high }` MIDI, comfortable notes) for a song's sections (their
 * melodies' typical spans together): the tonic (a MIDI note where the melody's home is) placed so
 * every section sits inside the range with `headroom` to spare (one semitone for a comfortable
 * range; two at the top when the range is someone's extremes), a margin of up to two semitones
 * counting, then a key a guitar plays easily. Higher and Lower are the nearest that fit, friendly
 * keys first. `{ tonic, tonicMidi, mode, name, fits, higher, lower }`.
 */
export function suggestKey(range, { mode = 'major', section = 'verse', sections = [section], headroom = 1 } = {}) {
    const { options, best, near, strip } = keyOptions(range, { mode, sections, headroom });
    return { ...strip(best), higher: strip(near(best, 1)), lower: strip(near(best, -1)) };
}

/**
 * The key a step lower or higher than the one now (`fromMidi`, its tonic as a MIDI note), for the
 * same range and sections: the nearest that fits and is friendly to play, else the nearest that
 * fits, else the next. Lower and Higher step from the key chosen, so pressing twice goes twice as far.
 */
export function nextKey(range, fromMidi, direction, { mode = 'major', section = 'verse', sections = [section], headroom = 1 } = {}) {
    const { near, strip } = keyOptions(range, { mode, sections, headroom });
    return strip(near({ tonicMidi: fromMidi }, direction));
}

/**
 * Where a tune in this key will likely sit (`{ low, high }` MIDI) for the song's sections, from the
 * same spans suggestKey fits to a range: what the key step draws beside the singer's range.
 */
export function tuneSpan(tonicMidi, { section = 'verse', sections = [section] } = {}) {
    const spans = sections.map((name) => SPANS[name] ?? SPANS.verse);
    return { low: tonicMidi + Math.min(...spans.map(([b]) => b)), high: tonicMidi + Math.max(...spans.map(([, a]) => a)) };
}

/** The keys round a range, each with how well it fits (suggestKey's and nextKey's ground). */
function keyOptions(range, { mode, sections, headroom }) {
    const spans = sections.map((name) => SPANS[name] ?? SPANS.verse);
    const below = Math.min(...spans.map(([b]) => b));
    const above = Math.max(...spans.map(([, a]) => a));
    const low = range.low + 1;
    const high = range.high - headroom;
    const option = (midi) => {
        const margin = Math.min(midi + below - low, high - (midi + above));
        return { tonic: pc(midi), tonicMidi: midi, mode, name: keyName({ tonic: midi, mode }), fits: margin >= 0, margin, friendly: FRIENDLY[mode === 'minor' ? 'minor' : 'major'].has(pc(midi)) };
    };
    const options = [];
    for (let midi = range.low - 12; midi <= range.high + 12; midi++) options.push(option(midi));
    const score = (o) => Math.min(o.margin, 2) * 2 + (o.margin < 0 ? o.margin * 2 : 0) + (o.friendly ? 1.5 : 0);
    const best = options.reduce((a, b) => (score(b) > score(a) ? b : a));
    const near = (from, direction) => {
        const ahead = options.filter((o) => (direction > 0 ? o.tonicMidi > from.tonicMidi : o.tonicMidi < from.tonicMidi)).sort((a, b) => direction * (a.tonicMidi - b.tonicMidi));
        return ahead.find((o) => o.fits && o.friendly) ?? ahead.find((o) => o.fits) ?? ahead[0] ?? null;
    };
    const strip = (o) => o && { tonic: o.tonic, tonicMidi: o.tonicMidi, mode: o.mode, name: o.name, fits: o.fits };
    return { options, best, near, strip };
}
