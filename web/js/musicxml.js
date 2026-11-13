// A song as sheet music (docs/sheet-music-plan.md, slice 1): MusicXML 4.0 (partwise), for MuseScore,
// Sibelius, Dorico and the rest. Pure: a template in, the file's text out, unit-tested in
// tests/sheet-music.test.js. Each part is a staff; notes split at bar lines are tied; words go under
// their notes with MusicXML's syllables; a breath (`br`) is a breath mark on the note before it; the
// chords go over the melody. Pitches are spelled in the song's key, as the Staff spells them.

import { keySignature, spell } from './sing.js';
import { transposeKey } from './theme.js';
import { bars as barsIn, meterChanges } from './theory.js';

export const DIVISIONS = 24; // per quarter note: halves, thirds, quarters and eighths of a beat are whole numbers
const STEPS = 'CDEFGAB';

// The note values a duration is written with (in divisions): longest first. Triplets carry 3:2.
const VALUES = [
    { d: 96, type: 'whole' }, { d: 72, type: 'half', dot: true }, { d: 48, type: 'half' }, { d: 36, type: 'quarter', dot: true },
    { d: 24, type: 'quarter' }, { d: 18, type: 'eighth', dot: true }, { d: 16, type: 'quarter', triplet: true }, { d: 12, type: 'eighth' },
    { d: 9, type: '16th', dot: true }, { d: 8, type: 'eighth', triplet: true }, { d: 6, type: '16th' }, { d: 4, type: '16th', triplet: true }, { d: 3, type: '32nd' },
];
const KINDS = { maj: 'major', min: 'minor', dom7: 'dominant', maj7: 'major-seventh', min7: 'minor-seventh', maj6: 'major-sixth', min6: 'minor-sixth', dim: 'diminished', dim7: 'diminished-seventh', hdim7: 'half-diminished', aug: 'augmented', sus2: 'suspended-second', sus4: 'suspended-fourth', add9: 'major' };

const escape = (text) => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** A time signature's bar and beat: `{ beats, beatType, perBar }`, perBar in our beats (6/8's beat a dotted quarter). */
export function metreOf(timeSignature) {
    const [beats, beatType] = String(timeSignature || '4/4').split('/').map(Number);
    return { beats, beatType, perBar: beatType === 8 ? beats / 3 : beats, quarters: beatType === 8 ? 1.5 : 1 };
}

/**
 * Quarter notes from the song's start to `beat`. A beat is a dotted quarter in 6/8 and a quarter in 4/4, so a song whose
 * time signature changes (`meters`, docs/meters-plan.md) adds them up metre by metre; one metre is beat x quarters.
 */
export function quartersAt(template, beat) {
    let quarters = 0;
    let from = 0;
    let metre = metreOf(template.timeSignature);
    for (const change of meterChanges(template)) {
        if (change.beat >= beat) break;
        quarters += (change.beat - from) * metre.quarters;
        from = change.beat;
        metre = metreOf(change.timeSignature);
    }
    return quarters + (beat - from) * metre.quarters;
}

/** The durations (divisions) a length is written with, longest first; a length no value fits is rounded to the grid. */
export function valuesFor(duration) {
    const out = [];
    let left = Math.round(duration);
    for (const value of VALUES) {
        while (left >= value.d) {
            out.push(value);
            left -= value.d;
        }
    }
    if (left > 0 && out.length) out[out.length - 1] = { ...out.at(-1), d: out.at(-1).d + left, odd: true };
    return out;
}

/** A part's clef, as the Staff names them: bass for a bass, treble with an 8 below for a low voice, else treble. */
export function clefNameOf(part) {
    if (part.key === 'bass') return 'bass';
    const notes = part.guide?.notes ?? [];
    const middle = notes.length ? [...notes].map((n) => n.pitch).sort((a, b) => a - b)[notes.length >> 1] : 67;
    return ['tenor', 'lead', 'baritone'].includes(part.key) || middle < 58 ? 'treble8' : 'treble';
}

const CLEF_XML = {
    bass: '<clef><sign>F</sign><line>4</line></clef>',
    treble8: '<clef><sign>G</sign><line>2</line><clef-octave-change>-1</clef-octave-change></clef>',
    treble: '<clef><sign>G</sign><line>2</line></clef>',
};
const clefOf = (part) => CLEF_XML[clefNameOf(part)];

