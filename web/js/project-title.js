// A song's name as shown, on its own: the lesson view, the cloud's conflicts and the delete confirm say it without
// loading the projects store (stores/projects.js, which re-exports it), so a first visit's lesson 1 doesn't download it.
import { t } from './i18n.js';
import { songTitle } from './song-titles.js';

/** A project's name as shown: its title (a library song's own in the page's language while it's still that), or "Untitled song". */
export function shownTitle(project) {
    const own = project?.title?.trim();
    if (own && project.template && own === project.template.title) return songTitle(project.template);
    return own || t('project.untitled');
}
