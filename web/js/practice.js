// What plays in a project made from a harmony template. Each part (a "slot") has up to two voices
// in the engine: its guide (`<id>:guide`, synthesized from the template's notes) and its take
// (`<id>`, the singer's recording). Plain projects have takes only. Pure functions: unit-tested in
// tests/practice.test.js.

import { tk } from './i18n.js';

/** The practice modes, as translation keys. */
export const MODES = {
    listen: tk('practice.mode.listen'),
    singAlong: tk('practice.mode.singAlong'),
    own: tk('practice.mode.own'),
};

export const PRACTICE_DEFAULTS = {
    focus: null, // the slot being practised
    mode: 'listen',
    withOthers: false, // Listen: hear the other parts too
    guideLevel: 0.35, // Sing along: how loud your guide is
    compare: false, // hear your take where your guide would be
    spread: false, // pan the guides apart, to tell them apart
    split: false, // Split ears: your part left, the others right (headphones only)
    ladder: false, // Build up to speed: 70%, then up 5% each clean round on your own
    phrase: null, // index of the selected phrase
    loop: false, // loop the selected phrase
    speed: 1,
    voices: {}, // part key -> 'synth' where the singer chose the practice voice over a real one
    voicesSeen: [], // real voices already announced (their URLs), so each is announced once
    chords: false, // the chords underneath (a template with chords)
    chordsLevel: 0.5,
    chordsSound: 'pad', // or 'piano'
};

/** Sing along's guide, as What you hear offers it (Medium is the default). */
export const GUIDE_LEVELS = { soft: 0.2, medium: 0.35, loud: 0.6 };

/** Recording without headphones: the others at this level, so less of them reaches the mic. */
export const SPEAKERS_UNDER = 0.5;

/** How loud the melody plays under a harmony part you listen to alone. */
export const MELODY_UNDER = 0.6;

/** The chords' voice in the engine. */
export const BACKING = 'backing';

export function guideId(slotId) {
    return `${slotId}:guide`;
}

export function hasTake(slot) {
    return slot.frames > 0;
}

/**
 * Melody first (Hillel, 2026-10-29): a singer is still new, and is offered a song's melody, until
 * they have a good take (2 stars: 70% on pitch) or have started another song. `parts` and
 * `projects` are the device's own; only songs count (not the course's lessons), and only takes this
 * person sang (in a friend's project, the parts marked theirs).
 */
export function isNewSinger(parts, projects, { projectId = null, templateId = null, me = null } = {}) {
    const songs = new Set(projects.filter((p) => p.template && !p.course).map((p) => p.id));
    // This song, however many times it was started, isn't "another" song.
    const thisSong = new Set(projects.filter((p) => p.id === projectId || (templateId && p.template?.id === templateId)).map((p) => p.id));
    const mine = parts.filter((p) => songs.has(p.projectId) && hasTake(p) && (!p.createdBy || p.createdBy.id === me));
    if (mine.some((p) => (p.score?.stars ?? 0) >= 2)) return false;
    return !mine.some((p) => !thisSong.has(p.projectId));
}

/**
 * The level of every voice: a Map of voice id to `{ audible, volume, pan }`.
 *
 * - No slot in focus: each slot plays its take, or its guide while it has no take (or `useGuide`).
 *   Mute and solo apply as usual.
 * - A slot in focus: Listen plays its guide (or, with `compare`, its take), with the others only
 *   if `withOthers`; Sing along plays its guide at `guideLevel` with the others; On your own
 *   plays only the others. The others play their take or guide as above (mute still applies).
 * - `recordingId`: that slot is silent, both voices, while its new take is recorded, and the others
 *   play whatever the mode (you record against them).
 * - `melody`: the song's melody part key. Listening to a harmony part, the melody plays under your
 *   guide even without the others (at `MELODY_UNDER`, so your part leads); its mute still applies.
 * - `hearRecorded`: the studio plays the part being recorded too (its guide, or else its take).
 * - `recordGuide`: your guide plays while you record (What you hear; off unless you turn it on).
 * - `othersLevel`: while recording, everything but your part plays at this (softer on speakers).
 */
