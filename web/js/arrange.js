// Harmony for the template creator (docs/template-creator-plan.md, "Harmony"; reworked after a
// choral arranger's review, docs/decisions.md): from a melody, a chord a half bar (a whole bar in
// 3/4, 6/8 and 2/4), then alto, tenor and bass voiced as a choir would sing them.
// - Chords: every slot's candidates are scored by how much of the melody they hold (weighted by
//   length and the strong beats, a note a semitone from the chord costing more), and the whole
//   song is planned at once (a Viterbi search), so progressions flow (ii and IV to V, V to I) and
//   phrases end on cadences: the song on I with V or IV before it, other phrases on V or I.
//   Modal tunes get their own chords: a major tune with a flattened 7th (Mixolydian) has bVII and v,
//   a minor one with a raised 6th (Dorian) has IV major and bVII.
// - Voicing: each chord is voiced as a whole (alto, tenor and bass together), and the voicings are
//   chosen across the song for the least cost: voices that hold or move by step, no parallel
//   fifths or octaves, no crossing or unisons, upper voices within an octave, the third always
//   there (in the soprano or alto too, so the three-singer version keeps it), no doubled leading
//   tone, the leading tone and the seventh resolving, each part near the middle of its range, and
//   the bass on the root (Warm allows first inversions) and well under the tune.
// - Between chords the alto and tenor hold their note, singing the tune's words with it; they move
//   only to stay under the tune, or off a second against a long or strong note.
// Styles: Simple (I, IV, V, V7), Warm (adds ii and vi), Bright (Simple's chords, the alto and tenor
// higher). "Try another chord" picks a slot's next option and plans the rest around it. Pure,
// unit-tested; the melody is the soprano (a tune written low is moved up an octave, as a low voice
// sings it an octave down).

import { beatsPerBar } from './theme.js';

// A chord's tones by quality: every quality a template's chords may have (js/notes.js, the schema), so
// a song's own chords (Michael Row's sus2, Happy Birthday's 6) voice as written. The third, or what
// stands for it (a sus chord's 2nd or 4th), is second; a sixth or added ninth sits where a seventh would.
export const TONES = {
    maj: [0, 4, 7], min: [0, 3, 7], dim: [0, 3, 6], aug: [0, 4, 8], sus2: [0, 2, 7], sus4: [0, 5, 7],
    maj7: [0, 4, 7, 11], min7: [0, 3, 7, 10], dom7: [0, 4, 7, 10], dim7: [0, 3, 6, 9], hdim7: [0, 3, 6, 10],
    maj6: [0, 4, 7, 9], min6: [0, 3, 7, 9], add9: [0, 4, 7, 2],
};
// Each mode's chords by scale step from the tonic, with a small preference (the prior); Warm adds some.
const PALETTES = {
    major: { simple: [[0, 'maj', 0.3], [5, 'maj', 0.1], [7, 'maj', 0.2], [7, 'dom7', 0.1]], warm: [[2, 'min', 0.15], [9, 'min', 0.15]] },
    minor: { simple: [[0, 'min', 0.3], [5, 'min', 0.1], [7, 'maj', 0.2], [7, 'dom7', 0.1]], warm: [[3, 'maj', 0.15], [8, 'maj', 0.15]] },
    // A minor tune with no raised 7th: its own v and bVII instead of V.
    aeolian: { simple: [[0, 'min', 0.3], [5, 'min', 0.1], [10, 'maj', 0.2], [7, 'min', 0.1]], warm: [[3, 'maj', 0.15], [8, 'maj', 0.15]] },
    mixolydian: { simple: [[0, 'maj', 0.3], [5, 'maj', 0.15], [10, 'maj', 0.2], [7, 'min', 0.05]], warm: [[2, 'min', 0.1], [9, 'min', 0.1]] },
    dorian: { simple: [[0, 'min', 0.3], [5, 'maj', 0.15], [10, 'maj', 0.15], [7, 'min', 0.05]], warm: [[3, 'maj', 0.1], [2, 'min', 0.05]] },
};
// Where each part may sing (MIDI), the middle it sits best around, and Bright's higher inner parts.
const RANGES = { alto: [55, 74], tenor: [48, 67], bass: [40, 60] };
const BASS_FLOOR = 36; // C2: where a bass goes when E2 up has no root under a low tune
const BRIGHT = { alto: [57, 76], tenor: [52, 69] };
const CENTRES = { alto: 64, tenor: 57, bass: 50 };
const BRIGHT_CENTRES = { alto: 66, tenor: 60, bass: 50 };
export const STYLES = ['simple', 'warm', 'bright'];
const OPTIONS = 4; // the chords to try in a slot, best first
const EPSILON = 1e-3; // beats are kept to 4 decimals (a third is 0.3333): closer than this is the same beat

