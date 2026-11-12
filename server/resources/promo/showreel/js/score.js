// The music the film is cut to: Amazing Grace's verse as the app's real singers recorded it (the four
// default real-voice guides of the song's catalogue entry, docs/showreel-v2-plan.md), at the song's own
// 80 BPM. A real voice can't be stretched, so there are no fermatas: the verse is sung twice, the first
// time voice by voice and the second with all four, and the last chord is held two more bars under the
// app's soft pad, so the end card stays up. 102 beats last exactly the film's 76.5 seconds, and the
// next loop's pickup lands on the film's first frame. Every cut sits on a beat (0.75 s) or a half beat.

import { E, clamp, range } from './lib.js';

// v5's three cuts (docs/showreel-v5-plan.md), picked with ?cut=: the hero (the verse twice, 76.5 s), the short
// (verse one and the held chord, 40.5 s, for Reddit and social) and the social (vertical, phrases three and
// four with a voice joining each half phrase, 19.75 s). The film runs on the song's clock: FROM is the
// song's beat at the film's first frame, END the beat it loops on.
// The carols cut (the season's posts, vertical or square) is another song on the same page: Silent Night's
// first two lines at its own 66 BPM, a part joining every two bars, in the app's own guide voice (the
// catalogue has no real voices for it), the last chord held two beats under the end card. It does not loop.
const CUTQ = new URLSearchParams(location.search).get('cut');
export const CUT = ['hero', 'short', 'social', 'carols'].includes(CUTQ) ? CUTQ : 'hero';
export const CAROLS = CUT === 'carols';
document.documentElement.dataset.cut = CUT;
export const BPM = CAROLS ? 66 : 80;
export const SPB = 60 / BPM;
export const VERSE = CAROLS ? 24 : 48; // the template's verse, its one-beat pickup included (the carols: its first two lines)
export const PASS2 = VERSE; // where the second time through starts
export const PASSES = CUT === 'hero' ? 2 : 1;
export const FROM = CUT === 'social' ? 24 : 0;
export const HOLD = CAROLS ? 2 : CUT === 'social' ? 7 / 3 : 6; // the last chord held on: two bars, or the social's 1.75 s
export const END = PASSES * VERSE + HOLD;
export const BEATS = END - FROM; // the film's length in beats
export const DUR = BEATS * SPB;
export const DURS = { hero: 76.5, short: 40.5, social: 19.75, carols: (26 * 60) / 66 };
export const sec = (beat) => beat * SPB;
export const beatAt = (t) => t / SPB;
export const PARTS = ['soprano', 'alto', 'tenor', 'bass'];

// song: the template; parts: each part's notes through both passes (each with its `pass`); voices:
// each part's real voice as the catalogue lists it (voices/voices.json, written by render.py --voices).
export const S = { song: null, parts: null, chords: null, phrases: null, voice: null, voices: null };

export async function load() {
    const song = await (await fetch(CAROLS ? '/server/database/templates/silent-night.json' : '/web/templates/amazing-grace.json')).json();
    S.song = song;
    // the carols cut sings only the song's first two lines
    const head = (list) => (CAROLS ? list.filter((x) => x.beat < VERSE - 1e-6).map((x) => ({ ...x })) : list);
    const twice = (list) => [0, 1].slice(0, PASSES).flatMap((pass) => list.map((x) => ({ ...x, beat: x.beat + pass * PASS2, pass })));
    S.parts = PARTS.map((key) => twice(head(song.parts.find((p) => p.key === key).guide.notes)));
    for (const notes of S.parts) { const last = notes[notes.length - 1]; last.length = CAROLS ? END - last.beat : last.length + HOLD; }
    S.chords = twice(head(song.chords));
    S.phrases = CAROLS ? [[0, 6], [6, 12], [12, 18], [18, 24]] : twice(song.sections[0].phrases.map(([a, b]) => ({ beat: a, end: b }))).map((p) => [p.beat, p.end + p.pass * PASS2]);
    S.voice = voiceCurve(S.parts[0]);
    S.voices = CAROLS ? null : (await fetch('voices/voices.json').then((r) => (r.ok ? r.json() : null)).catch(() => null))?.parts ?? null;
    // When each of your notes is first in tune, and how much of it you held there (the sheen).
    const step = 0.01;
    for (const n of S.parts[0]) {
        n.hit = null;
        n.tune = [];
        for (let b = n.beat; b < n.beat + n.length; b += step) {
            const on = Math.abs(S.voice(b) - n.pitch) < 0.4;
            n.tune.push(on);
            if (on && n.hit === null) n.hit = b;
        }
    }
}

