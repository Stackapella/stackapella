// Whether this singer has sung a first song yet (the first-session review, 2026-11-13; Hillel: yes): a starred lesson, or a
// song of their own started. Until then the song's sheet is the song, Start and Play, and the choices that only make
// sense once you've sung (how many singers, Edit) wait. One test for both skins (game's fresh mirrors it).
import { learning } from './stores/learning.js';
import { projects } from './stores/projects.js';

/** True once a lesson has stars or a song of the singer's own exists on this device. */
export function sungYet() {
    return Object.values(learning.progress?.lessons ?? {}).some((lesson) => (lesson?.stars ?? 0) > 0) || (projects.all ?? []).length > 0;
}