export function voiceLevels(slots, practice = PRACTICE_DEFAULTS, { recordingId = null, melody = null, othersLevel = 1, recordGuide = false, hearRecorded = false } = {}) {
    const levels = new Map();
    const under = recordingId ? othersLevel : 1; // recording on speakers: everything else softer
    const anySolo = slots.some((s) => s.solo);
    // A row's Solo and Mute (the studio's) decide what plays, practice focus or not: Sing turns every
    // solo off for its visit, so there this is the mute alone.
    const on = (slot) => (anySolo ? slot.solo : !slot.muted);
    const guides = slots.filter((s) => s.templatePartKey);
    const spreadPan = (slot) => {
        if (!practice.spread || guides.length < 2) return slot.pan;
        const index = guides.indexOf(slot);
        return Math.max(-1, Math.min(1, slot.pan + (index / (guides.length - 1) - 0.5) * 0.8));
    };
    const usual = (slot, audible, volume = slot.volume * under) => {
        const take = hasTake(slot) && !slot.useGuide;
        levels.set(slot.id, { audible: audible && take, volume, pan: slot.pan });
        if (slot.templatePartKey) {
            levels.set(guideId(slot.id), { audible: audible && !take, volume, pan: spreadPan(slot) });
        }
    };
    const focus = practice.focus && slots.find((s) => s.id === practice.focus);
    for (const slot of slots) {
        if (slot.id === recordingId && hearRecorded) {
            // The studio (Hillel: "play all tracks"): the track being recorded plays too, its guide
            // when it's a song's part, else its current take; mute and solo still apply.
            usual({ ...slot, useGuide: !!slot.templatePartKey }, on(slot));
        } else if (slot.id === recordingId) {
            usual(slot, false);
            // Your guide while you record, when you turn it on (at Sing along's level).
            if (recordGuide && slot.templatePartKey) levels.set(guideId(slot.id), { audible: !slot.muted, volume: slot.volume * practice.guideLevel, pan: spreadPan(slot) });
        } else if (!focus) {
            usual(slot, on(slot));
        } else if (slot === focus) {
            const yours = practice.compare && hasTake(slot);
            const guideOn = practice.mode === 'listen' ? !yours : practice.mode === 'singAlong';
            const volume = practice.mode === 'singAlong' ? slot.volume * practice.guideLevel : slot.volume;
            levels.set(slot.id, { audible: practice.mode === 'listen' && yours && on(slot), volume: slot.volume, pan: slot.pan });
            if (slot.templatePartKey) {
                levels.set(guideId(slot.id), { audible: guideOn && on(slot), volume, pan: spreadPan(slot) });
            }
        } else if (!recordingId && practice.mode === 'listen' && !practice.withOthers) {
            // Listening alone: only the melody, under your harmony part.
            usual(slot, on(slot) && !!melody && slot.templatePartKey === melody, slot.volume * MELODY_UNDER);
        } else {
            // While a part is recorded, the others play (you sing against them), as their rows say.
            usual(slot, on(slot));
        }
    }
    // The chords underneath: on when chosen, at their own level, never recorded into a take.
    levels.set(BACKING, { audible: !!practice.chords, volume: (practice.chordsLevel ?? 0.5) * under, pan: 0 });
    // Split ears (headphones only; the caller decides): your part in your left ear, the others in your
    // right, the chords in the middle, as the practice track of the same name.
    const yours = focus ?? slots.find((s) => s.id === recordingId);
    if (practice.split && yours) {
        const mine = new Set([yours.id, guideId(yours.id)]);
        for (const [id, level] of levels) if (id !== BACKING) levels.set(id, { ...level, pan: mine.has(id) ? -1 : 1 });
    }
    return levels;
}

/** The slots in the template's suggested order, for "next part" prompts. */
export function nextSlot(slots, template, afterId = null) {
    const order = template?.order ?? [];
    const rank = (slot) => {
        const i = order.indexOf(slot.templatePartKey);
        return i < 0 ? order.length + slot.order : i;
    };
    const sorted = slots.filter((s) => s.templatePartKey).sort((a, b) => rank(a) - rank(b));
    return sorted.find((s) => s.id !== afterId && !hasTake(s)) ?? null;
}

// ---- Practice tracks (docs/more-features-plan.md, feature 12) -------------------------------------

/**
 * The levels for a practice track of `partId`: 'without' (everything but my part, to sing along to),
 * 'louder' (my part forward, the others back: the part-predominant track choirs use), 'split' (my
 * part in the left ear and the others in the right, the barbershop "part left" track: no spread
 * from Polished, so each stays on its side) or 'alone' (just my part, to learn the notes).
 */
export function trackLevels(levels, partId, kind) {
    const mine = new Set([partId, guideId(partId)]);
    const out = new Map();
    for (const [id, level] of levels) {
        if (kind === 'split') out.set(id, id === BACKING ? level : { ...level, pan: mine.has(id) ? -1 : 1, spread: 0 });
        else if (!mine.has(id)) out.set(id, kind === 'alone' ? { ...level, audible: false } : kind === 'louder' && id !== BACKING ? { ...level, volume: level.volume * 0.5 } : level);
        else out.set(id, kind === 'without' ? { ...level, audible: false } : kind === 'louder' ? { ...level, volume: level.volume * 1.4 } : level);
    }
    return out;
}

/**
 * A render with a click on every beat and a bar of count-in before it (the track gets longer by that
 * bar; the singing keeps its place after it). Bar lines are accented, after the pickup.
 */
export function withClicks({ channels, sampleRate }, { beatSeconds, beatsPerBar, pickupBeats = 0, accent, normal }) {
    const lead = Math.round(beatsPerBar * beatSeconds * sampleRate);
    const out = channels.map((c) => {
        const longer = new Float32Array(c.length + lead);
        longer.set(c, lead);
        return longer;
    });
    for (let beat = -beatsPerBar; Math.round(beat * beatSeconds * sampleRate) + lead < out[0].length; beat++) {
        const at = lead + Math.round(beat * beatSeconds * sampleRate);
        const sound = (((beat - pickupBeats) % beatsPerBar) + beatsPerBar) % beatsPerBar === 0 ? accent : normal;
        for (const c of out) for (let i = 0; i < sound.length && at + i < c.length; i++) c[at + i] += sound[i] * 0.5;
    }
    return { channels: out, sampleRate };
}
