// Sheet music in (docs/sheet-music-plan.md, slices 3 and 4): a MusicXML file (or a compressed .mxl)
// or, as the fallback, a MIDI file, read on the device into the lines it holds, for the singer to
// pick from. Pure: a file in, its lines out, unit-tested in tests/sheet-import.test.js.
//
// Patents (docs/sheet-music-patent-notes.md, for the lawyer): nothing here works out which line is
// which part. A line is what the file encodes (a part's numbered voice, or a MIDI track's channel),
// listed in the file's order with the file's own names. The singer picks which lines to keep and
// what each one sings as. Never add guessing from range, order, rhythm, words or layout here.

import { unzip } from './zip.js';

const STEP_PC = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };
const MAJOR_KEYS = ['B', 'Gb', 'Db', 'Ab', 'Eb', 'Bb', 'F', 'C', 'G', 'D', 'A', 'E', 'B', 'F#', 'C#']; // fifths -7 to 7 (Cb written as B)
const MINOR_KEYS = ['Abm', 'Ebm', 'Bbm', 'Fm', 'Cm', 'Gm', 'Dm', 'Am', 'Em', 'Bm', 'F#m', 'C#m', 'G#m', 'D#m', 'Bbm']; // A#m written as Bbm; key names, not words: i18n-ignore
// MusicXML's chord kinds as our qualities; ninths and the rest as their nearest.
const QUALITIES = {
    major: 'maj', minor: 'min', dominant: 'dom7', 'major-seventh': 'maj7', 'minor-seventh': 'min7', 'major-sixth': 'maj6', 'minor-sixth': 'min6', diminished: 'dim',
    'diminished-seventh': 'dim7', 'half-diminished': 'hdim7', augmented: 'aug', 'suspended-second': 'sus2', 'suspended-fourth': 'sus4',
    'major-ninth': 'maj7', 'dominant-ninth': 'dom7', 'minor-ninth': 'min7', 'augmented-seventh': 'aug', power: 'maj',
};
export const MAX_LINES = 4; // our songs have 2 to 4 sung parts
const EPSILON = 1e-6;
// Six decimal places at most (MySQL's JSON column changes longer numbers); lengths are taken from rounded ends, so a
// bar's notes still add up to the bar.
const round = (beat) => Math.round(beat * 1e6) / 1e6;

/** The key's name from MusicXML's fifths and mode, as our keys are written ("G", "Bbm"). */
export function keyFromFifths(fifths, minor = false) {
    const i = Math.max(-7, Math.min(7, Math.round(fifths))) + 7;
    return (minor ? MINOR_KEYS : MAJOR_KEYS)[i];
}

/**
 * The metre as we can sing it: our four (4/4, 3/4, 6/8, 2/4), or one that's the same bars written
 * another way (2/2 and 4/2 as 4/4, 3/2 and 6/4 as 3/4 bars, 12/8 as 6/8). `per` is how many of our
 * bars one of the file's bars makes. Others can't be taken yet: null.
 */
export function metreFor(beats, beatType) {
    const sig = `${beats}/${beatType}`;
    if (['4/4', '3/4', '6/8', '2/4'].includes(sig)) return { timeSignature: sig, per: 1 };
    return { '2/2': { timeSignature: '4/4', per: 1 }, '4/2': { timeSignature: '4/4', per: 2 }, '3/2': { timeSignature: '3/4', per: 2 }, '6/4': { timeSignature: '3/4', per: 2 }, '12/8': { timeSignature: '6/8', per: 2 } }[sig] ?? null;
}

/** Quarter notes to our beats: 6/8's beat is a dotted quarter, the others' a quarter. */
const toBeats = (quarters, timeSignature) => (timeSignature === '6/8' ? quarters / 1.5 : quarters);

const text = (el, selector) => el?.querySelector(selector)?.textContent?.trim() ?? null;
const childrenNamed = (el, name) => [...el.children].filter((c) => c.tagName === name);

/** A measure's navigation marks, from its directions' sounds and words: segno, coda, To Coda, Fine, D.C., D.S. */
function marksOf(m) {
    const sounds = [...m.querySelectorAll('sound')];
    const has = (attr) => sounds.some((sd) => sd.hasAttribute(attr) && sd.getAttribute(attr) !== 'no');
    const words = [...m.querySelectorAll('direction-type words')].map((w) => w.textContent.trim());
    const said = (re) => words.some((w) => re.test(w));
    const toCoda = has('tocoda') || said(/\bto\s+coda\b/i);
    return {
        segno: has('segno') || !!m.querySelector('direction-type segno'),
        // The coda the music jumps to (not the To Coda sign, which some editors also draw as a coda).
        coda: has('coda') || (!toCoda && !!m.querySelector('direction-type coda')),
        toCoda,
        fine: has('fine') || said(/^fine\.?$/i),
        dc: has('dacapo') || said(/^d\.?\s?c\.?(\s|$)|^da capo/i),
        ds: has('dalsegno') || said(/^d\.?\s?s\.?(\s|$)|^dal segno/i),
    };
}

/**
 * The measures in the order they're sung: repeat signs and first and second endings written out, and D.C. or D.S.
 * followed once, as printed: back to the start or the segno, on to Fine, or from To Coda to the coda. After the jump
 * repeats aren't taken again and only a set of endings' last is sung (the usual reading). Returns `{ order, passes,
 * repeats, jump }`: `passes` is each measure's time through its repeat (1, 2...), for the verse sung on it, and
 * `jump` is null or `{ kind: 'dc' | 'ds', fine, coda }`.
 */
