// The arranger's suggested harmony judged as a choir course would: every library tune's melody and
// ten hummed tunes, in each style, through harmonize(); faults counted per kind, with examples.
import { harmonize, TONES } from '/js/arrange.js';
import { parseKey } from '/js/notes.js';
import { humToMelody } from '/js/hum.js';

const pc = (n) => ((n % 12) + 12) % 12;
const RANGE = { alto: [55, 74], tenor: [48, 67], bass: [36, 60] };
const BRIGHT = { alto: [57, 76], tenor: [52, 69] };

function at(notes, beat) {
    return notes.findLast((n) => n.beat <= beat + 1e-6 && n.beat + n.length > beat + 1e-6)?.pitch ?? null;
}

export function judge(template, style) {
    const { tonic, minor } = parseKey(template.key);
    const melodyPart = template.parts.find((p) => p.melody) ?? template.parts[0];
    const { result, template: out } = harmonize({ ...template, parts: [melodyPart] }, { style, key: { tonic, mode: minor ? 'minor' : 'major' } });
    const parts = Object.fromEntries(out.parts.map((p) => [p.key, p.guide.notes]));
    return { faults: faultsOf(template, parts, result.chords), chords: result.chords.length, warnings: result.warnings.length };
}

/**
 * A song's own four parts (a library template, as arranged) judged the same way: its voices top to
 * bottom by their middle pitch (so a barbershop tenor, lead, baritone and bass read as four
 * voices), its own chords. Ranges are a choir's SATB ones, so a barbershop song's out-of-range
 * count is a guide, not a fault.
 */
export function judgeTemplate(template) {
    const middle = (p) => p.guide.notes.reduce((t, n) => t + n.pitch * n.length, 0) / Math.max(1e-9, p.guide.notes.reduce((t, n) => t + n.length, 0));
    const voices = template.parts.filter((p) => p.guide?.notes?.length).sort((a, b) => middle(b) - middle(a)).slice(0, 4);
    const parts = Object.fromEntries(['soprano', 'alto', 'tenor', 'bass'].map((k, i) => [k, voices[i]?.guide.notes ?? []]));
    return { faults: faultsOf(template, parts, template.chords ?? []), voices: voices.map((p) => p.key) };
}

/** The faults in four parts ({ soprano, alto, tenor, bass }: notes), with the song's chords. */
function faultsOf(template, parts, chords) {
    const { tonic } = parseKey(template.key);
    const names = ['soprano', 'alto', 'tenor', 'bass'];
    const beats = [...new Set(names.flatMap((k) => parts[k].map((n) => n.beat)))].sort((a, b) => a - b);
    const faults = [];
    const add = (kind, beat, detail) => faults.push({ kind, beat, detail });
    const chordAt = (beat) => chords.findLast((c) => c.beat <= beat + 1e-6);
    const leading = pc(tonic + 11);
    const perBar = { '4/4': 4, '3/4': 3, '2/4': 2, '6/8': 2 }[template.timeSignature] ?? 4;
    const onGrid = (beat, every) => { const r = (((beat - (template.pickupBeats || 0)) % every) + every) % every; return r < 1e-3 || every - r < 1e-3; };
    // A firm note of the tune (as the arranger and the choral courses judge): on a strong beat, or held two beats or more.
    const firm = (beat) => { const n = parts.soprano.findLast((x) => x.beat <= beat + 1e-6 && x.beat + x.length > beat + 1e-6); return !!n && Math.abs(n.beat - beat) < 1e-6 && (onGrid(beat, perBar) || (perBar === 4 && onGrid(beat, 2)) || n.length >= 2 - 1e-6); };
    let prev = null;
    for (const beat of beats) {
        const v = names.map((k) => at(parts[k], beat));
        if (v.includes(null)) { prev = null; continue; }
        // A rest since the last onset (a phrase's break) starts afresh: no parallels across silence.
        if (prev && prev.ends.some((end) => end < beat - 1e-6)) prev = null;
        const [s, a, t, b] = v;
        if (a > s) add('crossing', beat, 'alto over soprano');
        if (t > a) add('crossing', beat, 'tenor over alto');
        if (b > t) add('crossing', beat, 'bass over tenor');
        if (s - a > 12 && firm(beat)) add('spacing', beat, `soprano-alto ${s - a}`);
        if (a - t > 12) add('spacing', beat, `alto-tenor ${a - t}`);
        const r = RANGE; // a choir's ranges: Bright's higher floors are its taste, not a fault
        if (a < r.alto[0] || a > r.alto[1]) add('range', beat, `alto ${a}`);
        if (t < r.tenor[0] || t > r.tenor[1]) add('range', beat, `tenor ${t}`);
        if (b < r.bass[0] || b > r.bass[1]) add('range', beat, `bass ${b}`);
        const chord = chordAt(beat);
        if (chord) {
            const tones = TONES[chord.quality].map((x) => pc(chord.root + x));
            const isChordStart = Math.abs(chord.beat - beat) < 1e-6;
            if (isChordStart && !v.some((p) => pc(p) === tones[1])) add('noThird', beat, `${chord.root}${chord.quality}`);
            if (v.filter((p) => pc(p) === leading).length > 1 && tones.includes(leading)) add('doubledLeading', beat, isChordStart ? 'chord' : 'inside');
        }
        // A second against the tune on a strong beat (alto a tone or semitone under the soprano).
        if ([1, 2].includes(s - a) && firm(beat)) add('secondUnderTune', beat, `${s}/${a}`);
        if (prev) {
            for (let i = 0; i < 4; i++) for (let j = i + 1; j < 4; j++) {
                const [x0, y0, x1, y1] = [prev.v[i], prev.v[j], v[i], v[j]];
                if (x0 === x1 || y0 === y1) continue;
                const was = pc(x0 - y0), now = pc(x1 - y1);
                if ((now === 0 || now === 7) && was === now && Math.sign(x1 - x0) === Math.sign(y1 - y0)) add('parallel', beat, `${names[i]}-${names[j]} ${now === 0 ? '8ve' : '5th'}`);
            }
            for (let i = 1; i < 4; i++) {
                const leap = Math.abs(v[i] - prev.v[i]);
                if (i < 3 && leap > 7 && leap !== 12) add('leap', beat, `${names[i]} ${leap}`);
                if (leap === 6) add('tritone', beat, `${names[i]}`);
            }
            const pc0 = prev.chord, c1 = chord;
            if (pc0 && c1 && pc0 !== c1 && pc(pc0.root - tonic) === 7 && pc(c1.root - tonic) === 0) {
                for (const i of [1, 2]) if (pc(prev.v[i]) === leading && !(v[i] === prev.v[i] + 1 || v[i] === prev.v[i] - 4)) add('leadingUnresolved', beat, names[i]);
            }
        }
        const ends = names.map((k) => { const n = parts[k].findLast((x) => x.beat <= beat + 1e-6 && x.beat + x.length > beat + 1e-6); return n.beat + n.length; });
        prev = { v, chord, ends };
    }
    return faults;
}