const pc = (n) => ((n % 12) + 12) % 12;
const tonesOf = (chord) => TONES[chord.quality].map((step) => pc(chord.root + step));
const same = (a, b) => a.root === b.root && a.quality === b.quality;
/** Whether `beat` falls on a multiple of `every` (beats are kept to 4 decimals: a third is 0.3333). */
const onEvery = (beat, every) => {
    const r = ((beat % every) + every) % every;
    return r < 1e-3 || every - r < 1e-3;
};

/** The chord slots: the pickup, then each bar or half bar, to the end of the melody. */
function slotsFor(melody, timeSignature, pickupBeats) {
    const perBar = beatsPerBar(timeSignature);
    const size = timeSignature === '4/4' ? 2 : perBar;
    const end = melody.reduce((e, n) => Math.max(e, n.beat + n.length), 0);
    const slots = [];
    if (pickupBeats > 0) slots.push([0, pickupBeats]);
    for (let beat = pickupBeats; beat < end - EPSILON; beat += size) slots.push([beat, beat + size]);
    return slots;
}

/** The melody's mode: major or minor as the key says, or modal when its 7th or 6th says so. */
export function modeOf(melody, key) {
    const has = (step) => melody.some((n) => pc(n.pitch - key.tonic) === step);
    if (key.mode === 'minor') {
        if (has(9) && !has(8) && !has(11)) return 'dorian';
        if (has(10) && !has(11)) return 'aeolian';
        return 'minor';
    }
    if (has(10) && !has(11)) return 'mixolydian';
    return 'major';
}

/** How well a progression flows from one chord to the next (scale steps from the tonic). */
function flow(prev, cur, style) {
    if (same(prev, cur)) return style === 'warm' ? -0.15 : 0;
    const [p, c] = [prev.step, cur.step];
    let score = 0;
    if (p === 7 && prev.quality !== 'min') score += c === 0 ? 0.5 : c === 9 || c === 8 ? 0.15 : c === 5 ? -0.3 : 0; // V to I (or deceptive), never back to IV
    if (prev.quality === 'dom7' && c !== 0 && c !== 9 && c !== 8) score -= 0.4; // a seventh wants resolving
    if ((p === 5 || p === 2) && c === 7) score += 0.35; // IV or ii to V
    if (p === 10 && c === 0) score += 0.4; // bVII to I (modal)
    if (p === 5 && c === 0) score += 0.15; // plagal
    if ((p === 9 || p === 8) && (c === 2 || c === 5)) score += 0.25; // vi to ii or IV
    if (p === 0 && [5, 7, 9, 2].includes(c)) score += 0.1;
    return score;
}

/**
 * Chords already chosen (the song builder's progression): each a slot to the next chord, with only
 * itself to choose, the ones under no singing left out. In chooseChords' form.
 */
function givenChords(melody, given, key) {
    const end = melody.reduce((e, n) => Math.max(e, n.beat + n.length), 0);
    const slots = given
        .map((c, i) => ({ beat: c.beat, endBeat: given[i + 1]?.beat ?? end, options: [{ root: c.root, quality: c.quality }], choice: 0, step: pc(c.root - key.tonic), final: false }))
        .filter((s) => s.beat < end - EPSILON && melody.some((n) => n.beat < s.endBeat - EPSILON && n.beat + n.length > s.beat + EPSILON));
    if (slots.length) slots.at(-1).final = true;
    return { chords: slots.map((s) => ({ beat: s.beat, ...s.options[0], bass: null })), slots };
}

/**
 * Whether the tune and a root-position bass would move in parallel octaves or fifths at a change
 * (the tune on both roots, or both fifths, moving): no voicing can save that, so the chords avoid it.
 */
function outerParallels(prevSlot, prev, slot, cur) {
    if (same(prev, cur) || prevSlot.last === slot.first) return 0;
    const was = pc(prevSlot.last - prev.root);
    const now = pc(slot.first - cur.root);
    return (was === 0 && now === 0) || (was === 7 && now === 7) ? 0.6 : 0;
}

