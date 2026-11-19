// A real recording's sync map (docs/youtube-sync-plan.md): how a YouTube performance runs against one of our
// templates. Pure, unit-tested in tests/sync-map.test.js; the video clock (js/video-clock.js) gives the video's time.
//
// A map is `{ form, anchors }`:
// - `form`: the performance in order, `[{ kind: 'section', section, shift, cents, words } | { kind: 'gap', beats }]`.
//   A section is one of the template's sections (by index), sung `shift` semitones from the template's key and `cents`
//   off equal temperament; `words: false` hides its words (a verse whose words the template doesn't carry). A gap is
//   bars of music with nothing of ours in them (an organ interlude).
// - `anchors`: `[[videoSeconds, performanceBeat], ...]`, both strictly increasing, made by a person tapping bar lines
//   (and the mic step's suggestions they accepted). Between two anchors the beat moves evenly; before the first and
//   after the last there is nothing of ours (the intro, the applause). No tempo is ever stored or read: the anchors are
//   the timing.
//
// The performance timeline lays the form's segments end to end, in beats from 0. `unroll` builds a template of the
// whole performance in that timeline, so the lane, the words and the scoring work on it unchanged, with the lane's
// position the performance beat at the template's tempo.

import { transposeKey } from './staff-layout.js';
import { bars, barAt } from './theory.js';

const EPS = 1e-6;
const pc = (n) => ((n % 12) + 12) % 12; // chord roots are pitch classes (0 = C), not steps from the key

/** Each segment of the form with its place: `{ ...segment, index, start, end, from }` (from: its template beat 0). */
export function segments(template, form) {
    let at = 0;
    return (form ?? []).map((segment, index) => {
        const section = segment.kind === 'section' ? template.sections[segment.section] : null;
        const from = section ? section.startBeat : 0;
        const beats = section ? section.endBeat - section.startBeat : segment.beats;
        const placed = { ...segment, index, start: at, end: at + beats, from };
        at += beats;
        return placed;
    });
}

/** The performance's length in beats. */
export function performanceBeats(template, form) {
    const all = segments(template, form);
    return all.length ? all[all.length - 1].end : 0;
}

/** The performance beat at `seconds` of the video, or null outside the anchors (the intro, the ending). */
export function beatAt(anchors, seconds) {
    if (!anchors?.length || seconds < anchors[0][0] - EPS || seconds > anchors[anchors.length - 1][0] + EPS) return null;
    for (let i = 1; i < anchors.length; i++) {
        const [t0, b0] = anchors[i - 1];
        const [t1, b1] = anchors[i];
        if (seconds <= t1 + EPS) return b0 + ((b1 - b0) * (seconds - t0)) / (t1 - t0);
    }
    return anchors[0][1];
}

/** The video's time at performance beat `beat` (tapping a bar seeks the video there), or null outside the anchors. */
export function secondsAt(anchors, beat) {
    if (!anchors?.length || beat < anchors[0][1] - EPS || beat > anchors[anchors.length - 1][1] + EPS) return null;
    for (let i = 1; i < anchors.length; i++) {
        const [t0, b0] = anchors[i - 1];
        const [t1, b1] = anchors[i];
        if (beat <= b1 + EPS) return t0 + ((t1 - t0) * (beat - b0)) / (b1 - b0);
    }
    return anchors[0][0];
}

/** Where performance beat `beat` falls: `{ segment, templateBeat }`, or null past the end (a gap has no template beat). */
export function placeOf(template, form, beat) {
    const segment = segments(template, form).find((s) => beat >= s.start - EPS && beat < s.end - EPS);
    if (!segment) return null;
    return { segment, templateBeat: segment.kind === 'section' ? segment.from + (beat - segment.start) : null };
}

/** What's wrong with a map, as codes (empty when it's sound): the admin tool shows them, the server checks the same. */
export function mapProblems(template, map) {
    const problems = [];
    const form = map?.form;
    if (!Array.isArray(form) || !form.length) problems.push('form.empty');
    for (const s of form ?? []) {
        if (s.kind === 'section') {
            if (!Number.isInteger(s.section) || !template.sections[s.section]) problems.push('form.section');
            if (!Number.isInteger(s.shift ?? 0) || Math.abs(s.shift ?? 0) > 11) problems.push('form.shift');
            if (!Number.isFinite(s.cents ?? 0) || Math.abs(s.cents ?? 0) > 50) problems.push('form.cents');
        } else if (s.kind === 'gap') {
            if (!(s.beats > 0)) problems.push('form.gap');
        } else problems.push('form.kind');
    }
    if (Array.isArray(form) && form.length && !form.some((s) => s.kind === 'section')) problems.push('form.sections');
    const anchors = map?.anchors;
    if (!Array.isArray(anchors) || anchors.length < 2) problems.push('anchors.few');
    else {
        const length = problems.length ? Infinity : performanceBeats(template, form);
        for (let i = 0; i < anchors.length; i++) {
            const [t, b] = anchors[i] ?? [];
            if (!Number.isFinite(t) || !Number.isFinite(b) || t < 0 || b < 0 || b > length + EPS) {
                problems.push('anchors.value');
                break;
            }
            if (i && (t <= anchors[i - 1][0] || b <= anchors[i - 1][1])) {
                problems.push('anchors.order');
                break;
            }
        }
    }
    return [...new Set(problems)];
}