/** Where a part's words go: MusicXML's single, begin, middle or end, from our hyphens ("val-" goes on). */
function syllables(notes) {
    let open = false;
    return notes.map((n) => {
        if (!n.lyric) return null;
        const goesOn = /-$/.test(n.lyric);
        const syllabic = open ? (goesOn ? 'middle' : 'end') : goesOn ? 'begin' : 'single';
        open = goesOn;
        return { text: n.lyric.replace(/-$/, ''), syllabic };
    });
}

function pitchXml(pitch, key) {
    const s = spell(pitch, key);
    return `<pitch><step>${STEPS[s.letter]}</step>${s.alter ? `<alter>${s.alter}</alter>` : ''}<octave>${s.octave}</octave></pitch>`;
}

function harmonyXml(chord, key, offset) {
    const root = spell(60 + chord.root, key);
    const bass = chord.bass === null || chord.bass === undefined ? '' : (() => {
        const b = spell(60 + chord.bass, key);
        return `<bass><bass-step>${STEPS[b.letter]}</bass-step>${b.alter ? `<bass-alter>${b.alter}</bass-alter>` : ''}</bass>`;
    })();
    return `<harmony><root><root-step>${STEPS[root.letter]}</root-step>${root.alter ? `<root-alter>${root.alter}</root-alter>` : ''}</root><kind>${KINDS[chord.quality] ?? 'major'}</kind>${bass}${offset ? `<offset>${offset}</offset>` : ''}</harmony>`;
}

/**
 * One part's bars as what's written in each (for this writer and the printed parts, js/print-score.js):
 * its notes on the grid and the rests between, split at bar lines into note values and tied, with
 * the words and breath marks. `bars`: [{ start, length }] in divisions. Each bar is a list of items,
 * `{ at, d, type, dot, triplet, rest, pitch, tieIn, tieOut, word, breath, note }` (`at` from the bar's
 * start, `note` the written note's index, the same on each tied piece), and its chords (for the
 * melody), `{ at, chord }`.
 */
export function measuresOf(part, template, bars, { melody = false } = {}) {
    const at = (beat) => Math.round(quartersAt(template, beat) * DIVISIONS); // a beat in divisions, metre by metre
    const notes = [...(part.guide?.notes ?? [])].sort((a, b) => a.beat - b.beat);
    const words = syllables(notes);
    // The part as events: a note or a rest, start and end in divisions.
    const events = [];
    let upTo = 0;
    notes.forEach((n, i) => {
        const start = at(n.beat);
        const end = Math.max(start + 1, at(n.beat + n.length));
        if (start > upTo) events.push({ start: upTo, end: start, rest: true });
        events.push({ start: Math.max(start, upTo), end, pitch: n.pitch, word: words[i], breath: !!notes[i + 1]?.br, note: i });
        upTo = end;
    });
    const songEnd = bars.at(-1).start + bars.at(-1).length;
    if (upTo < songEnd) events.push({ start: upTo, end: songEnd, rest: true });
    const chords = melody ? [...(template.chords ?? [])].map((c) => ({ ...c, at: at(c.beat) })) : [];
    return bars.map((bar) => {
        const end = bar.start + bar.length;
        const items = [];
        const barChords = [];
        for (const ev of events) {
            const from = Math.max(ev.start, bar.start);
            const to = Math.min(ev.end, end);
            if (to <= from) continue;
            // The chords that change in this stretch, over the note that starts it.
            for (const chord of chords.filter((c) => c.at >= from && c.at < to)) barChords.push({ at: chord.at - bar.start, offset: chord.at - from, chord });
            let x = from - bar.start;
            const values = valuesFor(to - from);
            values.forEach((value, v) => {
                const tieIn = !ev.rest && (from > ev.start || v > 0);
                const tieOut = !ev.rest && (to < ev.end || v < values.length - 1);
                items.push({ at: x, ...value, rest: !!ev.rest, pitch: ev.pitch ?? null, tieIn, tieOut, word: !ev.rest && !tieIn ? ev.word : null, breath: !ev.rest && ev.breath && !tieOut, note: ev.note ?? null });
                x += value.d;
            });
        }
        return { items, chords: barChords };
    });
}