/**
 * The chords for a melody ({ beat, length, pitch } in order), as `{ chords, slots }`: `chords` in
 * the template's form ({ beat, root, quality, bass }), and each slot's `options` (best first) and
 * `choice`. `key` is `{ tonic, mode }`; `phraseEnds` the beats where phrases end; `choices` maps a
 * slot to the option to use ("Try another chord"), and the rest are planned around it.
 */
export function chooseChords(melody, { key, timeSignature = '4/4', pickupBeats = 0, style = 'simple', phraseEnds = [], choices = {}, chords: given = null }) {
    if (given?.length) return givenChords(melody, given, key);
    const mode = modeOf(melody, key);
    const palette = [...PALETTES[mode].simple, ...(style === 'warm' ? PALETTES[mode].warm : [])];
    const candidates = palette.map(([step, quality, prior]) => ({ root: pc(key.tonic + step), quality, prior, step }));
    const perBar = beatsPerBar(timeSignature);
    const strong = (beat) => onEvery(beat - pickupBeats, perBar) || (timeSignature === '4/4' && onEvery(beat - pickupBeats, 2));
    const lastEnd = Math.max(0, ...phraseEnds);

    // The slots that have singing in them, and how well each chord fits each on its own.
    const slots = [];
    for (const [start, stop] of slotsFor(melody, timeSignature, pickupBeats)) {
        const inside = melody.filter((n) => n.beat < stop - EPSILON && n.beat + n.length > start + EPSILON);
        if (!inside.length) continue; // a rest: no chord to sing
        const ends = phraseEnds.find((e) => inside.some((n) => Math.abs(n.beat + n.length - e) < 1e-3)); // beats are kept to 4 decimals
        const fit = candidates.map((c) => {
            const tones = tonesOf(c);
            let score = c.prior;
            for (const n of inside) {
                const held = Math.min(stop, n.beat + n.length) - Math.max(start, n.beat);
                const weight = held * (strong(n.beat) ? 1.5 : 1);
                if (tones.includes(pc(n.pitch))) score += weight;
                else score -= (tones.some((t) => [1, 11].includes(pc(n.pitch - t))) ? 1.2 : 0.6) * weight; // a semitone from the chord clashes
            }
            return score;
        });
        const firstNote = inside.find((n) => n.beat >= start - EPSILON) ?? inside[0];
        slots.push({ beat: start, endBeat: stop, fit, ends, final: ends !== undefined && Math.abs(ends - lastEnd) < 1e-3, first: firstNote.pitch, last: inside.at(-1).pitch });
    }
    // Cadences: the song ends on I, with V (or IV, or modal bVII) before it; other phrases on V or I.
    slots.forEach((slot, i) => {
        const finalNext = slots[i + 1]?.final;
        slot.fit = slot.fit.map((score, k) => {
            const c = candidates[k];
            if (slot.final) return score + (c.step === 0 ? 3 : -1);
            if (finalNext && slot.ends === undefined) return score + (c.step === 7 && c.quality !== 'min' ? 0.8 : c.step === 5 || c.step === 10 ? 0.4 : 0);
            if (slot.ends !== undefined) return score + (c.step === 7 && c.quality !== 'min' ? 0.6 : c.step === 0 ? 0.4 : 0);
            if (i === 0) return score + (c.step === 0 ? 0.5 : c.step === 7 && pickupBeats > 0 ? 0.2 : 0);
            return score;
        });
    });

    // The whole song at once: the path of chords with the best fit and flow, with any fixed ones kept.
    const plan = (fixed) => {
        let best = candidates.map((c, k) => (fixed[0] !== undefined && !same(fixed[0], c) ? -Infinity : slots[0].fit[k]));
        const back = [];
        for (let i = 1; i < slots.length; i++) {
            const next = [];
            const from = [];
            candidates.forEach((c, k) => {
                if (fixed[i] !== undefined && !same(fixed[i], c)) {
                    next.push(-Infinity);
                    from.push(0);
                    return;
                }
                let top = -Infinity;
                let arg = 0;
                candidates.forEach((p, j) => {
                    const score = best[j] + flow(p, c, style) - outerParallels(slots[i - 1], p, slots[i], c);
                    if (score > top) [top, arg] = [score, j];
                });
                next.push(top + slots[i].fit[k]);
                from.push(arg);
            });
            best = next;
            back.push(from);
        }
        let k = best.indexOf(Math.max(...best));
        const path = [k];
        for (let i = back.length - 1; i >= 0; i--) path.unshift((k = back[i][k]));
        return path.map((index) => candidates[index]);
    };
    const free = plan({});
    // Each slot's options: the planned chord first, then the others by how well they fit the slot.
    const optionsOf = slots.map((slot, i) => {
        const ranked = candidates.map((c, k) => ({ c, score: slot.fit[k] })).sort((a, b) => b.score - a.score).map((x) => x.c);
        const options = [free[i]];
        for (const c of ranked) if (options.length < OPTIONS && !options.some((o) => same(o, c))) options.push(c);
        return options;
    });
    const fixed = {};
    for (const [i, choice] of Object.entries(choices)) {
        const options = optionsOf[i];
        if (options) fixed[i] = options[Math.min(options.length - 1, Math.max(0, choice))];
    }
    const path = Object.keys(fixed).length ? plan(fixed) : free;
    const out = slots.map((slot, i) => {
        const options = [...optionsOf[i]];
        let choice = options.findIndex((o) => same(o, path[i]));
        if (choice < 0) {
            options[options.length - 1] = path[i]; // a neighbour's choice moved this one off its list
            choice = options.length - 1;
        }
        return { beat: slot.beat, endBeat: slot.endBeat, options: options.map(({ root, quality }) => ({ root, quality })), choice, step: path[i].step, final: slot.final };
    });
    const chords = out.map((s) => ({ beat: s.beat, root: s.options[s.choice].root, quality: s.options[s.choice].quality, bass: null }));
    return { chords, slots: out };
}