/**
 * The whole performance as one template: each section's notes moved to its place in the timeline and transposed by its
 * shift, its words kept unless `words: false`, gaps left empty. The key is the first section's; a section lifted further
 * (a last verse up a step) carries its own shift in its pitches and its chords (whose roots are pitch classes). One section, one phrase list, named as
 * the template names them, so a verse sung three times shows "Verse 1" three times unless the form names it.
 */
export function unroll(template, form) {
    const placed = segments(template, form);
    const sung = placed.filter((s) => s.kind === 'section');
    const base = sung[0]?.shift ?? 0;
    const shifts = sung.length ? sung.map((s) => s.shift ?? 0) : [0];
    const parts = template.parts.map((part) => ({
        ...part,
        range: shiftRange(part.range, Math.min(...shifts), Math.max(...shifts)),
        guide: {
            ...part.guide,
            notes: sung.flatMap((s) => {
                const section = template.sections[s.section];
                return part.guide.notes
                    .filter((n) => n.beat >= section.startBeat - EPS && n.beat < section.endBeat - EPS)
                    .map((n) => {
                        const note = { ...n, beat: s.start + (n.beat - section.startBeat), pitch: n.pitch + (s.shift ?? 0) };
                        if (s.words === false) delete note.lyric;
                        return note;
                    });
            }),
        },
    }));
    const chords = sung.flatMap((s) => {
        const section = template.sections[s.section];
        return (template.chords ?? [])
            .filter((c) => c.beat >= section.startBeat - EPS && c.beat < section.endBeat - EPS)
            .map((c) => ({
                ...c,
                beat: s.start + (c.beat - section.startBeat),
                root: pc(c.root + (s.shift ?? 0)),
                bass: c.bass == null ? c.bass : pc(c.bass + (s.shift ?? 0)),
            }));
    });
    const sections = sung.map((s) => {
        const section = template.sections[s.section];
        const move = (beat) => s.start + (beat - section.startBeat);
        return { ...section, name: s.name ?? section.name, startBeat: s.start, endBeat: s.end, phrases: section.phrases.map(([a, b]) => [move(a), move(b)]) };
    });
    const unrolled = { ...template, key: transposeKey(template.key, base), parts, chords, sections };
    // The pickup belongs to the song's first section only: a performance starting on a gap or a later section has none.
    // (The lane's own bar grid can still drift where a song's length isn't whole bars; barLines is the true one.)
    if (placed[0]?.kind !== 'section' || template.sections[placed[0].section]?.startBeat > 0) unrolled.pickupBeats = 0;
    return unrolled;
}

/**
 * Every bar line of the performance, in performance beats, ending with its length: each section's own bars (its pickup
 * a short bar where the template has one, wherever the section starts), and a gap's whole bars of the meter around it.
 * The lane's bars, the sync tool's taps and the mic step's anchors all count on these, so a verse sung again starts on
 * a bar line even where the song's length isn't whole bars.
 */
export function barLines(template, form) {
    const lines = [];
    for (const s of segments(template, form)) {
        if (s.kind === 'section') {
            const section = template.sections[s.section];
            for (const bar of bars(template, section.endBeat, section.startBeat)) lines.push(s.start + (bar.start - section.startBeat));
        } else {
            const per = barAt(template, 0).beats;
            for (let at = s.start; at < s.end - EPS; at += per) lines.push(at);
        }
    }
    const end = performanceBeats(template, form);
    return [...new Set(lines.map((b) => Math.round(b * 1e6) / 1e6))].filter((b) => b < end - EPS).concat(end);
}

/** The cents of the section sung at performance beat `beat` (0 in a gap or outside): pitch feedback aims there. */
export function centsAt(template, form, beat) {
    const place = placeOf(template, form, beat);
    return place?.segment.kind === 'section' ? (place.segment.cents ?? 0) : 0;
}

function shiftRange(range, low, high) {
    return range ? { low: range.low + low, high: range.high + high } : range;
}
