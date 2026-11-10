// A lesson opened from its own public page (Hillel, 2026-11-08, "Yes, once"): a lesson page's "Sing this lesson" can be
// sung once by anyone, a guest or a new account. It counts as that course's guest lesson (in place of its first) and
// waives its unit's lock, for that one lesson; past it, the account ask and the units work as usual.
//
// Only the page's own way in grants it, never a URL: the link carries ?ref=lesson-page, and the browser says it came
// from our site (the site's Referrer-Policy, strict-origin-when-cross-origin, gives the app "https://stackapella.com/";
// a typed, pasted or other site's link gives no such referrer, and a parameter in the link could be copied). Once per
// course on this device: the first lesson entered that way in a course is that course's for good.

import { reactive } from 'vue';
import { local, session } from './storage.js';

const ARRIVAL = 'stackapella.pageArrival'; // this tab: the lesson this visit came to from its page, until it's taken
const GRANTS = 'stackapella.pageLessons'; // this device: { courseId: lessonId }, the one lesson a page opened per course

function read() {
    try {
        const kept = JSON.parse(local.get(GRANTS) || '{}');
        return kept && typeof kept === 'object' ? kept : {};
    } catch (error) {
        console.info('The lessons opened from their pages were unreadable', error?.message ?? error);
        return {};
    }
}

/** courseId → the lesson its page opened on this device. */
export const pageGrants = reactive(read());

/** Whether `referrer` is our own site (the app's host without "app.", or its www.). */
export function fromSite(referrer, host = location.hostname) {
    let from;
    try {
        from = new URL(referrer).hostname;
    } catch {
        return false;
    }
    const site = host.replace(/^app\./, '');
    return from === site || from === `www.${site}`;
}

/** At the app's start: a lesson arrived at from its own page is kept for this tab, to be granted when it opens. */
export function noteArrival({ search = location.search, path = location.pathname, referrer = document.referrer, host = location.hostname } = {}) {
    const lesson = path.match(/^\/learn\/([\w-]+)\/?$/)?.[1];
    if (new URLSearchParams(search).get('ref') !== 'lesson-page' || !lesson || !fromSite(referrer, host)) return;
    session.set(ARRIVAL, lesson);
}

/**
 * The lesson `lessonId` of `courseId` was just opened: granted when this visit came to it from its page, the course has
 * no granted lesson yet, and it would otherwise be walled or locked (`blocked`). The arrival is spent either way.
 */
export function takePageLesson(courseId, lessonId, blocked) {
    if (session.get(ARRIVAL) !== lessonId) return false;
    session.remove(ARRIVAL);
    if (!blocked || pageGrants[courseId]) return false;
    pageGrants[courseId] = lessonId;
    local.set(GRANTS, JSON.stringify({ ...read(), [courseId]: lessonId }));
    return true;
}

/** Whether `lessonId` is the lesson a page opened in `courseId` on this device. */
export const pageLesson = (courseId, lessonId) => !!courseId && pageGrants[courseId] === lessonId;
