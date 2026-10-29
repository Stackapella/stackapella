// The pitch camera (Hillel: "zoom in, and let the camera follow the range they're singing in", like
// the camera in SNK's Neo Geo fighters, subtle and smooth; ux-spec 5.7c). It frames the notes of
// the next few seconds and the live voice with a little room, so a narrow phrase fills the lane with
// big bars and a wide leap eases out to fit, before the leap arrives (it looks ahead). The frame
// moves on a critically damped spring (no overshoot, settled in about 0.8 s), and only for a real
// change: a quick note inside the frame moves nothing. A long note held in tune pushes in a touch;
// a phrase's end pulls back a touch. With reduced motion: one frame a phrase, cut at its start.
// Pure, tested in tests/camera.test.js; the lanes (components/sing/lane.js, the exercises) draw with it.

export const CAMERA = {
    lookBehind: 0.4, // seconds of notes just gone still framed
    lookAhead: 3, // seconds of notes to come framed (a leap is framed before it arrives)
    pad: 1.5, // semitones of room above and below
    minSpan: 7, // never tighter than this (it would jump around)
    settle: 0.8, // seconds to settle (critically damped)
    deadband: { centre: 0.75, span: 1 }, // a new frame this close to the goal changes nothing
    lockIn: 0.9, // a long note held in tune: the span this much tighter
    lockAfter: 0.6, // seconds in tune on a note before the push-in
    lockLong: 1, // a note this long (seconds) or more can push in
    phraseOut: 1.1, // a phrase's end (nothing to sing for a while): the span this much wider
    restAhead: 1.2, // nothing to sing for this long ahead: a phrase's end
};

/** A fresh camera: nothing framed yet (the first frame is taken at once). */
export function camera() {
    return { centre: null, span: null, vc: 0, vs: 0, goal: null };
}

/**
 * The frame for the notes around `now` (`notes`: `[{ start, end, pitch }]` in seconds and MIDI) and
 * the live `voice` (MIDI or null): `{ centre, span }`, or null when there's nothing to frame.
 * `maxSpan`: the part's whole range with its room (never wider).
 */
export function frameFor(notes, now, { voice = null, maxSpan = 36, minSpan = CAMERA.minSpan, from = now - CAMERA.lookBehind, to = now + CAMERA.lookAhead } = {}) {
    const near = notes.filter((n) => n.end > from && n.start < to).map((n) => n.pitch);
    if (!near.length && voice === null) return null;
    let lo = Math.min(...near, ...(voice === null ? [] : [voice]));
    let hi = Math.max(...near, ...(voice === null ? [] : [voice]));
    // The voice far off (a slip, a cough) doesn't drag the frame further than the notes allow.
    if (near.length && voice !== null) {
        lo = Math.max(lo, Math.min(...near) - maxSpan / 2);
        hi = Math.min(hi, Math.max(...near) + maxSpan / 2);
    }
    const span = Math.min(maxSpan, Math.max(minSpan, hi - lo + 2 * CAMERA.pad));
    return { centre: (lo + hi) / 2, span };
}

/** The game's touches on a frame: a push-in while a long note is held in tune, a pull-back at a phrase's end. */
export function moodFor(notes, now, { inTuneFor = 0 } = {}) {
    const current = notes.find((n) => now >= n.start && now < n.end);
    if (current && current.end - current.start >= CAMERA.lockLong && inTuneFor >= CAMERA.lockAfter) return CAMERA.lockIn;
    const coming = notes.some((n) => n.start >= now - 1e-6 && n.start < now + CAMERA.restAhead) || !!current;
    return coming ? 1 : CAMERA.phraseOut;
}

/**
 * The camera after `dt` seconds toward `target` (`{ centre, span }`, or null to hold): a critically
 * damped spring on the centre and the span. The first frame, a big jump in time (a seek) or
 * `still` (reduced motion) take the target at once.
 */
export function stepCamera(cam, target, dt, { still = false } = {}) {
    if (!target) return { ...cam, vc: 0, vs: 0 };
    const goal = cam.goal && Math.abs(target.centre - cam.goal.centre) < CAMERA.deadband.centre && Math.abs(target.span - cam.goal.span) < CAMERA.deadband.span ? cam.goal : target;
    if (cam.centre === null || still || dt > 0.5) return { centre: goal.centre, span: goal.span, vc: 0, vs: 0, goal };
    const omega = 5.8 / CAMERA.settle; // within 2% of the goal after `settle`
    let { centre, span, vc, vs } = cam;
    // Small steps keep the spring exact at any frame rate.
    for (let left = dt; left > 1e-6; left -= 1 / 120) {
        const h = Math.min(left, 1 / 120);
        vc += (omega * omega * (goal.centre - centre) - 2 * omega * vc) * h;
        vs += (omega * omega * (goal.span - span) - 2 * omega * vs) * h;
        centre += vc * h;
        span += vs * h;
    }
    return { centre, span, vc, vs, goal };
}

/** The pitches shown by a camera: `{ low, high }` (MIDI, fractional). */
export function viewOf(cam) {
    return { low: cam.centre - cam.span / 2, high: cam.centre + cam.span / 2 };
}

/**
 * Reduced motion: one frame for the whole phrase `now` is in (or the next one), so the frame only
 * changes at a phrase's start. `phrases`: `[[startSeconds, endSeconds]]`.
 */
export function phraseFrame(notes, now, phrases, options = {}) {
    const phrase = phrases.find(([a, b]) => now >= a - 1e-6 && now < b) ?? phrases.find(([a]) => a > now) ?? phrases.at(-1);
    if (!phrase) return frameFor(notes, now, options);
    return frameFor(notes, now, { ...options, from: phrase[0], to: phrase[1] });
}
