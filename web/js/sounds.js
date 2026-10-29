// The app's few sounds (Hillel's picks from the audition, 2026-10-28; their sources and CC0 in
// web/sounds/CREDITS.md): the stars as a lesson's last star lands (one, two or three), the mic
// heard for the first time, and a special moment (a badge, a level, a unit or the course done, a
// streak, a first song with every part). Each plays once, quietly, on the AudioContext clock, and
// never two at once: a special moment that meets the stars waits for them to end. Settings turns
// them off (prefs.sounds).

import { engine } from './audio.js';
import { prefs } from './stores/prefs.js';

export const SOUNDS = {
    stars1: '/sounds/stars-1.mp3',
    stars2: '/sounds/stars-2.mp3',
    stars3: '/sounds/stars-3.mp3',
    micReady: '/sounds/mic-ready.mp3',
    special: '/sounds/special.mp3',
};
const LEVEL = 0.4; // quiet: under the singing, never over it
const SPECIAL_AFTER = 0.5; // seconds a special moment waits, so the stars of the same moment go first

const buffers = new Map();
let playing = []; // [{ name, source, start, end }], what's scheduled or sounding

function load(name) {
    if (!buffers.has(name)) {
        buffers.set(name, fetch(SOUNDS[name]).then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error(`${name}: ${r.status}`)))).then((data) => engine.ctx.decodeAudioData(data)));
        buffers.get(name).catch(() => buffers.delete(name)); // tried again next time
    }
    return buffers.get(name);
}

function schedule(name, buffer, start) {
    const source = engine.ctx.createBufferSource();
    source.buffer = buffer;
    const gain = engine.ctx.createGain();
    gain.gain.value = LEVEL;
    source.connect(gain).connect(engine.playBus);
    source.start(start);
    const entry = { name, source, start, end: start + buffer.duration };
    source.onended = () => (playing = playing.filter((p) => p !== entry));
    playing.push(entry);
    return entry;
}

const REWARD_SOUNDS = new Set(['stars1', 'stars2', 'stars3', 'special']); // game mode only

/**
 * Plays `name` once: at `at` (the audio clock's seconds; the stars give when the last one lands),
 * else now. A special moment goes after whatever is sounding; anything else that meets a special
 * moment moves it to after itself. Returns quietly when sounds are off or nothing can play yet.
 */
export async function playSound(name, { at = null } = {}) {
    if (!prefs.sounds || !engine.ctx || engine.ctx.state !== 'running') return;
    // The rewards' sounds are game mode's (docs/skins-plan.md); the mic's ready sound is feedback, kept.
    if (REWARD_SOUNDS.has(name) && document.documentElement.dataset.skin === 'serious') return;
    let buffer;
    try {
        buffer = await load(name);
    } catch (error) {
        console.warn('A sound could not load', error);
        return;
    }
    const now = engine.ctx.currentTime + 0.02;
    if (name === 'special') {
        // A moment after, so the stars of the same moment (scheduled just after) can still move it.
        const after = Math.max(now + SPECIAL_AFTER, at ?? now, ...playing.map((p) => p.end));
        schedule(name, buffer, after);
        return;
    }
    const start = Math.max(now, at ?? now);
    const end = start + buffer.duration;
    // Specials that would sound with this one wait for it.
    const moved = playing.filter((p) => p.name === 'special' && p.start < end && p.end > start);
    for (const special of moved) {
        special.source.onended = null;
        special.source.stop();
        playing = playing.filter((p) => p !== special);
    }
    schedule(name, buffer, start);
    for (const special of moved) schedule('special', special.source.buffer, end);
}

/** The stars' sound as the last of `stars` lands, `landsIn` seconds from now. */
export function starsSound(stars, landsIn) {
    if (!stars || !engine.ctx) return;
    playSound(`stars${Math.min(3, stars)}`, { at: engine.ctx.currentTime + landsIn });
}
