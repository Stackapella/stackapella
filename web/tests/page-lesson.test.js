// A lesson opened from its own public page (js/page-lesson.js; Hillel, 2026-11-08, "Yes, once"): granted once per course
// on a device, only by the page's own way in (the ref and our site's referrer), and then sung by anyone.
import { fromSite, noteArrival, pageGrants, pageLesson, takePageLesson } from '/js/page-lesson.js';
import { guestOpen, lessonAccess } from '/js/courses.js';
import { emptyProgress } from '/js/course.js';
import { local, session } from '/js/storage.js';
import { assert, test } from './harness.js';

const course = {
    id: 'page-test',
    units: [
        { id: 'u1', lessons: [{ id: 'pt-1' }, { id: 'pt-2' }] },
        { id: 'u2', lessons: [{ id: 'pt-3' }, { id: 'pt-4' }] },
    ],
};

function reset() {
    delete pageGrants[course.id];
    local.remove('stackapella.pageLessons');
    session.remove('stackapella.pageArrival');
}

test('page lesson: only our own site\'s referrer counts as the page\'s way in', () => {
    assert(fromSite('https://stackapella.com/', 'app.stackapella.com'), 'the site');
    assert(fromSite('https://www.stackapella.com/courses/x', 'app.stackapella.com'), 'its www');
    assert(!fromSite('', 'app.stackapella.com'), 'typed or pasted: no referrer');
    assert(!fromSite('https://app.stackapella.com/learn', 'app.stackapella.com'), 'the app itself');
    assert(!fromSite('https://evil.example/stackapella.com', 'app.stackapella.com'), 'another site');
    assert(!fromSite('https://stackapella.com.evil.example/', 'app.stackapella.com'), 'a look-alike host');
});

test('page lesson: from its page, a later unit\'s lesson opens once for a guest, and counts as the course\'s guest lesson', () => {
    reset();
    const fresh = emptyProgress();
    assert(lessonAccess(course, 'pt-3', fresh).needsAccount && !lessonAccess(course, 'pt-3', fresh).open, 'without the page: walled and locked');
    noteArrival({ search: '?ref=lesson-page', path: '/learn/pt-3', referrer: 'https://stackapella.com/', host: 'app.stackapella.com' });
    assert(takePageLesson(course.id, 'pt-3', true), 'granted as it opens');
    const now = lessonAccess(course, 'pt-3', fresh);
    assert(now.open && !now.needsAccount, 'sung by a guest, its unit\'s lock waived');
    assert(lessonAccess(course, 'pt-3', fresh, { signedIn: true }).open, 'and by a new account');
    assert(pageLesson(course.id, 'pt-3') && !guestOpen(course, 'pt-1'), 'it is the course\'s guest lesson now: lesson 1 asks for the account');
    assert(!lessonAccess(course, 'pt-4', fresh).open && lessonAccess(course, 'pt-4', fresh).needsAccount, 'the next lesson: walled and locked as usual');
    reset();
});

test('page lesson: once per course, only from the page, and never spent on a lesson already open', () => {
    reset();
    const page = (lesson, referrer = 'https://stackapella.com/') => noteArrival({ search: '?ref=lesson-page', path: `/learn/${lesson}`, referrer, host: 'app.stackapella.com' });
    page('pt-3');
    assert(takePageLesson(course.id, 'pt-3', true), 'the first');
    page('pt-4');
    assert(!takePageLesson(course.id, 'pt-4', true) && !pageLesson(course.id, 'pt-4'), 'a second lesson from a page in the same course: walled');
    reset();
    noteArrival({ search: '?ref=lesson-page', path: '/learn/pt-3', referrer: '', host: 'app.stackapella.com' });
    assert(!takePageLesson(course.id, 'pt-3', true), 'the ref with no page behind it (a pasted link): nothing');
    noteArrival({ search: '', path: '/learn/pt-3', referrer: 'https://stackapella.com/', host: 'app.stackapella.com' });
    assert(!takePageLesson(course.id, 'pt-3', true), 'from the site without the ref: nothing');
    page('pt-1');
    assert(!takePageLesson(course.id, 'pt-1', false) && !pageGrants[course.id], 'a lesson open anyway: the grant is kept for later');
    assert(!takePageLesson(course.id, 'pt-1', true), 'and the arrival is spent');
    reset();
});
