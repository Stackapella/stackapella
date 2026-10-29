// Real singers' clips for the courses (web/course/plus/clips.json's slots, recorded by beta singers
// and published: contract courses.clips.list). A listening round's clip or a self-check's model can
// name a slot (`"clip": "vibrato.natural"`); once it's published it plays, credited, and until then
// (or offline, or when the file won't load) the step plays its own notes, or describes the sound.
// The list is asked once per course and each recording decoded once.

import { api } from './api.js';
import { engine } from './audio.js';

const lists = new Map(); // course id: a promise of Map(slot: { url, durationSeconds, credit })
const buffers = new Map(); // url: a promise of an AudioBuffer

/** The course a slot belongs to: its id's first part ("pop.breathy" is Pop and country's). */
export const courseOfSlot = (slot) => String(slot).split('.')[0];

/** A course's published clips, by slot (empty when there are none, or the list can't be had). */
export function publishedClips(courseId) {
    if (!lists.has(courseId)) {
        lists.set(courseId, api.courses.clips.list(courseId)
            .then((response) => new Map((response.data ?? []).map((clip) => [clip.slot, clip])))
            .catch((error) => {
                lists.delete(courseId); // asked again next time (offline now, say)
                console.warn('Course clips not loaded', error);
                return new Map();
            }));
    }
    return lists.get(courseId);
}

/** The published clips for every slot named in `slots`: `{ slot: { url, credit, ... } }`. */
export async function publishedFor(slots) {
    const named = [...new Set(slots.filter(Boolean))];
    const courses = await Promise.all([...new Set(named.map(courseOfSlot))].map(async (id) => [id, await publishedClips(id)]));
    const byCourse = new Map(courses);
    return Object.fromEntries(named.map((slot) => [slot, byCourse.get(courseOfSlot(slot))?.get(slot) ?? null]).filter(([, clip]) => clip));
}

/** A published clip's sound, decoded at the context's rate; rejects when it won't load. */
export function clipBuffer(url) {
    if (!buffers.has(url)) {
        buffers.set(url, fetch(url)
            .then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error(`clip ${r.status}`))))
            .then((data) => engine.ctx.decodeAudioData(data))
            .catch((error) => {
                buffers.delete(url);
                throw error;
            }));
    }
    return buffers.get(url);
}