// ---- Voicing ---------------------------------------------------------------------------------------

/** Every way to voice a chord under a soprano note (or none: `soprano` null): alto, tenor and bass, in range, nothing crossing. */
function voicingsFor(chord, { soprano, lowest, style, first, last, warm, lower = 0 }) {
    const tones = tonesOf(chord);
    const [aLow, aHigh] = ((style === 'bright' && BRIGHT.alto) || RANGES.alto).map((p, i) => (i ? p : p - lower));
    const [tLow, tHigh] = ((style === 'bright' && BRIGHT.tenor) || RANGES.tenor).map((p, i) => (i ? p : p - lower));
    const [bLow, bHigh] = RANGES.bass;
    // The bass: the root, or (Warm, inside the song, a major or minor chord) the third; well under the tune.
    const bassTones = [tones[0], ...(warm && !first && !last && ['maj', 'min'].includes(chord.quality) ? [tones[1]] : [])];
    const bassTop = Math.max(bLow + 7, Math.min(bHigh, lowest - 12));
    const pitches = (classes, low, high) => {
        const out = [];
        for (let p = low; p <= high; p++) if (classes.includes(pc(p))) out.push(p);
        return out;
    };
    // A tune sitting low leaves the bass only E2 to B2: a root of C to Eb has none there. Then the
    // bass goes down to C2 (BASS_FLOOR), and failing that sits up to its top, nearer the tune.
    let basses = pitches(bassTones, bLow, bassTop);
    if (!basses.length) basses = pitches(bassTones, BASS_FLOOR, bassTop);
    if (!basses.length) basses = pitches(bassTones, bLow, bHigh);
    const out = [];
    for (const b of basses) {
        for (const a of pitches(tones, aLow, Math.min(aHigh, (soprano ?? lowest) - 1))) {
            if (soprano !== null && soprano - a > 12) continue;
            for (const t of pitches(tones, Math.max(tLow, b + 1), Math.min(tHigh, a - 1))) out.push({ a, t, b });
        }
    }
    return out;
}

/**
 * The last resort when no voicing fits the ranges (a tune far below where any choir sings): the
 * chord's tones packed under the tune, each a step down from the last, the bass on the root below.
 */
function closeUnder(chord, soprano) {
    const tones = tonesOf(chord);
    const below = (ceiling, classes) => {
        for (let p = ceiling; p > ceiling - 12; p--) if (classes.includes(pc(p))) return p;
        return ceiling;
    };
    const a = below(soprano - 1, tones);
    const t = below(a - 1, tones);
    const b = below(Math.min(t - 1, RANGES.bass[1]), [tones[0]]);
    return { a, t, b };
}