export function playOrder(measures) {
    const order = [];
    const passes = [];
    const marks = measures.map(marksOf);
    const endingStart = (m) => childrenNamed(m, 'barline').flatMap((b) => childrenNamed(b, 'ending')).find((e) => e.getAttribute('type') === 'start');
    const endsAnEnding = (m) => childrenNamed(m, 'barline').some((b) => b.querySelector('ending[type="stop"], ending[type="discontinue"]'));
    const has = (m, location, direction) => childrenNamed(m, 'barline').some((b) => (b.getAttribute('location') ?? 'right') === location && b.querySelector(`repeat[direction="${direction}"]`));
    /** The measure an ending starting at `i` stops at. */
    const endingEnd = (i) => {
        let j = i;
        while (j < measures.length - 1 && !endsAnEnding(measures[j])) j++;
        return j;
    };
    let start = 0;
    let repeats = 0;
    let pass = 1;
    const taken = new Map(); // a backward repeat's index: how many times it's been taken
    let jump = null;
    let i = 0;
    while (i < measures.length && order.length < measures.length * 6) {
        const m = measures[i];
        if (!jump && has(m, 'left', 'forward')) {
            if (!taken.has(`from${i}`)) pass = 1;
            start = i;
        }
        const e = endingStart(m);
        if (e) {
            const stop = endingEnd(i);
            const numbers = (e.getAttribute('number') ?? '1').split(/[,\s]+/).filter(Boolean).map(Number);
            // After a D.C. or D.S., only the set's last ending; before it, the ending for this time through.
            const sing = jump ? !endingStart(measures[stop + 1] ?? m) || stop + 1 >= measures.length : numbers.includes(pass);
            if (!sing) {
                i = stop + 1;
                continue;
            }
        }
        order.push(i);
        passes.push(pass);
        if (!jump && endsAnEnding(m) && !has(m, 'right', 'backward')) pass = 1; // the set's last ending: past the repeat
        if (jump && marks[i].fine) break;
        if (jump && marks[i].toCoda) {
            const coda = marks.findIndex((k, j) => j > i && k.coda);
            if (coda > i) {
                jump.coda = true;
                i = coda;
                continue;
            }
        }
        if (!jump && has(m, 'right', 'backward')) {
            const times = Number(childrenNamed(m, 'barline').find((b) => b.querySelector('repeat[direction="backward"]'))?.querySelector('repeat').getAttribute('times') ?? 2);
            const done = taken.get(i) ?? 0;
            if (done + 1 < times) {
                taken.set(i, done + 1);
                taken.set(`from${start}`, true);
                repeats++;
                pass = done + 2;
                i = start;
                continue;
            }
            pass = 1;
            start = i + 1;
        }
        if (!jump && (marks[i].dc || marks[i].ds)) {
            const to = marks[i].dc ? 0 : Math.max(0, marks.findIndex((k) => k.segno));
            jump = { kind: marks[i].dc ? 'dc' : 'ds', fine: marks.some((k) => k.fine), coda: false };
            pass = 1;
            i = to;
            continue;
        }
        i++;
    }
    return { order, passes, repeats, jump };
}

/** A lyric's words as printed: its syllables joined at an elision ("o'‿clock"), an em dash read as a comma. */
function lyricText(lyric) {
    let out = '';
    for (const child of lyric.children) {
        if (child.tagName === 'text') out += child.textContent;
        else if (child.tagName === 'elision') out += child.textContent || '‿';
    }
    out = out.trim().replace(/\s*—\s*/g, ', ').replace(/,\s*$/, ',');
    return out === '_' ? '' : out;
}

/**
 * Each note's word for verse `verseOf(note)`: where a bar (one time through it, `n`) has no words for that verse, they're
 * sung as printed once, so verse 1's.
 */
function wordsFor(notes, verseOf) {
    const worded = new Set(notes.filter((x) => x.lyrics?.[verseOf(x)]).map((x) => x.n));
    return notes.map((x) => {
        const { lyric, ...rest } = x;
        const verse = verseOf(x);
        const said = x.lyrics?.[verse] ?? (worded.has(x.n) ? null : x.lyrics?.[1] ?? (verse === 1 && x.lyrics ? Object.values(x.lyrics)[0] : null));
        return said ? { ...rest, lyric: said } : rest;
    });
}

/** Bars as printed, short: "3, 7–9, 12". */
export function barList(bars) {
    const nums = [...new Set(bars)].map(Number).filter(Number.isFinite).sort((a, b) => a - b);
    const out = [];
    for (let i = 0; i < nums.length; i++) {
        let j = i;
        while (j + 1 < nums.length && nums[j + 1] === nums[j] + 1) j++;
        out.push(j > i + 1 ? `${nums[i]}–${nums[j]}` : j === i + 1 ? `${nums[i]}, ${nums[j]}` : `${nums[i]}`);
        i = j;
    }
    return out.join(', ');
}

const TEMPO_WORDS = /\b(rall|rit|ritard|accel|a tempo|allarg|morendo|poco a poco|tempo primo|rubato)/i;
const SOLO_TUTTI = /^(solo|soli|tutti)\b/i;

/**
 * A MusicXML score read into its lines: `{ title, credits, key, timeSignature, tempo, pickupBeats, chords, sections,
 * lines, notices }`, or `{ unreadable }`, or `{ unsupported: reason }`. A line is one voice of one part as the file
 * numbers it: `{ id, name, staff, voice, notes, range, chordNotes, chordBars, words }`. A note is `{ beat, length,
 * pitches, lyric?, br? }`: `pitches`, lowest first, more than one where the voice has two notes at once (the singer
 * picks the top or the bottom). `sections` are where the song's sections start, `{ name, beat }`: each verse its own
 * pass through the music ("Verse 2"), or at rehearsal marks.
 */
