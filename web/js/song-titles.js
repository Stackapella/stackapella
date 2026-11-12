// A song's title in the page's language, where the language names the song its own way (the coordinator for Hillel,
// 2026-11-12: Are You Sleeping is "Frère Jacques" in Spanish). Read by every place a library song's title shows (Home's
// cards, the song's sheet and page, a group's songs, Sing's header through shownTitle); English, and a song with no such
// name, show the template's own title. The server keeps the same names for its pages (resources/data/song_titles).
import { locale, t, tk } from './i18n.js';

// By template id (a key in each language file; English's is the template's own title).
const TITLES = { 'are-you-sleeping': tk('songTitles.areYouSleeping') };

/** `template`'s title as the page shows it ({ id, title }; a group's song passes { id: templateId, title }). */
export function songTitle(template) {
    const own = template?.title ?? '';
    const key = template && TITLES[template.id];
    if (!key || locale.lang === 'en' || locale.lang.startsWith('en-')) return own;
    return t(key) || own;
}
