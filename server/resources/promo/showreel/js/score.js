// The music the film is cut to: Amazing Grace's verse from the app's own template
// (web/templates/amazing-grace.json, read as it is), at 96 BPM. Four of its long notes are held
// longer, as fermatas, so the words on screen have time to be read: "me.", "found," and "now" a bar
// each, and the last chord ("see.") two bars, so the end card stays up. Its 63 beats last exactly
// the film's 39.375 seconds, and the next loop's pickup lands on the film's first frame. Every cut
// sits on its beat (0.625 s) or half beat.

import { E, clamp, range } from './lib.js';

export const BPM = 96;
export const SPB = 60 / BPM;
// The fermatas: [a beat inside the held note, in the template's own beats, how many beats more].
// Everything after one moves later; a note sounding across it is held longer.
export const STRETCH = [[23.5, 3], [35.5, 3], [41, 3]];
/** A beat of the template, as a beat of the film. */
export const m = (b) => b + STRETCH.reduce((sum, [at, n]) => sum + (b > at ? n : 0), 0);
export const VERSE = m(48);
export const HOLD = 6; // the last fermata: "see." held two more bars
export const BEATS = VERSE + HOLD;
export const DUR = BEATS * SPB;
export const sec = (beat) => beat * SPB;
export const beatAt = (t) => t / SPB;
export const PARTS = ['soprano', 'alto', 'tenor', 'bass'];

export const S = { song: null, parts: null, chords: null, phrases: null, voice: null };

export async function load() {
    const song = await (await fetch('/web/templates/amazing-grace.json')).json();
    S.song = song;
    const held = (n) => ({ ...n, beat: m(n.beat), length: m(n.beat + n.length) - m(n.beat) });
    S.parts = PARTS.map((key) => song.parts.find((p) => p.key === key).guide.notes.map(held));
    for (const notes of S.parts) notes[notes.length - 1].length += HOLD;
    S.chords = song.chords.map((c) => ({ ...c, beat: m(c.beat) }));
    S.phrases = song.sections[0].phrases.map(([a, b]) => [m(a), m(b)]);
    S.voice = voiceCurve(S.parts[0]);
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
 * The beats the picture and the sound share, so a chime can never land off its gem. The film reads
 * them for its moves, the soundtrack for its entries and chimes.
 */
// In the film's beats; m() turns a beat of the template into one.
export const CUE = {
    enter: [0, m(13), m(15), m(16)],         // when each voice joins (the alto, tenor and bass lanes slide in)
    mark: m(19),                             // "me.": the four held notes become the mark
    forward: [21, 22, 23, 24, 25],           // the mixer brings a voice forward on each beat of the held "me."
    forwardPart: [0, 1, 2, 3, 1],            // the soprano, alto, tenor, bass, and back to the alto ("You")
    hum: [m(24), m(28)],                     // you hum alone: the creator
    bloom: m(28),                            // the harmony parts bloom under the tune
    blocks: [m(31), m(31.5), m(32), m(32.5)], // the chords land as glass blocks
    pad: m(31),                              // and the "oo" pad comes in under them
    write: m(32),                            // "Write a song.", once the blocks are down
    share: m(35.5),                          // the held "found,": the share card
    copied: m(35.5) + 1.5,                   // "Link copied."
    friends: [m(37), m(38), m(39)],          // friends take the alto, the tenor, the bass
    all: m(40),                              // "now", held: everyone, wide
    end: m(43),                              // "see.": the end card
    fold: [BEATS - 3, BEATS - 0.8],          // everything folds back into the one voice
};

/**
 * The captions track (WebVTT): a cue for each phrase, with the line of the hymn the tune carries and
 * what is heard. The guide voice sings every part on "oo", so a cue never claims the words are sung.
 */
const HEARD = [
    'One voice, singing on "oo"',
    'The alto, tenor and bass join, on "oo"',
    'A hummed tune, then the harmony and soft chords',
    'Friends double the four parts; it folds back to one voice',
];
export function vtt() {
    const stamp = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${(s % 60).toFixed(3).padStart(6, '0')}`;
    const out = ['WEBVTT', ''];
    S.phrases.forEach(([b0, b1], k) => {
        let words = '';
        for (const n of S.parts[0]) {
            if (!n.lyric || n.beat < b0 || n.beat >= b1) continue;
            words += n.lyric.endsWith('-') ? n.lyric.slice(0, -1) : `${n.lyric} `;
        }
        // the last phrase runs on through the held chord
        const end = k === S.phrases.length - 1 ? DUR : sec(b1);
        out.push(String(k + 1), `${stamp(sec(b0))} --> ${stamp(end - 0.2)}`, `♪ ${words.trim()} ♪`, `[${HEARD[k]}]`, '');
    });
    return out.join('\n');
}