export function readMusicXML(xml) {
    const doc = new DOMParser().parseFromString(xml, 'application/xml');
    const root = doc.documentElement;
    if (doc.querySelector('parsererror') || !root) return { unreadable: true };
    if (root.tagName === 'score-timewise') return { unsupported: 'timewise' };
    if (root.tagName !== 'score-partwise') return { unreadable: true };
    const names = Object.fromEntries([...root.querySelectorAll('part-list > score-part')].map((p) => [p.getAttribute('id'), text(p, 'part-name') || text(p, 'part-abbreviation') || '']));
    const notices = [];
    let metre = null;
    let originalTime = null;
    let fifths = 0;
    let minor = false;
    let tempo = null;
    let laterTimes = false;
    let laterTempos = false;
    let verses = 1;
    let graces = 0;
    let pickupQuarters = 0;
    let repeatsWritten = 0;
    let jump = null;
    let songQuarters = 0;
    let maxPass = 1;
    const chords = [];
    const lines = [];
    const rehearsals = []; // { name, quarters }, the first part's
    const bars = { fermatas: new Set(), tempoWords: new Set(), dynamics: new Set(), accents: new Set(), soloTutti: new Set() };

    childrenNamed(root, 'part').forEach((part, partIndex) => {
        const partId = part.getAttribute('id');
        const measures = childrenNamed(part, 'measure');
        const { order, passes, repeats, jump: j } = playOrder(measures);
        if (partIndex === 0) {
            repeatsWritten = repeats;
            jump = j;
        }
        let divisions = 1;
        let full = 4; // a whole bar, in quarters, as the time signature now says
        let at = 0; // quarters from the song's start, at this measure's start
        const voices = new Map(); // voice number: { notes, staff, open ties, slurs }
        order.forEach((index, n) => {
            const measure = measures[index];
            const bar = measure.getAttribute('number') ?? String(index + 1);
            const pass = passes[n];
            let pos = 0; // divisions into the measure
            let longest = 0;
            let lastStart = 0;
            for (const el of measure.children) {
                if (el.tagName === 'attributes') {
                    divisions = Number(text(el, 'divisions') ?? divisions);
                    const time = el.querySelector('time');
                    if (time && text(time, 'beats')) {
                        const sig = `${text(time, 'beats')}/${text(time, 'beat-type')}`;
                        full = (Number(text(time, 'beats')) * 4) / Number(text(time, 'beat-type')) || 4;
                        if (!originalTime) {
                            originalTime = sig;
                            metre = metreFor(Number(text(time, 'beats')), Number(text(time, 'beat-type')));
                        } else if (sig !== originalTime) laterTimes = true;
                    }
                    const key = el.querySelector('key');
                    if (key && partIndex === 0 && n === 0) {
                        fifths = Number(text(key, 'fifths') ?? 0);
                        minor = text(key, 'mode') === 'minor';
                    }
                } else if (el.tagName === 'direction' || el.tagName === 'sound') {
                    const sound = el.tagName === 'sound' ? el : el.querySelector('sound');
                    const bpm = Number(sound?.getAttribute('tempo'));
                    if (bpm) {
                        if (tempo == null) tempo = bpm;
                        else if (Math.abs(bpm - tempo) > 0.5) laterTempos = true;
                    }
                    const perMinute = Number(text(el, 'metronome per-minute'));
                    if (tempo == null && perMinute) {
                        const unit = text(el, 'metronome beat-unit');
                        const dotted = !!el.querySelector('metronome beat-unit-dot');
                        const q = { whole: 4, half: 2, quarter: 1, eighth: 0.5 }[unit] ?? 1;
                        tempo = perMinute * q * (dotted ? 1.5 : 1);
                    }
                    // What the guide doesn't sing yet, named in the report with its bars.
                    for (const w of el.querySelectorAll('direction-type words')) {
                        const said = w.textContent.trim();
                        if (TEMPO_WORDS.test(said)) bars.tempoWords.add(bar);
                        if (SOLO_TUTTI.test(said)) bars.soloTutti.add(bar);
                    }
                    if (el.querySelector('direction-type dynamics, direction-type wedge')) bars.dynamics.add(bar);
                    const mark = text(el, 'direction-type rehearsal');
                    if (mark && partIndex === 0) {
                        // Met again on a repeat or after a D.C.: that's its section again.
                        rehearsals.push({ name: mark, quarters: at + pos / divisions });
                    }
                } else if (el.tagName === 'harmony') {
                    const rootStep = text(el, 'root-step');
                    const quality = QUALITIES[text(el, 'kind')];
                    if (rootStep && quality) {
                        const pc = (step, alter) => (((STEP_PC[step] + Number(alter ?? 0)) % 12) + 12) % 12;
                        const offset = Number(text(el, 'offset') ?? 0);
                        const bass = text(el, 'bass-step');
                        chords.push({ quarters: at + (pos + offset) / divisions, root: pc(rootStep, text(el, 'root-alter')), quality, bass: bass ? pc(bass, text(el, 'bass-alter')) : null });
                    }
                } else if (el.tagName === 'backup') {
                    pos -= Number(text(el, 'duration') ?? 0);
                } else if (el.tagName === 'forward') {
                    pos += Number(text(el, 'duration') ?? 0);
                    longest = Math.max(longest, pos);
                } else if (el.tagName === 'note') {
                    if (el.querySelector('grace') || el.querySelector('cue')) {
                        if (el.querySelector('grace')) graces++;
                        continue;
                    }
                    const duration = Number(text(el, 'duration') ?? 0);
                    const chord = !!el.querySelector('chord');
                    const start = chord ? lastStart : pos;
                    const voiceNo = text(el, 'voice') ?? '1';
                    if (!voices.has(voiceNo)) voices.set(voiceNo, { notes: [], staff: Number(text(el, 'staff') ?? 1), ties: new Map(), restBefore: 0, breathNext: true, breathMark: false, slurs: 0 });
                    const v = voices.get(voiceNo);
                    const quarters = at + start / divisions;
                    const length = duration / divisions;
                    const pitchEl = el.querySelector('pitch');
                    if (el.querySelector('notations fermata')) bars.fermatas.add(bar);
                    if (el.querySelector('notations articulations accent, notations articulations strong-accent')) bars.accents.add(bar);
                    const slurs = [...el.querySelectorAll('notations slur')];
                    const underSlur = v.slurs > 0; // a slur open from an earlier note covers this one
                    if (!pitchEl) {
                        if (!chord) v.restBefore += length;
                    } else {
                        const midi = 12 * (Number(text(pitchEl, 'octave')) + 1) + STEP_PC[text(pitchEl, 'step')] + Math.round(Number(text(pitchEl, 'alter') ?? 0));
                        const tieStop = [...el.querySelectorAll('tie')].some((t) => t.getAttribute('type') === 'stop');
                        const tieStart = [...el.querySelectorAll('tie')].some((t) => t.getAttribute('type') === 'start');
                        const open = v.ties.get(midi);
                        const lyrics = [...el.querySelectorAll('lyric')];
                        const last = v.notes.at(-1);
                        if (tieStop && open && Math.abs(open.quarters + open.length - quarters) < EPSILON) {
                            open.length += length; // a tied note: one note
                            if (!tieStart) v.ties.delete(midi);
                        } else if (tieStop && open && open.quarters < quarters && quarters < open.quarters + open.length + EPSILON) {
                            if (!tieStart) v.ties.delete(midi); // another note of a tied chord: already held
                        } else if (!chord && underSlur && !lyrics.length && last && last.pitches.length === 1 && last.pitches[0] === midi && Math.abs(last.quarters + last.length - quarters) < EPSILON && !v.breathNext && v.restBefore < EPSILON) {
                            last.length += length; // a slurred pair on one pitch and one syllable: one held note, not a melisma
                            if (tieStart) v.ties.set(midi, last);
                        } else {
                            let note;
                            if (chord && last && Math.abs(last.quarters - quarters) < EPSILON) {
                                last.pitches.push(midi);
                                last.pitches.sort((a, b) => a - b);
                                note = last;
                            } else {
                                note = { quarters, length, pitches: [midi], bar, n, pass };
                                if (v.breathNext || v.restBefore >= 1 - EPSILON) {
                                    note.br = true;
                                    if (v.breathMark && v.restBefore < EPSILON) note.breathMark = true; // a breath with no rest to take it in
                                }
                                v.breathNext = false;
                                v.breathMark = false;
                                v.restBefore = 0;
                                if (lyrics.length) {
                                    verses = Math.max(verses, ...lyrics.map((l) => Number(l.getAttribute('number')) || 1), lyrics.length);
                                    note.lyrics = {};
                                    lyrics.forEach((l, k) => {
                                        const word = lyricText(l);
                                        const syllabic = [...l.querySelectorAll('syllabic')].at(-1)?.textContent.trim();
                                        if (word) note.lyrics[Number(l.getAttribute('number')) || k + 1] = word + (syllabic === 'begin' || syllabic === 'middle' ? '-' : '');
                                    });
                                }
                                v.notes.push(note);
                            }
                            if (tieStart) v.ties.set(midi, note);
                        }
                        if (el.querySelector('breath-mark, caesura, fermata')) {
                            v.breathNext = true; // a hymn's line ends at its fermata
                            v.breathMark = true;
                        }
                    }
                    for (const sl of slurs) v.slurs = Math.max(0, v.slurs + (sl.getAttribute('type') === 'start' ? 1 : sl.getAttribute('type') === 'stop' ? -1 : 0));
                    if (!chord) {
                        lastStart = pos;
                        pos += duration;
                        longest = Math.max(longest, pos);
                    }
                }
            }
            // A bar is as long as what's in it (an upbeat, a short last bar), or a whole bar when it's empty.
            const barQuarters = longest ? longest / divisions : full;
            if (partIndex === 0 && n === 0 && (measure.getAttribute('implicit') === 'yes' || barQuarters < full - EPSILON)) pickupQuarters = barQuarters;
            at += barQuarters;
        });
        if (partIndex === 0) {
            songQuarters = at;
            maxPass = Math.max(1, ...passes);
        }
        for (const [voiceNo, v] of voices) {
            if (!v.notes.length) continue;
            v.notes = wordsFor(v.notes, (x) => x.pass); // a repeat's second time through sings verse 2
            const pitches = v.notes.flatMap((x) => x.pitches);
            lines.push({
                id: `${partId}:${voiceNo}`,
                part: names[partId] ?? '',
                staff: v.staff,
                voice: Number(voiceNo),
                voicesInPart: voices.size,
                notes: v.notes,
                range: { low: Math.min(...pitches), high: Math.max(...pitches) },
                chordNotes: v.notes.filter((x) => x.pitches.length > 1).length,
                chordBars: [...new Set(v.notes.filter((x) => x.pitches.length > 1).map((x) => x.bar))],
                words: v.notes.filter((x) => x.lyric).length,
            });
        }
    });

    if (!lines.length) return { unsupported: 'empty' };
    if (!metre) return { unsupported: 'metre', metre: originalTime };
    // A metre change mid-song isn't taken yet (no song of ours has one): said plainly, rather than sung wrong.
    if (laterTimes) return { unsupported: 'metreChange', metre: originalTime };
    const ts = metre.timeSignature;
    const beats = (q) => round(toBeats(q, ts));

    // Verses: with no repeat to sing them on, each verse is its own pass through the music, in order, as a section; a
    // pass whose notes carry none of that verse's words is left out and named.
    const sections = [];
    let versesWritten = 0;
    const versesLeft = [];
    if (verses > 1 && !repeatsWritten) {
        const sung = [];
        for (let k = 1; k <= verses; k++) if (lines.some((l) => l.notes.some((x) => x.lyrics?.[k]))) sung.push(k);
        else versesLeft.push(k);
        if (sung.length > 1) {
            const chordsOnce = chords.splice(0);
            for (const line of lines) {
                const once = line.notes;
                // Each verse starts a line of the tune, with a breath taken from the last note of the verse before.
                line.notes = sung.flatMap((k, pass) => wordsFor(once, () => k).map((x, i) => ({ ...x, quarters: x.quarters + pass * songQuarters, n: `${pass}:${x.n}`, ...(pass && i === 0 ? { br: true, breathMark: true } : {}) })));
                line.words = line.notes.filter((x) => x.lyric).length;
            }
            sung.forEach((k, pass) => {
                sections.push({ name: `Verse ${k}`, quarters: pass * songQuarters }); // i18n-ignore: a song's section name, as the templates write them
                chords.push(...chordsOnce.map((c) => ({ ...c, quarters: c.quarters + pass * songQuarters })));
            });
            versesWritten = sung.length;
        }
    }
    // More verses than times through the repeats: the rest are named.
    if (repeatsWritten) for (let k = maxPass + 1; k <= verses; k++) versesLeft.push(k);
    if (!sections.length && rehearsals.length) {
        // Rehearsal marks as sections; music before the first mark is a section of its own.
        if (rehearsals[0].quarters > EPSILON) sections.push({ name: '', quarters: 0 });
        sections.push(...rehearsals);
    }

    // Each note ends where its rounded end is, so triplets meet the next note exactly and a bar's notes add up. A breath
    // marked with no rest before it takes half a beat from the note before (never moving an onset), as the arranger does.
    for (const line of lines) {
        line.notes = line.notes.map(({ quarters, length, lyrics, bar, n, pass, ...rest }) => ({ beat: beats(quarters), length: round(beats(quarters + length) - beats(quarters)), ...rest }));
        line.notes.forEach((x, i) => {
            if (!x.breathMark) return;
            delete x.breathMark;
            const before = line.notes[i - 1];
            if (before && Math.abs(before.beat + before.length - x.beat) < EPSILON) before.length = round(before.length - Math.min(0.5, before.length / 2));
        });
    }
    if (repeatsWritten) notices.push({ kind: 'repeats', count: repeatsWritten });
    if (jump) notices.push({ kind: 'jumpFollowed', jump: jump.kind, fine: jump.fine, coda: jump.coda });
    if (versesWritten) notices.push({ kind: 'versesWritten', count: versesWritten });
    else if (verses > 1 && !repeatsWritten) notices.push({ kind: 'verses', count: verses });
    if (versesLeft.length && (versesWritten || repeatsWritten)) notices.push({ kind: 'versesLeftOut', verses: versesLeft.join(', ') });
    if (laterTempos) notices.push({ kind: 'tempoChanges' });
    if (graces) notices.push({ kind: 'graces', count: graces });
    if (originalTime !== ts) notices.push({ kind: 'metreWritten', from: originalTime, to: ts });
    // What isn't sung yet, by bar: fermatas are held in time, the others are left to the singers.
    for (const [kind, set] of Object.entries(bars)) if (set.size) notices.push({ kind, bars: barList([...set]), count: set.size });
    for (const line of lines) if (line.chordBars.length) notices.push({ kind: 'divisi', line: line.id, part: line.part, voice: line.voice, bars: barList(line.chordBars), count: line.chordBars.length });
    const credits = { tune: text(root, 'identification creator[type="composer"]') ?? '', words: text(root, 'identification creator[type="lyricist"]') ?? '', arrangement: text(root, 'identification creator[type="arranger"]') ?? '' };
    return {
        title: text(root, 'work > work-title') || text(root, 'movement-title') || '',
        credits,
        key: keyFromFifths(fifths, minor),
        timeSignature: ts,
        tempo: Math.min(300, Math.max(30, Math.round((tempo ?? 90) / (ts === '6/8' ? 1.5 : 1)))), // MusicXML's tempo is in quarters
        pickupBeats: beats(pickupQuarters),
        // Chord symbols from any staff, one at a time (the same symbol on two staves is one).
        chords: chords.sort((a, b) => a.quarters - b.quarters).filter((c, i, all) => i === 0 || c.quarters > all[i - 1].quarters + EPSILON).map(({ quarters, ...c }) => ({ beat: beats(quarters), ...c })),
        sections: sections.map(({ name, quarters }) => ({ name, beat: beats(quarters) })),
        lines,
        notices,
    };
}