/** The credit line, as the app gives it a part at a time: "Soprano and alto sung by <name>". */
export function creditLine() {
    if (!S.voices) return '';
    const by = new Map();
    for (const key of PARTS) {
        const name = S.voices[key]?.credit;
        if (name) by.set(name, [...(by.get(name) ?? []), key]);
    }
    const list = (xs) => (xs.length < 2 ? xs[0] : `${xs.slice(0, -1).join(', ')} and ${xs.at(-1)}`);
    return [...by].map(([name, keys]) => { const p = list(keys); return `${p[0].toUpperCase()}${p.slice(1)} sung by ${name}`; }).join('. ');
}

/** How much of note `n` you have held in tune by `beat` (0 to 1): the sheen's fill. */
export function sung(n, beat) {
    const k = Math.floor(clamp((beat - n.beat) / 0.01, 0, n.tune.length));
    let on = 0;
    for (let i = 0; i < k; i++) if (n.tune[i]) on++;
    return on / n.tune.length;
}

/** The note sounding in `notes` at `beat`, or null. */
export const noteAt = (notes, beat) => notes.find((n) => beat >= n.beat && beat < n.beat + n.length) ?? null;

/**
 * Your voice, as a pitch (MIDI, fractional) at a beat: it glides into each note, settles, grows a
 * little vibrato on a long note, and breathes at each phrase's end. On "grace" it starts a little
 * low, the lane says "Slide up", and it finds the note.
 */
function voiceCurve(notes) {
    return (b) => {
        let i = notes.findLastIndex((n) => n.beat <= b);
        if (i < 0) i = 0;
        const n = notes[i], prev = notes[i - 1];
        const d = b - n.beat;
        let p = n.pitch;
        const from = prev && prev.beat + prev.length >= n.beat - 0.01 ? prev.pitch : n.pitch - 0.6;
        p = from + (n.pitch - from) * E.out(range(d, 0, 0.16));
        // a touch of overshoot as it lands, then it settles
        p += (n.pitch - from) * 0.06 * Math.sin(Math.PI * range(d, 0.12, 0.4));
        if (n.lyric === 'grace,') p -= 0.95 * (1 - E.io(range(d, 0.5, 0.85)));
        const secs = d * SPB;
        p += 0.2 * clamp((secs - 0.35) / 0.3) * Math.sin(2 * Math.PI * 5.3 * secs);
        p += 0.05 * Math.sin(b * 7.3) + 0.035 * Math.sin(b * 13.1 + 1);
        return p;
    };
}

/** Whether you are singing at `beat`: a breath at each phrase's end (`br` marks a phrase start). */
export function voicing(beat) {
    const notes = S.parts[0];
    const i = notes.findLastIndex((n) => n.beat <= beat);
    if (i < 0) return false;
    const n = notes[i], next = notes[i + 1];
    const end = n.beat + n.length - (next?.br ? 0.22 : 0);
    return beat < end;
}

/** The chord sounding at `beat` (the template's chords, each until the next). */
export const chordAt = (beat) => S.chords.findLast((c) => c.beat <= beat + 1e-6) ?? S.chords[0];
const NAMES = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];
export const chordName = (c) => NAMES[c.root] + (c.quality === 'min' ? 'm' : c.quality === 'maj6' ? '6' : '') + (c.bass != null ? '/' + NAMES[c.bass] : '');