/** What a voicing costs on its own: a missing third, doubling, spacing, register, inversion. With no tune (`s` null), the three alone. */
function standing(v, s, chord, { leading, style, first, last, lowest }) {
    const tones = tonesOf(chord);
    const classes = [...(s === null ? [] : [pc(s)]), pc(v.a), pc(v.t), pc(v.b)];
    const count = (x) => classes.filter((c) => c === x).length;
    const centres = style === 'bright' ? BRIGHT_CENTRES : CENTRES;
    let cost = 0;
    // The third: in the alto, tenor or bass (the tune moves off its note), and never missing.
    // (V's third is the leading tone: in the tune, the lower voices leave it alone.)
    if (!count(tones[1])) cost += 12;
    else if (![pc(v.a), pc(v.t), pc(v.b)].includes(tones[1]) && !(tones[1] === leading && pc(s) === leading)) cost += 8;
    if (tones.length === 4 && !count(tones[3])) cost += 2; // no seventh
    else if (!count(tones[2])) cost += 0.6; // no fifth
    if (count(leading) > 1) cost += 8;
    if (chord.quality === 'maj' && count(tones[1]) > 1) cost += 1.2; // a doubled major third
    if (s !== null && s - v.a > 12) cost += 6;
    if (v.a - v.t > 12) cost += 6;
    if (v.t - v.b > 19) cost += 3;
    cost += 0.12 * Math.abs(v.a - centres.alto) + 0.12 * Math.abs(v.t - centres.tenor) + 0.1 * Math.abs(v.b - centres.bass);
    if (pc(v.b) !== tones[0]) cost += last ? 40 : first ? 3 : style === 'warm' ? 0.7 : 1.5;
    if (s === null) return cost;
    // For fewer singers: the third in the soprano or alto (three), and the alto in thirds and sixths under the tune (two).
    if (![pc(s), pc(v.a)].includes(tones[1])) cost += 6;
    if (lowest !== undefined && v.a >= lowest) cost += 1; // it moves down under a lower note of the slot
    const upper = pc(s - v.a);
    if ([0, 5, 7].includes(upper)) cost += 2;
    else if ([1, 2, 10, 11].includes(upper)) cost += 2;
    return cost;
}

/** What moving from one voicing to the next costs: leaps, parallels, unresolved tendencies. */
function moving(p, ps, q, qs, prevChord, chord, leading) {
    // `ps` is the tune's note just before the change, `qs` its note on it.
    let cost = 0.35 * (Math.abs(q.a - p.a) + Math.abs(q.t - p.t)) + 0.15 * Math.abs(q.b - p.b);
    for (const [x, y] of [[p.a, q.a], [p.t, q.t]]) {
        const leap = Math.abs(y - x);
        if (leap >= 5) cost += 2.5;
        if (leap >= 7) cost += 2;
        if (leap > 7) cost += leap > 9 ? 25 : 15;
        if (leap === 6) cost += 20; // a tritone is hard to sing
    }
    const bassLeap = Math.abs(q.b - p.b);
    if (bassLeap > 7) cost += 1.5;
    if (bassLeap === 6) cost += 3;
    // Parallel fifths and octaves between any two voices (the tune too, when there is one).
    const before = [...(ps === null ? [] : [ps]), p.a, p.t, p.b];
    const after = [...(qs === null ? [] : [qs]), q.a, q.t, q.b];
    for (let i = 0; i < before.length; i++) {
        for (let j = i + 1; j < before.length; j++) {
            if (before[i] === after[i] || before[j] === after[j]) continue;
            const was = pc(before[i] - before[j]);
            const now = pc(after[i] - after[j]);
            if ((now === 7 || now === 0) && was === now) cost += 25;
        }
    }
    // Hidden fifths and octaves between the outer voices, when the tune leaps into them.
    const outer = qs === null ? null : pc(qs - q.b);
    if ((outer === 7 || outer === 0) && Math.sign(qs - ps) === Math.sign(q.b - p.b) && Math.abs(qs - ps) > 2) cost += 3;
    // V to I: the leading tone rises to the tonic (an inner voice may fall to the fifth), the seventh falls.
    if (prevChord.step === 7 && prevChord.quality !== 'min' && chord.step === 0) {
        for (const [x, y] of [[p.a, q.a], [p.t, q.t]]) {
            if (pc(x) === leading && y !== x + 1 && y !== x - 4) cost += 4;
            if (TONES[prevChord.quality].length === 4 && pc(x) === tonesOf(prevChord)[3] && !(y < x && x - y <= 2)) cost += 4;
        }
    }
    return cost;
}