/** What a line can sing as: SATB or barbershop, never a mix (the templates' rule). */
export const SING_AS = ['soprano', 'alto', 'tenor', 'bass', 'lead', 'baritone'];
const SATB = new Set(['soprano', 'alto']);
const BARBERSHOP = new Set(['lead', 'baritone']);

/**
 * A row's voice when the file's own part name is a voice name ("Soprano", "S.", "Bass 1"): read
 * from the label its author wrote, never worked out from the notes (docs/sheet-music-patent-notes.md).
 * A part with two voices and a name like "Soprano Alto" gets nothing: the singer picks.
 */
export function namedVoice(line) {
    if (line.voicesInPart > 1) return null;
    const name = line.part.toLowerCase().replace(/[^a-z ]/g, ' ').trim();
    const LABELS = { soprano: ['soprano', 's', 'sop'], alto: ['alto', 'a', 'contralto'], tenor: ['tenor', 't', 'ten'], bass: ['bass', 'b'], lead: ['lead'], baritone: ['baritone', 'bari', 'bar'] };
    const first = name.split(/\s+/)[0];
    return Object.keys(LABELS).find((k) => LABELS[k].includes(first)) ?? null;
}

/** The problem with a set of picks, or null: 2 to 4 lines (1 is a solo, fine), each its own voice, SATB or barbershop. */
export function picksProblem(picks) {
    const chosen = picks.filter((p) => p.as);
    if (!chosen.length) return 'none';
    if (chosen.length > MAX_LINES) return 'tooMany';
    if (new Set(chosen.map((p) => p.as)).size !== chosen.length) return 'twice';
    if (chosen.some((p) => SATB.has(p.as)) && chosen.some((p) => BARBERSHOP.has(p.as))) return 'mixed';
    return null;
}

