// A song's name as shown, on its own: the lesson view, the cloud's conflicts and the delete confirm say it without
// loading the projects store (stores/projects.js, which re-exports it), so a first visit's lesson 1 doesn't download it.
import { t } from './i18n.js';

/** A project's name as shown: its title, or "Untitled song" while it has none. */
export function shownTitle(project) {
    return project?.title?.trim() || t('project.untitled');
}