/**
 * Alto, tenor and bass for a melody: `{ chords, slots, parts: { alto, tenor, bass }, warnings }`.
 * The alto and tenor sing the melody's words with it, note for note, holding their chord's note;
 * the bass holds each chord on "oo". `warnings` lists the beats with parallel fifths or octaves
 * between the tune and the bass (with this voicing, normally none).
 */
export function arrange(melody, options) {
    const style = options.style ?? 'simple';
    const { chords, slots } = chooseChords(melody, options);
    const leading = pc(options.key.tonic + 11);
    const inside = (slot) => melody.filter((n) => n.beat < slot.endBeat - EPSILON && n.beat + n.length > slot.beat + EPSILON);

    const perBar = beatsPerBar(options.timeSignature ?? '4/4');
    const strong = (beat) => onEvery(beat - (options.pickupBeats ?? 0), perBar);
    const below = (tones, ceiling, floor) => {
        for (let p = ceiling; p > floor; p--) if (tones.includes(pc(p))) return p;
        return null;
    };
    const altoFloor = ((style === 'bright' && BRIGHT.alto) || RANGES.alto)[0];
    // The alto and tenor of a voicing on one note of the tune: the alto moves under it (to the chord's
    // third first, within a sixth, unless the bass has the third), the tenor under the alto.
    const placeUnder = (v, tones, pitch, firm) => {
        const clashes = (p) => p >= pitch || (firm && [1, 2].includes(pitch - p));
        let a = v.a;
        if (clashes(a)) a = (pc(v.b) === tones[1] ? null : below([tones[1]], pitch - 3, Math.max(v.t, altoFloor - 1, pitch - 10))) ?? below(tones, pitch - 3, Math.max(v.t, altoFloor - 1)) ?? below(tones, pitch - 1, v.t) ?? below(tones, pitch - 1, v.b) ?? Math.min(a, pitch - 1);
        let t = v.t;
        if (t >= a) t = below(tones, a - 1, v.b) ?? Math.min(t, a - 1);
        return { a, t };
    };

    // Each slot's choices, then the cheapest path through them (a Viterbi search).
    const events = slots.map((slot, i) => {
        const notes = inside(slot);
        const from = Math.max(slot.beat, notes[0].beat);
        const soprano = notes.find((n) => n.beat <= from + EPSILON && n.beat + n.length > from + EPSILON)?.pitch ?? notes[0].pitch;
        const held = notes.filter((n) => n.length >= 0.5 - EPSILON);
        const lowest = Math.min(...(held.length ? held : notes).map((n) => n.pitch));
        const chord = { ...slot.options[slot.choice], step: slot.step };
        const context = { soprano, lowest, style, first: i === 0, last: i === slots.length - 1, warm: style === 'warm', leading };
        // A tune too low to sit over: the alto and tenor go a little lower, then sit under its first note only.
        let choices = voicingsFor(chord, context);
        // None with the third (a narrow gap under the tune): the lower ones too.
        const third = tonesOf(chord)[1];
        if (!choices.some((v) => [v.a, v.t, v.b, soprano].some((p) => pc(p) === third))) choices = [...choices, ...voicingsFor(chord, { ...context, lower: 5 })];
        if (!choices.length) choices = voicingsFor(chord, { ...context, lower: 5, lowest: soprano });
        if (!choices.length) choices = voicingsFor(chord, { ...context, lower: 12, lowest: soprano });
        if (!choices.length) choices = [closeUnder(chord, soprano)]; // never none: the chord's tones packed under the tune
        // Where each choice starts and ends the slot (the note pass moves it under the tune's notes),
        // so the search judges the move from one chord to the next from there.
        const firmOf = (n) => strong(n.beat) || n.length >= 2 - EPSILON;
        const firstNote = notes.find((n) => n.beat <= from + EPSILON && n.beat + n.length > from + EPSILON) ?? notes[0];
        const lastNote = notes.at(-1);
        choices = choices.map((v) => ({ ...v, start: { ...v, ...placeUnder(v, tonesOf(chord), firstNote.pitch, firmOf(firstNote)) }, end: { ...v, ...placeUnder(v, tonesOf(chord), lastNote.pitch, firmOf(lastNote)) } }));
        return { slot, notes, from, soprano, last: notes.at(-1).pitch, chord, choices, cost: choices.map((v) => standing(v.start ?? v, soprano, chord, context)) };
    });
    let total = events[0].cost.slice();
    const back = [];
    for (let i = 1; i < events.length; i++) {
        const [prev, cur] = [events[i - 1], events[i]];
        const next = [];
        const from = [];
        cur.choices.forEach((q, k) => {
            let low = Infinity;
            let arg = 0;
            prev.choices.forEach((p, j) => {
                const cost = total[j] + moving(p.end ?? p, prev.last, q.start ?? q, cur.soprano, prev.chord, cur.chord, leading);
                if (cost < low) [low, arg] = [cost, j];
            });
            next.push(low + cur.cost[k]);
            from.push(arg);
        });
        total = next;
        back.push(from);
    }
    let k = total.indexOf(Math.min(...total));
    const picked = [k];
    for (let i = back.length - 1; i >= 0; i--) picked.unshift((k = back[i][k]));
    const voiced = events.map((e, i) => e.choices[picked[i]]);

    // The bass: one note a slot, over the notes sung in it.
    const bass = [];
    events.forEach((e, i) => {
        const to = Math.min(e.slot.endBeat, Math.max(...e.notes.map((n) => n.beat + n.length)));
        const pitch = voiced[i].b;
        if (pc(pitch) !== e.chord.root) chords[i].bass = pc(pitch);
        // A new line of the tune since the last bass note: the bass starts its "oo" again.
        const startsLine = !bass.length || melody.some((n) => n.br && n.beat > bass.at(-1).beat + EPSILON && n.beat < e.slot.endBeat - EPSILON);
        bass.push({ beat: e.from, length: to - e.from, pitch, ...(startsLine ? { lyric: 'oo', br: true } : {}) });
    });

    // The alto and tenor, note for note with the tune (split where the chord changes under a note),
    // holding the slot's voicing; a note moves only to stay under the tune, or off a second against
    // a long or strong note of it.
    const indexAt = (beat) => Math.max(0, events.findLastIndex((e) => e.slot.beat <= beat + EPSILON));
    const alto = [];
    const tenor = [];
    for (const note of melody) {
        const cuts = [note.beat, ...slots.map((s) => s.beat).filter((b) => b > note.beat + EPSILON && b < note.beat + note.length - EPSILON), note.beat + note.length];
        for (let c = 0; c < cuts.length - 1; c++) {
            const beat = cuts[c];
            const length = cuts[c + 1] - beat;
            const i = indexAt(beat);
            const v = voiced[i];
            const tones = tonesOf(events[i].chord);
            const firm = strong(beat) || note.length >= 2 - EPSILON;
            const { a, t: tn } = placeUnder(v, tones, note.pitch, firm);
            const words = c === 0 ? { ...(note.lyric ? { lyric: note.lyric } : {}), ...(note.br ? { br: true } : {}) } : {};
            for (const [line, pitch] of [[alto, a], [tenor, tn]]) {
                const prev = line.at(-1);
                // A held vowel on the same pitch is one note.
                if (prev && !words.lyric && !words.br && prev.pitch === pitch && Math.abs(prev.beat + prev.length - beat) < 1e-6) prev.length += length;
                else line.push({ beat, length, pitch, ...words });
            }
        }
    }
    return { chords, slots, parts: { alto, tenor, bass }, warnings: parallels(melody, bass) };
}

