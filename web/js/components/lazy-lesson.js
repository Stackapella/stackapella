// What a lesson draws only once you've begun (the coordinator's lesson 1 waterfall, 2026-11-09): the exercise, its
// result, the comfortable note's result, the lesson's end and Keep the finale. About 100 KB (gzip) of a first visit's
// lesson 1 was these and what they import, all downloaded before its first screen could show on a slow phone. Now
// they load from the page's own version (js/lazy.js), fetched ahead as soon as a lesson is drawn (prefetchLesson,
// views/learn.js), all at once on a first visit (js/preload.js's lesson list, written by tool/preload_list.py), so
// they're in long before the note finder or a first tap needs them. A step drawn before they're in shows a quiet
// spinner after a moment, never a blank space. Their words come with lesson 1's own (locales/lesson1/en.json), so
// none of them waits for the rest of the words.
import { defineAsyncComponent } from 'vue';
import { lazyView } from '../lazy.js';
import { LOADING_DELAY_MS } from './screen-loading.js';

// The lesson's words are in already (above), so a load waits for nothing but its code.
const ready = () => Promise.resolve();

const Waiting = {
    template: `
        <div class="grid min-h-40 place-items-center" aria-busy="true" data-lesson-loading>
            <span class="spinner text-accent" aria-hidden="true"></span>
            <p class="sr-only" role="status">{{ t('common.loading') }}</p>
        </div>`,
};

const parts = [];
function lazyPart(load) {
    const loader = lazyView(load, { words: ready });
    const component = defineAsyncComponent({ loader, loadingComponent: Waiting, delay: LOADING_DELAY_MS });
    parts.push({ loader, component });
    return component;
}

const exercise = () => import('./exercise.js');
const results = () => import('./results.js');
export const Exercise = lazyPart(() => exercise().then((m) => m.Exercise));
export const NoteResult = lazyPart(() => results().then((m) => m.NoteResult));
export const TakeResult = lazyPart(() => results().then((m) => m.TakeResult));
export const LessonEnd = lazyPart(() => results().then((m) => m.LessonEnd));
export const KeepSong = lazyPart(() => import('./keep-song.js').then((m) => m.KeepSong));
// Off lesson 1's first screen too (the first-visit speed pass, 2026-11-13): the account wall, the chord moment and Then
// and now (a lesson's end), the voice card (unit 1's end) and the boss lessons'. Not the note finder: loaded here, the mic
// step after Continue opened 1.8 s late on Slow 4G, so it stays with the lesson (views/learn.js).
const boss = () => import('./boss.js');
export const CourseWall = lazyPart(() => import('./course-wall.js').then((m) => m.CourseWall));
export const ChordMoment = lazyPart(() => import('./chord-moment.js').then((m) => m.ChordMoment));
export const ThenAndNow = lazyPart(() => import('./then-and-now.js').then((m) => m.ThenAndNow));
export const VoiceCard = lazyPart(() => import('./voice-card.js').then((m) => m.VoiceCard));
export const BossBar = lazyPart(() => boss().then((m) => m.BossBar));
export const BossEnd = lazyPart(() => boss().then((m) => m.BossEnd));
export const BossIntro = lazyPart(() => boss().then((m) => m.BossIntro));

let fetching = null;
/**
 * Every part fetched ahead, once (a failure is left for its first use, which loads it again or reloads as one version).
 * Vue's own wrapper is told too (its __asyncLoader, which then has the code at once), so a part draws in the same tick as
 * the change that shows it, as it did before it was lazy: the take's result is there for focus on the next tick (a
 * screen reader hears it), the lesson's end for its ref (focus, the stars' celebration). Without it each part's first
 * draw came a moment later, and singing_a11y's focus on the result's heading never came (2026-11-10).
 */
export function prefetchLesson() {
    window.stackapellaPreloadLesson?.(); // a first visit: every module at once (the song stores' too), not one import level at a time
    fetching ??= Promise.all(parts.map(async ({ loader, component }) => {
        await loader.prefetch();
        if (loader.loaded()) await component.__asyncLoader?.().catch((error) => console.info('A lesson part will load when shown', error?.message ?? error));
    }));
    return fetching;
}
