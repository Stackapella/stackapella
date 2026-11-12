// A unit's song (retention row 8; the courses lead's unitSong in course.json, Hillel 2026-11-11: "a real song at unit 1's
// end and the wall"): at a unit's end, beside Back to the course, one quiet offer to sing a real song's part with the
// other voices singing around you, opened straight in Sing along. Counted anonymously, once a device a place, as a path:
// us_shown (on screen), us_tap (opened), us_kept (a take from that visit kept). Places: u1 to u4, and the account wall.

import { count, KINDS } from './usage.js';
import { local, session as tabStorage } from './storage.js';

export const PLACES = ['u1', 'u2', 'u3', 'u4', 'wall'];
const EVENTS = ['shown', 'tap', 'kept'];
for (const e of EVENTS) for (const p of PLACES) KINDS.add(`us_${e}:${p}`);

const COUNTED = 'stackapella.unitSong.counted'; // the device's firsts: { "us_tap:u1": true }
const OPEN = 'stackapella.unitSong.open'; // this tab's visit: { projectId, place }

/** Counts `event` at `place` the first time on this device (so shown, tap and kept read as a path). */
export function countUnitSong(event, place) {
    if (!EVENTS.includes(event) || !PLACES.includes(place)) return;
    const kind = `us_${event}:${place}`;
    let done = {};
    try {
        done = JSON.parse(local.get(COUNTED) ?? '{}') ?? {};
    } catch {
        done = {};
    }
    if (done[kind]) return;
    done[kind] = true;
    local.set(COUNTED, JSON.stringify(done));
    count(kind);
}

/** The song opened from `place`: a take kept in this visit to it counts as kept there. */
export function openedFrom(projectId, place) {
    tabStorage.set(OPEN, JSON.stringify({ projectId, place }));
}

/** A take was kept in `projectId`: us_kept for the place that opened it, if this visit came from one. */
export function keptIn(projectId) {
    try {
        const open = JSON.parse(tabStorage.get(OPEN) ?? 'null');
        if (open?.projectId === projectId) countUnitSong('kept', open.place);
    } catch {
        // nothing opened from a unit's end in this tab
    }
}

/** Sing was left: coming back to the song another way later is no longer that visit. */
export function leftSong(projectId) {
    try {
        if (JSON.parse(tabStorage.get(OPEN) ?? 'null')?.projectId === projectId) tabStorage.remove(OPEN);
    } catch {
        tabStorage.remove(OPEN);
    }
}

/** The part a unit's song is sung on: "melody" is the template's tune (melody: true), else the part's own key. */
export function songPartKey(template, part) {
    if (part !== 'melody') return part;
    return (template.parts.find((p) => p.melody) ?? template.parts[0])?.key ?? null;
}

/** The song with `key`'s words sung on `syllable` ("oo"): every worded note on it, the other parts as they are. */
export function onSyllable(template, key, syllable) {
    return {
        ...template,
        parts: template.parts.map((p) => (p.key !== key || !p.guide?.notes ? p : { ...p, guide: { ...p.guide, notes: p.guide.notes.map((n) => (n.lyric ? { ...n, lyric: syllable } : n)) } })),
    };
}

/**
 * The unit's song to offer at a lesson's end, or null: the free course's units 1 to 4 that have one, at the unit's
 * challenge sung with stars (never a boss or a cool-down). The next unit's first lesson stays the main button beside it.
 * `place` is what's counted.
 */
export function unitSongOffer({ entry, done, progress, course }) {
    if (!entry || !done || !entry.unit?.song || entry.boss || entry.cooldown || !entry.lesson?.challenge) return null;
    if (!(done.stars >= 1) && !(progress?.lessons?.[entry.lesson.id]?.stars >= 1)) return null;
    const index = course?.units?.findIndex((u) => u.id === entry.unit.id) ?? -1;
    if (index < 0 || index > 3) return null;
    return { ...entry.unit.song, place: `u${index + 1}` };
}

/**
 * The project for a unit's song, made once and kept: its part (the tune, or the part named), on its syllable when it
 * has one, in the singer's key (the real key when the range covers it, as a lesson's song). Opened from `place`.
 */
export async function unitSongProject(song, { lessonId, unitId, place }) {
    const [{ projectsStore }, { loadTemplate }, { realKeyFit }, { songFit }, { knownRange, learning }] = await Promise.all([
        import('./lazy-stores.js'), import('./templates.js'), import('./real-key.js'), import('./song-course.js'), import('./stores/learning.js'),
    ]);
    const { loadProjects, projects, startFromTemplate } = await projectsStore();
    await loadProjects();
    let project = projects.all.find((p) => p.course?.unitSong === unitId && p.template?.id === song.template);
    if (!project) {
        let template = await loadTemplate(song.template);
        const focusKey = songPartKey(template, song.part);
        // Units 2 and 3 sing on "oo" (the syllable): the singer's part on it, the other voices with their words.
        if (song.syllable) template = onSyllable(template, focusKey, song.syllable);
        const fit = (await realKeyFit(template, focusKey, knownRange())) ?? songFit(template, focusKey, knownRange(), learning.progress.home ?? null);
        project = await startFromTemplate(template, { focusKey, transpose: fit?.transpose ?? 0, keyChosen: true, course: { lessonId, unitSong: unitId, partKey: focusKey, fit } });
    }
    countUnitSong('tap', place);
    openedFrom(project.id, place);
    return project;
}