/**
 * A chord progression voiced for alto, tenor and bass with no tune (the song builder's backing, and
 * the harmony its chords become): `chords` in the template's form ({ beat, root, quality }), each
 * held until the next (the last to `endBeat`), voiced as the arranger voices a song (the cheapest
 * path through every chord's voicings: smooth, no parallels, the third there, in range). Returns
 * `{ alto, tenor, bass }`, each `[{ beat, length, pitch, lyric }]` on "oo".
 */
export function voiceChords(chords, { key, style = 'simple', endBeat }) {
    const leading = pc(key.tonic + 11);
    const events = chords.map((c, i) => {
        const chord = { root: c.root, quality: c.quality, step: pc(c.root - key.tonic) };
        const context = { soprano: null, lowest: 127, style, first: i === 0, last: i === chords.length - 1, warm: style === 'warm', leading };
        const choices = voicingsFor(chord, context);
        return { chord, choices, cost: choices.map((v) => standing(v, null, chord, context)) };
    });
    let total = events[0].cost.slice();
    const back = [];
    for (let i = 1; i < events.length; i++) {
        const [prev, cur] = [events[i - 1], events[i]];
        const next = [];
        const from = [];
        cur.choices.forEach((q, k) => {
            let low = Infinity;
            let arg = 0;
            prev.choices.forEach((p, j) => {
                const cost = total[j] + moving(p, null, q, null, prev.chord, cur.chord, leading);
                if (cost < low) [low, arg] = [cost, j];
            });
            next.push(low + cur.cost[k]);
            from.push(arg);
        });
        total = next;
        back.push(from);
    }
    let k = total.indexOf(Math.min(...total));
    const picked = [k];
    for (let i = back.length - 1; i >= 0; i--) picked.unshift((k = back[i][k]));
    const parts = { alto: [], tenor: [], bass: [] };
    chords.forEach((c, i) => {
        const length = (chords[i + 1]?.beat ?? endBeat) - c.beat;
        const v = events[i].choices[picked[i]];
        for (const [part, pitch] of [['alto', v.a], ['tenor', v.t], ['bass', v.b]]) {
            const line = parts[part];
            const prev = line.at(-1);
            // The same note held on: one note, one "oo".
            if (prev && prev.pitch === pitch && Math.abs(prev.beat + prev.length - c.beat) < EPSILON) prev.length += length;
            else line.push({ beat: c.beat, length, pitch, lyric: 'oo', ...(line.length ? {} : { br: true }) });
        }
    });
    return parts;
}

