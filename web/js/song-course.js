// Song courses (docs/song-courses-plan.md): a course that teaches one song, a unit a part. Their
// exercises never hold notes: `exercise.song` ({ template, part, phrases, with? }) names the song's
// part and phrases, and the notes come with the song (loadTemplate), so a Plus song's notes reach only
// someone who may start it. songExercise turns one into the course's own exercise shape (steps from
// the song's tonic, which Exercise already plays). Pure, unit-tested in tests/song-course.test.js.

import { octaveFit, suggestTranspose } from './dsp.js';
import { parseKey } from './notes.js';
import { partsFor } from './templates.js';
import { beatsPerBar } from './theme.js';

const EPSILON = 1e-6;
const TONIC_OCTAVE = 60; // the song's tonic is laid from middle C's octave; the transpose moves it

/** A song course's id is "song-<template id>"; its lessons' ids start with it. */
export const SONG_PREFIX = 'song-';
export const isSongLesson = (id) => typeof id === 'string' && id.startsWith(SONG_PREFIX);

/** The step's song spec, from its exercise (play, try) or a learn card's example (listen); else null. */
export function songOf(step) {
    return step?.exercise?.song ?? step?.listen?.song ?? null;
}

/** Every phrase in order, all sections', as [start, end] beats: the course names them by place, from 0. */
function phrasesOf(template) {
    return template.sections.flatMap((s) => s.phrases);
}

/** A part's notes in those phrases, from the first phrase's start (a gap between phrases kept). */
function notesIn(part, spans, from, tonic) {
    return part.guide.notes
        .filter((n) => Number.isFinite(n.pitch) && spans.some(([a, b]) => n.beat >= a - EPSILON && n.beat < b - EPSILON))
        .map((n) => {
            const note = { beat: n.beat - from, length: n.length, step: n.pitch - tonic };
            if (n.lyric) note.lyric = n.lyric;
            return note;
        });
}

// With only the comfortable note known, a beginner's range is taken as a fourth below it to a sixth above
// (the span the first week's exercises use; the arranger's check, 2026-11-02).
const HOME_BELOW = 5;
const HOME_ABOVE = 9;

/**
 * How a part fits this singer (docs/song-keys-plan.md): `{ transpose, octave, fits }`, the semitones the
 * whole song moves, the octave the singer takes the part in (as Sing's fitKey says it), and whether it
 * fits. From the range (a fifth or more: knownRange), or with only the comfortable note (`home`) the range
 * a beginner has around it (a fourth below to a sixth above), by one rule, Sing's: the octave the part
 * fits, else the closest key. Null with neither.
 */
export function songFit(template, partKey, range, home = null) {
    const part = template.parts.find((p) => p.key === partKey);
    const voice = range ?? (home === null || home === undefined ? null : { low: home - HOME_BELOW, high: home + HOME_ABOVE });
    if (!part?.range || !voice) return null;
    const fit = octaveFit(voice, part.range);
    if (fit) return { transpose: fit.transpose, octave: fit.octave, fits: true };
    const near = suggestTranspose(voice, part.range);
    return { transpose: near.transpose, octave: 0, fits: near.fits };
}

/** The semitones the whole song moves for this singer (songFit's), and 0 with nothing known yet. */
export function songTranspose(template, partKey, range, home = null) {
    return songFit(template, partKey, range, home)?.transpose ?? 0;
}

/**
 * An exercise from a song: `{ exercise, tonic, minor }`, where the exercise is the step's own fields
 * (turn, guide, giveNote, a slower `tempo` for reading …) with the notes filled in, at the song's tempo
 * unless the step gives its own, as steps from `tonic` (the song's tonic as MIDI,
 * before any transpose). Sung with the guide (a `turn: false` step not marked `guide: false`), your own
 * line sounds as a partner too (never in a learn card's example, `listen`, where the guide already
 * sings it); `with: 'others'` adds the song's other parts; without them, its chords play under you.
 */
export function songExercise(template, raw, { listen = false } = {}) {
    const { song, ...rest } = raw;
    const part = template.parts.find((p) => p.key === song.part);
    if (!part) throw new Error(`${template.id} has no part ${song.part}`);
    const all = phrasesOf(template);
    const spans = song.phrases.map((i) => all[i]).filter(Boolean);
    if (!spans.length) throw new Error(`${template.id} has no phrases ${song.phrases.join(', ')}`);
    const from = Math.min(...spans.map(([a]) => a));
    const to = Math.max(...spans.map(([, b]) => b));
    const key = parseKey(template.key);
    const tonic = TONIC_OCTAVE + key.tonic;
    const exercise = { ...rest, tempo: rest.tempo ?? template.tempo, notes: notesIn(part, spans, from, tonic) };
    const partners = [];
    if (!listen && rest.turn === false && rest.guide !== false) partners.push({ delay: 0 }); // the guide sings your line with you
    if (song.with === 'others') {
        for (const other of partsFor(template).filter((p) => p.key !== part.key && p.guide?.type === 'notes')) {
            const notes = notesIn(other, spans, from, tonic);
            if (notes.length) partners.push({ notes });
        }
    } else if (template.chords?.length) {
        // Each chord lasts until the next, so the one sounding as the first phrase starts comes in at 0.
        const sounding = template.chords.filter((c) => c.beat <= from + EPSILON).at(-1);
        exercise.chords = template.chords
            .filter((c) => c.beat > from + EPSILON && c.beat < to - EPSILON)
            .map((c) => ({ ...c, beat: c.beat - from }));
        if (sounding) exercise.chords.unshift({ ...sounding, beat: 0 });
        exercise.chords = exercise.chords.map((c) => ({ beat: c.beat, root: (c.root - key.tonic + 12) % 12, quality: c.quality, bass: c.bass == null ? null : (c.bass - key.tonic + 12) % 12 }));
    }
    if (partners.length) exercise.with = partners;
    // Read from a stave (sight-singing's unit 4): the song's own time, and where its bar lines fall from
    // the first phrase's start (a pickup's beats before the first one), so the stave's bars are the song's.
    if (rest.staff) {
        const perBar = beatsPerBar(template.timeSignature);
        const pickup = ((((template.pickupBeats ?? 0) - from) % perBar) + perBar) % perBar;
        exercise.staff = { time: template.timeSignature, ...rest.staff, pickup: Math.round(pickup * 1000) / 1000 };
    }
    return { exercise, tonic, minor: key.minor };
}