const ORDER = ['soprano', 'lead', 'alto', 'tenor', 'baritone', 'bass'];
// The parts' English names are the file's (as creator.js keeps them); the app words them from the key.
const PART_NAMES = { soprano: 'Soprano', alto: 'Alto', tenor: 'Tenor', bass: 'Bass', lead: 'Lead', baritone: 'Baritone' }; // i18n-ignore

/**
 * The song from the singer's picks: `picks` [{ id, as, take: 'top' | 'bottom' }] (a line with no
 * `as` is left out), the words from `wordsFrom` (a line's id) for lines without their own, a part
 * with no words singing "oo". The tune is the part the singer set as soprano or lead, else the
 * first of their picks in score order (alto, tenor, then bass), unless `melody` names the line the director chose as the
 * tune. The score's sections (verses, rehearsal marks) are the song's, each phrase inside one. Private, credited to the
 * file's authors, its licence "by someone else: kept private".
 */
export function toTemplate(score, { picks, wordsFrom = null, melody: tuneLine = null, title = score.title, id = crypto.randomUUID() }) {
    const chosen = picks.filter((p) => p.as).sort((a, b) => ORDER.indexOf(a.as) - ORDER.indexOf(b.as));
    const words = wordsFrom ? score.lines.find((l) => l.id === wordsFrom) : null;
    const wordAt = new Map((words?.notes ?? []).filter((n) => n.lyric).map((n) => [round(n.beat), n.lyric]));
    const parts = chosen.map((pick) => {
        const line = score.lines.find((l) => l.id === pick.id);
        const own = line.words > 0;
        let end = 0;
        const notes = [];
        for (const n of line.notes) {
            if (n.beat < end - EPSILON) continue; // overlapping notes in one voice: the first is kept
            const note = { beat: n.beat, length: n.length, pitch: pick.take === 'bottom' ? n.pitches[0] : n.pitches.at(-1) };
            const lyric = own ? n.lyric : wordAt.get(round(n.beat));
            if (lyric) note.lyric = lyric;
            if (n.br) note.br = true;
            notes.push(note);
            end = n.beat + n.length;
        }
        const pitches = notes.map((n) => n.pitch);
        return { key: pick.as, name: PART_NAMES[pick.as], range: { low: Math.min(...pitches), high: Math.max(...pitches) }, guide: { type: 'notes', notes } };
    });
    // Whole bars before anyone sings (an instrument's intro, a MIDI file's lead-in) are dropped, and
    // a MIDI file's upbeat becomes the pickup.
    const perBar = { '4/4': 4, '3/4': 3, '6/8': 2, '2/4': 2 }[score.timeSignature];
    const first = Math.min(...parts.flatMap((p) => p.guide.notes.map((n) => n.beat)));
    let pickupBeats = score.pickupBeats;
    let shift = 0;
    if (first >= pickupBeats + perBar - EPSILON) {
        shift = pickupBeats + Math.floor((first - pickupBeats) / perBar + EPSILON) * perBar;
        pickupBeats = 0;
    }
    if (!pickupBeats && first - shift > EPSILON) {
        pickupBeats = round(perBar - (first - shift));
        shift = first;
    }
    if (shift) for (const p of parts) p.guide.notes = p.guide.notes.map((n) => ({ ...n, beat: round(n.beat - shift) }));
    const picked = chosen.findIndex((p) => p.id === tuneLine);
    const melody = (picked >= 0 ? parts[picked] : null) ?? parts.find((p) => p.key === 'soprano' || p.key === 'lead') ?? parts[0];
    if (melody) melody.melody = true;
    const ends = parts.flatMap((p) => p.guide.notes.map((n) => n.beat + n.length));
    const total = round(Math.max(0, ...ends));
    // The score's sections from where they start (one that starts before the notes do takes the start), to the next. A
    // section marked on a downbeat starts with the tune's phrase leading into it (its upbeat), less than a bar before.
    const phraseStarts = (melody?.guide.notes ?? []).filter((n, i) => i === 0 || n.br).map((n) => n.beat);
    const starts = [];
    for (const sec of score.sections ?? []) {
        let beat = Math.max(0, round(sec.beat - shift));
        const lead = phraseStarts.filter((b) => b <= beat + EPSILON).at(-1);
        if (lead != null && beat - lead > EPSILON && beat - lead < perBar - EPSILON && (!starts.length || lead > starts.at(-1).beat + EPSILON || !starts.at(-1).name)) beat = lead;
        if (beat >= total - EPSILON) continue;
        if (starts.length && beat <= starts.at(-1).beat + EPSILON) starts.at(-1).name = sec.name;
        else starts.push({ name: sec.name, beat });
    }
    if (!starts.length || starts[0].beat > EPSILON) starts.unshift({ name: '', beat: 0 });
    const sections = total ? starts.map((sec, i) => ({ name: sec.name, startBeat: sec.beat, endBeat: starts[i + 1]?.beat ?? total, phrases: [] })) : [];
    // A phrase a line of the tune (each note marked br starts one, as does a new section), inside its section.
    let at = 0;
    let open = null;
    for (const n of melody?.guide.notes ?? []) {
        while (sections[at + 1] && n.beat >= sections[at + 1].startBeat - EPSILON) {
            at++;
            open = null;
        }
        const section = sections[at];
        if (!section) break;
        const end = Math.min(round(n.beat + n.length), section.endBeat);
        if (n.br || !open) section.phrases.push((open = [n.beat, end]));
        else open[1] = end;
    }
    const chords = score.chords.map((c) => ({ ...c, beat: round(c.beat - shift) })).filter((c, i, all) => c.beat >= 0 && c.beat < total && (i === 0 || c.beat > all[i - 1].beat));
    const KEPT = 'by someone else: kept private';
    return {
        id, version: 1, reviewed: false, title: (title || '').slice(0, 120), description: '', difficulty: 'intermediate', genre: [],
        tempo: score.tempo, key: score.key, timeSignature: score.timeSignature, pickupBeats,
        credits: { words: score.credits.words, tune: score.credits.tune, arrangement: score.credits.arrangement },
        licence: { words: KEPT, tune: KEPT, arrangement: KEPT, recordings: 'synthesized guides', checked: null },
        sections,
        ...(chords.length ? { chords } : {}),
        parts,
    };
}