/** A tune hummed as hum.test.js hums it, then turned into a melody (Hum a tune). */
export function hummed(tune, seed = 7) {
    let r = seed;
    const rand = () => ((r = (r * 16807) % 2147483647) / 2147483647);
    const gauss = () => Math.sqrt(-2 * Math.log(rand() + 1e-12)) * Math.cos(2 * Math.PI * rand());
    const notes = tune.notes.map(([beat, length, pitch, br]) => ({ beat, length, pitch, br: !!br }));
    const base = 60 / tune.tempo;
    const events = notes.map((n, i) => {
        const start = 0.5 + n.beat * base + gauss() * 0.025;
        let stop = 0.5 + (n.beat + n.length) * base - 0.06 - rand() * 0.05;
        if (notes[i + 1]?.br) stop = Math.min(stop, 0.5 + notes[i + 1].beat * base - 0.3);
        return { start, end: Math.max(stop, start + 0.08), pitch: n.pitch, sure: true };
    });
    const m = humToMelody(events);
    const phrases = m.phrases.map((p) => [p.startBeat, p.endBeat]);
    return { id: `hum:${tune.id}`, key: m.key, timeSignature: m.timeSignature, pickupBeats: m.pickupBeats, tempo: m.tempo, sections: [{ name: '', startBeat: 0, endBeat: Math.max(...m.notes.map((n) => n.beat + n.length)), phrases }], parts: [{ key: 'soprano', melody: true, guide: { type: 'notes', notes: m.notes } }] };
}

/** The four parts and the chord from `from` to `to` beats, for a look at a fault. */
export function probe(template, style, from, to) {
    const { tonic, minor } = parseKey(template.key);
    const melodyPart = template.parts.find((p) => p.melody) ?? template.parts[0];
    const { result, template: out } = harmonize({ ...template, parts: [melodyPart] }, { style, key: { tonic, mode: minor ? 'minor' : 'major' } });
    const parts = Object.fromEntries(out.parts.map((p) => [p.key, p.guide.notes]));
    const rows = [];
    for (let b = from; b <= to; b += 0.5) rows.push([b, ...['soprano', 'alto', 'tenor', 'bass'].map((k) => at(parts[k], b)), JSON.stringify(result.chords.findLast((c) => c.beat <= b + 1e-6))]);
    return { key: template.key, ts: template.timeSignature, pickup: template.pickupBeats, rows };
}