/** Beats where the tune and the bass move in parallel fifths or octaves. */
export function parallels(melody, bass) {
    const at = (line, beat) => line.findLast((n) => n.beat <= beat + EPSILON && n.beat + n.length > beat + EPSILON)?.pitch ?? null;
    const beats = [...new Set(bass.map((n) => n.beat))].sort((a, b) => a - b);
    const out = [];
    for (let i = 1; i < beats.length; i++) {
        const [s0, b0, s1, b1] = [at(melody, beats[i - 1]), at(bass, beats[i - 1]), at(melody, beats[i]), at(bass, beats[i])];
        if ([s0, b0, s1, b1].includes(null) || s0 === s1 || b0 === b1) continue;
        const i0 = pc(s0 - b0);
        if ((i0 === 7 || i0 === 0) && pc(s1 - b1) === i0 && Math.sign(s1 - s0) === Math.sign(b1 - b0)) out.push(beats[i]);
    }
    return out;
}

/** The middle of a melody's pitches (by length): where it sits. */
function middle(notes) {
    const sorted = [...notes].sort((a, b) => a.pitch - b.pitch);
    const half = notes.reduce((t, n) => t + n.length, 0) / 2;
    let run = 0;
    for (const n of sorted) if ((run += n.length) >= half) return n.pitch;
    return sorted.at(-1)?.pitch ?? 60;
}

/**
 * A whole template's parts for a melody part: soprano (the tune), alto, tenor and bass, each with
 * its range, the chords, the recording order and the versions for 2, 3 and 4 singers.
 */
export function harmonize(template, { style = 'simple', choices = {}, key, chords = null } = {}) {
    const tune = template.parts.find((p) => p.melody) ?? template.parts[0];
    // A tune written low (sung by a low voice) goes up an octave to be the soprano; it's sung an octave down.
    const lift = middle(tune.guide.notes) < 57 ? 12 : 0;
    const melody = tune.guide.notes.map((n) => (lift ? { ...n, pitch: n.pitch + lift } : n));
    const phraseEnds = template.sections.flatMap((s) => s.phrases.map(([, end]) => end));
    const result = arrange(melody, { key, timeSignature: template.timeSignature, pickupBeats: template.pickupBeats, style, phraseEnds, choices, chords });
    const part = (k, name, notes) => ({ key: k, name, range: { low: Math.min(...notes.map((n) => n.pitch)), high: Math.max(...notes.map((n) => n.pitch)) }, guide: { type: 'notes', notes } });
    return {
        result,
        template: {
            ...template,
            chords: result.chords,
            order: ['soprano', 'bass', 'alto', 'tenor'],
            voicings: { 2: ['soprano', 'alto'], 3: ['soprano', 'alto', 'bass'], 4: ['soprano', 'alto', 'tenor', 'bass'] },
            // The parts' English names are the file's (credits, the admin); the app words them from the key.
            parts: [
                { ...part('soprano', 'Soprano', melody), melody: true }, // i18n-ignore
                part('alto', 'Alto', result.parts.alto), // i18n-ignore
                part('tenor', 'Tenor', result.parts.tenor), // i18n-ignore
                part('bass', 'Bass', result.parts.bass), // i18n-ignore
            ],
        },
    };
}
