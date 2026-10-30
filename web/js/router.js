// A few screens don't need a router library: the projects list at /, a project at /p/<id> (and
// Sing at /p/<id>/sing), the creator at /create (a draft at /create/<id>), a template of someone's
// own at /t/<id>, the
// admin at /admin/<section> (and /admin/<section>/<id>), and the styleguide.

import { reactive } from 'vue';

export const route = reactive({ name: 'projects', params: {} });

const routes = [
    { name: 'sing', pattern: /^\/p\/([\w-]+)\/sing\/?$/, keys: ['id'] },
    { name: 'studio', pattern: /^\/p\/([\w-]+)\/?$/, keys: ['id'] },
    { name: 'admin', pattern: /^\/admin(?:\/([\w-]+))?(?:\/([\w-]+))?\/?$/, keys: ['section', 'id'] },
    { name: 'styleguide', pattern: /^\/styleguide\/?$/, keys: [] },
    { name: 'auth', pattern: /^\/(signin\/link|signin|verify|reset|email\/confirm|email\/revert)\/?$/, keys: ['page'] },
    { name: 'settings', pattern: /^\/settings\/?$/, keys: [] },
    { name: 'storage', pattern: /^\/storage\/?$/, keys: [] },
    { name: 'voice', pattern: /^\/voice\/?$/, keys: [] },
    { name: 'workout', pattern: /^\/workout\/?$/, keys: [] },
    { name: 'plus', pattern: /^\/plus\/?$/, keys: [] },
    { name: 'reminders', pattern: /^\/settings\/reminders\/?$/, keys: [] },
    { name: 'course', pattern: /^\/learn\/?$/, keys: [] },
    { name: 'learn', pattern: /^\/learn\/([\w-]+)\/?$/, keys: ['id'] },
    { name: 'plusCourse', pattern: /^\/learn\/c\/([\w-]+)\/?$/, keys: ['id'] },
    { name: 'beta', pattern: /^\/beta(?:\/([\w-]+))?\/?$/, keys: ['token'] },
    { name: 'create', pattern: /^\/create(?:\/([\w-]+))?\/?$/, keys: ['draft'] },
    { name: 'template', pattern: /^\/t\/([\w-]+)\/?$/, keys: ['id'] },
    { name: 'join', pattern: /^\/join\/([\w-]+)\/?$/, keys: ['token'] },
    { name: 'early', pattern: /^\/early\/([\w-]+)\/?$/, keys: ['code'] },
    { name: 'invite', pattern: /^\/invite\/([\w-]+)\/?$/, keys: ['code'] },
    { name: 'groups', pattern: /^\/groups\/?$/, keys: [] },
    { name: 'group', pattern: /^\/groups\/([\w-]+)\/?$/, keys: ['id'] },
    { name: 'choir', pattern: /^\/choir\/([\w-]+)\/?$/, keys: ['token'] },
    { name: 'challenge', pattern: /^\/challenge\/([\w-]+)\/?$/, keys: ['token'] },
    { name: 'moved', pattern: /^\/moved\/?$/, keys: [] },
    { name: 'contact', pattern: /^\/contact\/?$/, keys: [] },
    { name: 'fresh', pattern: /^\/start-fresh\/?$/, keys: [] },
    { name: 'ideas', pattern: /^\/ideas\/?$/, keys: [] },
    { name: 'idea', pattern: /^\/ideas\/([\w-]+)\/?$/, keys: ['id'] },
    { name: 'projects', pattern: /^\/?$/, keys: [] },
];

function resolve(path) {
    for (const candidate of routes) {
        const match = path.match(candidate.pattern);
        if (match) {
            route.name = candidate.name;
            route.params = Object.fromEntries(candidate.keys.map((key, i) => [key, match[i + 1]]));
            return;
        }
    }
    route.name = 'projects';
    route.params = {};
    history.replaceState({}, '', '/');
}

export function navigate(path, { replace = false } = {}) {
    // Each page remembers the page it was opened from, in its own history entry, for goBack().
    const from = replace ? history.state?.from ?? null : location.pathname + location.search;
    history[replace ? 'replaceState' : 'pushState']({ from }, '', path);
    resolve(location.pathname);
    window.scrollTo(0, 0);
}

/**
 * Back to the page this one was opened from (the browser's own Back, so the history stays
 * honest), or to `fallback` when it was opened cold: a shared link, a new tab, a bookmark.
 */
export function goBack(fallback = '/') {
    if (history.state?.from) history.back();
    else navigate(fallback, { replace: true });
}

window.addEventListener('popstate', () => resolve(location.pathname));
resolve(location.pathname);