/**
 * The beats the picture and the sound share, so a voice can never join off its lane. The film reads
 * them for its moves, the soundtrack for its entries and chimes. The first time through, a voice joins
 * at each phrase; the second, all four sing from the pickup.
 */
export const CUE = {
    // when each voice joins: its lane ignites, its voice fades in at its first note from here (the social
    // cut joins a voice every half phrase, from phrase three)
    enter: CAROLS ? [0, 6, 12, 18] : CUT === 'social' ? [24, 30, 36, 42] : [0, 12, 24, 36],
    // v5's share card, in verse two: you, then Maya, Sam and Leo take the other parts, a beat each
    share: 72,
    friends: [74, 75, 76],
    copied: 73,
    all: CAROLS ? 20 : (PASSES - 1) * VERSE + 40, // "now I", the last time: everyone
    end: CAROLS ? 21 : (PASSES - 1) * VERSE + 43, // "see." (the carols: "bright"): the mark and the end card
    fold: CAROLS ? [END + 50, END + 51] : [END - 3, END - 0.8], // everything folds back into the one voice (never, in the carols cut)
};

/**
 * When part `i` is let in, in song seconds: its voice fades in over JOIN_RAMP from just before its first
 * note at its entry (the soundtrack's gate; v5's lane ignites on what is then heard, loud.js onsetAt).
 */
export const JOIN_LEAD = 0.16, JOIN_RAMP = 0.12;
const firstFrom = (i, beat) => S.parts[i].find((n) => n.beat >= beat - 1e-6 && n.pass === 0);
export const joinAt = (i) => sec(firstFrom(i, CUE.enter[i]).beat) - JOIN_LEAD;

/**
 * The captions track (WebVTT): a cue for each phrase, with the line the singers sing and who joins,
 * The bass sings on "oo", the others the words.
 */
const HEARD = [
    'A real singer sings the tune',
    'The alto joins',
    'The tenor joins',
    'The bass joins, on "oo": four voices',
    'All four voices',
    'All four voices',
    'All four voices',
    'All four voices, the last chord held',
];
// the same, for Spanish speakers (docs/languages/es-glossary.md: contralto, bajo, melodía; the hymn is sung in
// English, so its words stay as sung)
const HEARD_ES = [
    'Una voz real canta la melodía',
    'Entra la contralto',
    'Entra el tenor',
    'Entra el bajo, con "oo": cuatro voces',
    'Las cuatro voces',
    'Las cuatro voces',
    'Las cuatro voces',
    'Las cuatro voces, con el último acorde sostenido',
];
const HEARD_CAROLS = ['The soprano has the tune', 'The alto joins', 'The tenor joins', 'The bass joins, on "oo": four parts'];
export function vtt(lang = 'en') {
    const HEARD_IN = lang === 'es' ? HEARD_ES : HEARD;
    const stamp = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${(s % 60).toFixed(3).padStart(6, '0')}`;
    const out = ['WEBVTT', ''];
    // the social cut is for platforms that caption it themselves: its lines are in the picture
    if (CUT === 'social') return out.join('\n');
    S.phrases.forEach(([b0, b1], k) => {
        let words = '';
        for (const n of S.parts[0]) {
            if (!n.lyric || n.beat < b0 || n.beat >= b1) continue;
            words += n.lyric.endsWith('-') ? n.lyric.slice(0, -1) : `${n.lyric} `;
        }
        // the last phrase runs on through the held chord (the singer's credit is on the end card, not here)
        const last = k === S.phrases.length - 1, end = last ? DUR : sec(b1);
        const heard = CAROLS ? HEARD_CAROLS[k] : last && PASSES === 1 ? `${HEARD_IN[k]}${lang === 'es' ? ', con el último acorde sostenido' : ', the last chord held'}` : HEARD_IN[k];
        if (CAROLS) words = words.trim().replace(/,$/, '').replace(/^./, (c) => c.toUpperCase());
        out.push(String(k + 1), `${stamp(sec(b0))} --> ${stamp(end - 0.2)}`, `♪ ${words.trim()} ♪`, `[${heard}]`, '');
    });
    return out.join('\n');
}