/** The tempo mark for a metre: the beat's note value (a dotted quarter in 6/8) and the song's beats a minute. */
function tempoXml(metre, tempo) {
    const unit = metre.beatType === 8 ? '<beat-unit>quarter</beat-unit><beat-unit-dot/>' : '<beat-unit>quarter</beat-unit>';
    return `<direction placement="above"><direction-type><metronome>${unit}<per-minute>${tempo}</per-minute></metronome></direction-type><sound tempo="${Math.round(tempo * metre.quarters)}"/></direction>`;
}

/** One part's measures as MusicXML, from measuresOf, with the attributes and tempo in the first. */
function partMeasures(part, template, bars, { first, melody }) {
    const key = template.key || 'C';
    const metre = metreOf(template.timeSignature);
    const fifths = (() => {
        const sig = keySignature(key);
        return sig.type === 'flat' ? -sig.count : sig.count;
    })();
    return measuresOf(part, template, bars, { melody }).map(({ items, chords }, b) => {
        const bar = bars[b];
        let xml = `<measure number="${b + (bars[0].pickup ? 0 : 1)}"${bar.pickup ? ' implicit="yes"' : ''}>`;
        if (b === 0) {
            xml += `<attributes><divisions>${DIVISIONS}</divisions><key><fifths>${fifths}</fifths><mode>${/m$/.test(key) ? 'minor' : 'major'}</mode></key>`
                + `<time><beats>${metre.beats}</beats><beat-type>${metre.beatType}</beat-type></time>${clefOf(part)}</attributes>`;
            if (first) xml += tempoXml(metre, template.tempo);
        } else if (bar.timeSignature && bar.timeSignature !== bars[b - 1].timeSignature) {
            // The metre changes here (docs/meters-plan.md): its time signature, and the beat's note value for the tempo.
            const now = metreOf(bar.timeSignature);
            xml += `<attributes><time><beats>${now.beats}</beats><beat-type>${now.beatType}</beat-type></time></attributes>`;
            if (first) xml += tempoXml(now, template.tempo);
        }
        for (const item of items) {
            // A chord sits over the first note value of the stretch where it changes.
            for (const c of chords.filter((c) => c.at - c.offset === item.at)) xml += harmonyXml(c.chord, key, c.offset);
            xml += '<note>';
            xml += item.rest ? '<rest/>' : pitchXml(item.pitch, key);
            xml += `<duration>${item.d}</duration>`;
            if (item.tieIn) xml += '<tie type="stop"/>';
            if (item.tieOut) xml += '<tie type="start"/>';
            xml += `<type>${item.type}</type>${item.dot ? '<dot/>' : ''}`;
            if (item.triplet) xml += '<time-modification><actual-notes>3</actual-notes><normal-notes>2</normal-notes></time-modification>';
            const marks = [];
            if (item.tieIn) marks.push('<tied type="stop"/>');
            if (item.tieOut) marks.push('<tied type="start"/>');
            if (item.breath) marks.push('<articulations><breath-mark/></articulations>');
            if (marks.length) xml += `<notations>${marks.join('')}</notations>`;
            if (item.word) xml += `<lyric number="1"><syllabic>${item.word.syllabic}</syllabic><text>${escape(item.word.text)}</text></lyric>`;
            xml += '</note>';
        }
        if (b === bars.length - 1) xml += '<barline location="right"><bar-style>light-heavy</bar-style></barline>';
        return xml + '</measure>';
    }).join('');
}

/**
 * The rights line for a song's file. A library song (it has a licence): our arrangement under CC BY-SA
 * 4.0, credited to Stackapella, with its public-domain tune and words said as such. A person's own
 * song: their own credits only (we claim nothing in it).
 */
export function rightsOf(template) {
    const credits = template.credits ?? {};
    const licence = template.licence;
    const pd = (what) => (/^public domain/i.test(licence?.[what] ?? '') ? ' (public domain)' : '');
    // The file's rights line, in English like the licence it names, whatever the app's language.
    const lines = [];
    if (credits.tune) lines.push(`Tune: ${credits.tune}${pd('tune')}.`); // i18n-ignore
    if (credits.words) lines.push(`Words: ${credits.words}${pd('words')}.`); // i18n-ignore
    if (licence) lines.push('Arrangement: Stackapella (https://stackapella.com), CC BY-SA 4.0 (https://creativecommons.org/licenses/by-sa/4.0/).'); // i18n-ignore
    return lines.join(' ') || null;
}