/**
 * A MIDI file read into lines, as readMusicXML's: a line is one track's notes on one channel, named
 * by the track ("Track 2" when it has no name). Notes are snapped to a twelfth of a quarter (a
 * played-in file's timing), and two notes starting together are one note with two pitches. Words
 * are the lyric events. MIDI has no pickup or bar lines of its own; empty bars before the singing
 * are dropped when the song is made.
 */
export function readMidi(bytes) {
    const str = (from, n) => String.fromCharCode(...bytes.slice(from, from + n));
    if (bytes.length < 14 || str(0, 4) !== 'MThd') return { unreadable: true };
    const tracks = (bytes[10] << 8) | bytes[11];
    const division = (bytes[12] << 8) | bytes[13];
    if (division & 0x8000) return { unsupported: 'smpte' };
    let at = 8 + ((bytes[4] << 24) | (bytes[5] << 16) | (bytes[6] << 8) | bytes[7]);
    let tempo = null;
    let laterTempos = false;
    let time = null;
    let laterTimes = false;
    let key = null;
    let title = '';
    const lines = [];
    const decoder = new TextDecoder();
    for (let t = 0; t < tracks && at + 8 <= bytes.length; t++) {
        if (str(at, 4) !== 'MTrk') return { unreadable: true };
        const end = Math.min(bytes.length, at + 8 + ((bytes[at + 4] << 24) | (bytes[at + 5] << 16) | (bytes[at + 6] << 8) | bytes[at + 7]));
        let p = at + 8;
        let tick = 0;
        let status = 0;
        let name = '';
        const on = new Map(); // channel:pitch -> start tick
        const byChannel = new Map(); // channel -> notes
        const lyrics = [];
        const vlq = () => {
            let v = 0;
            let b;
            do { b = bytes[p++]; v = (v << 7) | (b & 0x7f); } while (b & 0x80 && p < end);
            return v;
        };
        while (p < end) {
            tick += vlq();
            let s = bytes[p];
            if (s & 0x80) p++;
            else s = status; // running status
            if (s === 0xff) {
                const type = bytes[p++];
                const n = vlq();
                const data = bytes.slice(p, p + n);
                p += n;
                if (type === 0x03 && !name) name = decoder.decode(data).trim();
                else if (type === 0x05) lyrics.push({ tick, text: decoder.decode(data) });
                else if (type === 0x51) {
                    const bpm = 60000000 / ((data[0] << 16) | (data[1] << 8) | data[2]);
                    if (tempo == null) tempo = bpm;
                    else if (Math.abs(bpm - tempo) > 0.5) laterTempos = true;
                } else if (type === 0x58) {
                    const sig = `${data[0]}/${2 ** data[1]}`;
                    if (!time) time = sig;
                    else if (sig !== time) laterTimes = true;
                } else if (type === 0x59 && key == null) key = { fifths: (data[0] << 24) >> 24, minor: data[1] === 1 };
                continue;
            }
            if (s === 0xf0 || s === 0xf7) {
                p += vlq();
                continue;
            }
            status = s;
            const kind = s & 0xf0;
            const channel = s & 0x0f;
            if (kind === 0x90 || kind === 0x80) {
                const pitch = bytes[p];
                const velocity = bytes[p + 1];
                p += 2;
                const id = `${channel}:${pitch}`;
                if (kind === 0x90 && velocity > 0) {
                    if (!on.has(id)) on.set(id, tick);
                } else if (on.has(id)) {
                    const start = on.get(id);
                    on.delete(id);
                    if (channel === 9) continue; // drums
                    if (!byChannel.has(channel)) byChannel.set(channel, []);
                    byChannel.get(channel).push({ start, stop: tick, pitch });
                }
            } else p += kind === 0xc0 || kind === 0xd0 ? 1 : 2;
        }
        if (t === 0 && !byChannel.size) title = name;
        const snap = (ticks) => Math.round((ticks / division) * 12) / 12; // quarters, to a twelfth
        const channels = [...byChannel.keys()].sort((a, b) => a - b);
        for (const channel of channels) {
            const raw = byChannel.get(channel).sort((a, b) => a.start - b.start || a.pitch - b.pitch);
            const notes = [];
            for (const r of raw) {
                const quarters = snap(r.start);
                const length = Math.max(1 / 12, snap(r.stop) - quarters);
                const last = notes.at(-1);
                if (last && Math.abs(last.quarters - quarters) < EPSILON) {
                    last.pitches.push(r.pitch);
                    last.length = Math.max(last.length, length);
                } else notes.push({ quarters, length, pitches: [r.pitch] });
            }
            // The words go on the notes they start with (the track's first channel only).
            if (channel === channels[0]) {
                for (const l of lyrics) {
                    const note = notes.find((n) => Math.abs(n.quarters - snap(l.tick)) < EPSILON);
                    const word = l.text.replace(/[\\/\r\n]/g, ' ').trim();
                    if (note && word) note.lyric = word;
                    if (note && /^[\\/]/.test(l.text)) note.br = true;
                }
            }
            notes.forEach((n, i) => {
                if (i === 0 || n.quarters - (notes[i - 1].quarters + notes[i - 1].length) >= 1 - EPSILON) n.br = true;
            });
            const pitches = notes.flatMap((n) => n.pitches);
            lines.push({ id: `${t}:${channel}`, part: name, track: t + 1, staff: 1, voice: channel + 1, voicesInPart: channels.length, notes, range: { low: Math.min(...pitches), high: Math.max(...pitches) }, chordNotes: notes.filter((n) => n.pitches.length > 1).length, words: notes.filter((n) => n.lyric).length });
        }
        at = end;
    }
    if (!lines.length) return { unsupported: 'empty' };
    const [beats, beatType] = (time ?? '4/4').split('/').map(Number);
    const metre = metreFor(beats, beatType);
    if (!metre) return { unsupported: 'metre', metre: time };
    const ts = metre.timeSignature;
    const ours = (q) => round(toBeats(q, ts));
    for (const line of lines) line.notes = line.notes.map(({ quarters, length, ...rest }) => ({ beat: ours(quarters), length: ours(quarters + length) - ours(quarters), ...rest }));
    const notices = [];
    if (laterTimes) notices.push({ kind: 'metreChanges' });
    if (laterTempos) notices.push({ kind: 'tempoChanges' });
    if ((time ?? '4/4') !== ts) notices.push({ kind: 'metreWritten', from: time, to: ts });
    return {
        title,
        credits: { tune: '', words: '', arrangement: '' },
        key: key ? keyFromFifths(key.fifths, key.minor) : 'C',
        timeSignature: ts,
        tempo: Math.min(300, Math.max(30, Math.round((tempo ?? 90) / (ts === '6/8' ? 1.5 : 1)))),
        pickupBeats: 0,
        chords: [],
        lines,
        notices,
        midi: true,
    };
}

