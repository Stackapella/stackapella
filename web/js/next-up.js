// What waits, named (docs/retention-plan.md, idea 2; Hillel, 2026-11-11: ship to all, read at two weeks): one line
// where a first session can end (the course wall, the lesson's end), and the one quiet way back for a guest not
// making an account: the device's share sheet with the lesson's address, or one calendar entry for tomorrow made on
// the device. Nothing goes through us.

import { localized, t } from './i18n.js';
import { findLesson } from './stores/learning.js';
import { lessonMinutes } from './results.js';
import { count } from './usage.js';

const MORNING = 9; // the calendar entry's hour, morning or evening, on the singer's own clock
const EVENING = 19;
const ENTRY_MINUTES = 15;

/** "Next: Lesson 2, Match a note, about 3 minutes." for the lesson `id` (its place in its unit), or '' if it isn't one. */
export function nextUpLine(id) {
    const entry = id ? findLesson(id) : null;
    if (!entry || entry.boss || entry.cooldown) return '';
    return t('course.nextUp', { number: entry.index + 1, title: localized(entry.lesson.title), minutes: lessonMinutes(entry.lesson) });
}

/** The lesson's own address, tagged with where the way back came from (a source, never who). */
export const lessonLink = (id, ref) => `${location.origin}/learn/${id}?ref=${ref}`;

/** "Send myself the link": the device's share sheet (to their notes, messages or email, by their own hand). */
export async function sendMyselfTheLink(id) {
    count('back_link_tap');
    const title = localized(findLesson(id)?.lesson.title) || 'Stackapella'; // i18n-ignore: the app's name
    try {
        await navigator.share({ title, text: t('courses.wall.shareText', { title }), url: lessonLink(id, 'back-link') });
    } catch (error) {
        if (error?.name !== 'AbortError') console.warn('The link was not shared', error);
    }
}

const stamp = (d) => `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}T${String(d.getHours()).padStart(2, '0')}${String(d.getMinutes()).padStart(2, '0')}00`;
const utc = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d+/, '');
const escape = (s) => String(s).replace(/\\/g, '\\\\').replace(/;/g, '\;').replace(/,/g, '\\,').replace(/\n/g, '\\n');

/** One calendar entry for tomorrow (`when`: 'morning' or 'evening'), on the device's own clock, holding the lesson's link. */
export function calendarEntry(id, when, now = new Date()) {
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, when === 'evening' ? EVENING : MORNING, 0);
    const end = new Date(start.getTime() + ENTRY_MINUTES * 60000);
    const entry = findLesson(id);
    const title = localized(entry?.lesson.title) || '';
    const link = lessonLink(id, 'back-calendar');
    return [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Stackapella//Lesson//EN', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
        `UID:${crypto.randomUUID()}@stackapella.com`, `DTSTAMP:${utc(now)}`, `DTSTART:${stamp(start)}`, `DTEND:${stamp(end)}`,
        `SUMMARY:${escape(t('courses.wall.calendarTitle', { title }))}`,
        `DESCRIPTION:${escape(`${nextUpLine(id)}\n${link}`)}`, `URL:${link}`,
        'END:VEVENT', 'END:VCALENDAR', '',
    ].join('\r\n');
}

/** "Add tomorrow to my calendar": the entry, saved as a file the device's calendar opens. */
export function addTomorrow(id, when) {
    count('back_cal_tap');
    const url = URL.createObjectURL(new Blob([calendarEntry(id, when)], { type: 'text/calendar' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: 'stackapella-tomorrow.ics' });
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000); // a page timer for a file, never audio
}