/** The bars a song spans, in divisions: a pickup bar if it has one, then whole bars to its last note. */
export function barsOf(template) {
    if (meterChanges(template).length) {
        // A song whose time signature changes: its bars where they fall, each with its own time signature.
        const endBeat = Math.max(...template.parts.flatMap((p) => (p.guide?.notes ?? []).map((n) => n.beat + n.length)), 0);
        const at = (beat) => Math.round(quartersAt(template, beat) * DIVISIONS);
        return barsIn(template, endBeat).map((bar, i) => ({
            start: at(bar.start),
            length: at(bar.start + bar.beats) - at(bar.start),
            timeSignature: bar.timeSignature,
            ...(i === 0 && (template.pickupBeats ?? 0) > 0 ? { pickup: true } : {}),
        }));
    }
    const metre = metreOf(template.timeSignature);
    const scale = metre.quarters * DIVISIONS;
    const bar = metre.perBar * scale;
    const pickup = Math.round((template.pickupBeats ?? 0) * scale);
    const ends = template.parts.flatMap((p) => (p.guide?.notes ?? []).map((n) => Math.round((n.beat + n.length) * scale)));
    const end = Math.max(pickup + bar, ...ends);
    const bars = pickup ? [{ start: 0, length: pickup, pickup: true }] : [];
    for (let start = pickup; start < end; start += bar) bars.push({ start, length: bar });
    return bars;
}

/**
 * The song as the studio has it: moved by `transpose` semitones (its key, notes and chords), at
 * `tempo` (default the song's), so the sheet matches what's sung. For both writers.
 */
export function asSung(template, { transpose = 0, tempo = null } = {}) {
    const pc = (n) => (n == null ? n : (((n + transpose) % 12) + 12) % 12);
    return {
        ...template,
        key: transposeKey(template.key, transpose),
        tempo: tempo || template.tempo,
        chords: (template.chords ?? []).map((c) => ({ ...c, root: pc(c.root), bass: pc(c.bass) })),
        parts: template.parts.map((p) => (p.guide?.notes ? { ...p, guide: { ...p.guide, notes: p.guide.notes.map((n) => ({ ...n, pitch: n.pitch + transpose })) } } : p)),
    };
}

/**
 * The song as MusicXML text. `partKeys`: the parts to write (default every part with notes), in the
 * template's order. `rights`: the credit and licence line (rightsOf's, by default).
 */
export function toMusicXML(template, { partKeys = null, rights = rightsOf(template), date = new Date() } = {}) {
    const parts = template.parts.filter((p) => (p.guide?.notes ?? []).length && (!partKeys || partKeys.includes(p.key)));
    if (!parts.length) throw new Error('No written parts to export');
    const bars = barsOf({ ...template, parts });
    const melodyKey = (template.parts.find((p) => p.melody) ?? template.parts[0]).key;
    const credits = template.credits ?? {};
    const creators = [
        credits.tune ? `<creator type="composer">${escape(credits.tune)}</creator>` : '',
        credits.words ? `<creator type="lyricist">${escape(credits.words)}</creator>` : '',
        credits.arrangement ? `<creator type="arranger">${escape(credits.arrangement)}</creator>` : '',
    ].join('');
    const list = parts.map((p, i) => `<score-part id="P${i + 1}"><part-name>${escape(p.name ?? p.key)}</part-name><score-instrument id="P${i + 1}-I1"><instrument-name>${escape(p.name ?? p.key)}</instrument-name></score-instrument><midi-instrument id="P${i + 1}-I1"><midi-channel>${i + 1}</midi-channel><midi-program>53</midi-program></midi-instrument></score-part>`).join('');
    const body = parts.map((p, i) => `<part id="P${i + 1}">${partMeasures(p, template, bars, { first: i === 0, melody: p.key === melodyKey })}</part>`).join('');
    return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>\n'
        + '<!DOCTYPE score-partwise PUBLIC "-//Recordare//DTD MusicXML 4.0 Partwise//EN" "http://www.musicxml.org/dtds/partwise.dtd">\n'
        + '<score-partwise version="4.0">'
        + `<work><work-title>${escape(template.title ?? '')}</work-title></work>`
        + `<identification>${creators}${rights ? `<rights>${escape(rights)}</rights>` : ''}<encoding><software>Stackapella</software><encoding-date>${date.toISOString().slice(0, 10)}</encoding-date></encoding></identification>`
        + `<part-list>${list}</part-list>${body}</score-partwise>\n`;
}
