// The song stores, loaded when first needed (the coordinator's lesson 1 waterfall, 2026-11-09): your songs
// (stores/projects.js, with practice.js and guides.js), your own song templates (stores/templates.js) and the library's
// real voices (stores/catalogue.js). A first visit's lesson 1 never shows any of them, but they were downloaded before
// its first screen. Each comes from the page's own version (js/lazy.js), and all are fetched ahead with the lesson's
// later parts (components/lazy-lesson.js prefetchLesson) and once the first screen is up (main.js fetchAhead), so a
// song step's Open in Sing never waits for one. The screens that live on them (Home, your songs, Sing, the studio)
// import them themselves.
import { lazyView } from './lazy.js';

// The words they use are in lesson 1's own (tool/preload_list.py), so a load waits for nothing but its code.
const ready = () => Promise.resolve();
const store = (load) => lazyView(load, { words: ready });

/** stores/projects.js, imported on first use: `const { projects, loadProjects } = await projectsStore();` */
export const projectsStore = store(() => import('./stores/projects.js'));
/** stores/templates.js (your own songs' templates), imported on first use. */
export const templatesStore = store(() => import('./stores/templates.js'));
/** stores/catalogue.js (the library's real voices), imported on first use. */
export const catalogueStore = store(() => import('./stores/catalogue.js'));

let fetching = null;
/** All three fetched ahead, once (a failure is left for the first use). */
export function prefetchStores() {
    fetching ??= Promise.all([projectsStore, templatesStore, catalogueStore].map((loader) => loader.prefetch()));
    return fetching;
}