/**
 * A compressed MusicXML file (.mxl, a zip): the score inside, as text. The zip is read here and
 * unpacked by the browser's own DecompressionStream, so there's no library. Null when it isn't one.
 */
export async function unzipScore(bytes) {
    const entries = unzip(bytes);
    if (!entries) return null;
    const text = async (entry) => {
        const data = await entry.read();
        return data ? new TextDecoder().decode(data) : null;
    };
    const container = entries.find((e) => e.name === 'META-INF/container.xml');
    let path = null;
    if (container) {
        const xml = await text(container);
        path = xml && new DOMParser().parseFromString(xml, 'application/xml').querySelector('rootfile')?.getAttribute('full-path');
    }
    const entry = entries.find((e) => e.name === path) ?? entries.find((e) => !e.name.startsWith('META-INF/') && /\.(musicxml|xml)$/i.test(e.name));
    return entry ? text(entry) : null;
}

/** Any of the files we take, read: `.musicxml`, `.xml`, `.mxl`, `.mid` or `.midi` (by their first bytes, not their names). */
export async function readSheetFile(file) {
    const bytes = new Uint8Array(await file.arrayBuffer());
    const head = String.fromCharCode(...bytes.slice(0, 4));
    if (head === 'MThd') return readMidi(bytes);
    if (head === 'PK\u0003\u0004') {
        const xml = await unzipScore(bytes);
        return xml ? readMusicXML(xml) : { unreadable: true };
    }
    return readMusicXML(new TextDecoder().decode(bytes));
}
