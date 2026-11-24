// Ready by rehearsal's plans on this device and in the account (progress.rehearsals; js/rehearsal-plan.js does the
// sums): making one, reading each day's mark from Coach, spreading a missed day, removing one, and the ways back (a
// calendar file of the practice days, the share sheet). The date and the plan go nowhere else; the server sees only the
// anonymous counts (rr_plan_made, rr_day_done, rr_cal_add).

import { computed } from 'vue';
import { localized, t } from '../i18n.js';
import { navigate } from '../router.js';
import { count } from '../usage.js';
import { daysUntil, isoDate, localDate, rehearsalDates } from '../rehearsal.js';
import { dayNow, livePlans, makePlan, phrasesDone, replan, upsertPlan } from '../rehearsal-plan.js';
import { progressOf } from '../sing.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { songTitle } from '../song-titles.js';
import { learning, loadLearning, setRehearsals } from './learning.js';
import { loadProjects, projects, startFromTemplate } from './projects.js';

const EVENING = 18; // the calendar entries' hour, on the device's own clock
const ENTRY_MINUTES = 20;

/** The plans still on, soonest rehearsal first. */
export const plans = computed(() => livePlans(learning.progress?.rehearsals ?? []).sort((a, b) => a.date.localeCompare(b.date)));

export const planFor = (songId, partKey) => plans.value.find((p) => p.songId === songId && p.partKey === partKey) ?? null;

/** Makes (or makes again, with a new date) a plan for the part, and keeps it. Returns the plan. */
export async function startPlan({ songId, partKey, date, groupId = null }) {
    await loadLearning();
    const template = await loadTemplate(songId);
    const plan = makePlan({ songId, partKey, date, template, groupId });
    await setRehearsals(upsertPlan(learning.progress?.rehearsals ?? [], plan));
    count('rr_plan_made');
    return plan;
}

/** The plan taken off this device and the account (a removal, so the other device's copy goes too). */
export async function removePlan(songId, partKey) {
    const list = (learning.progress?.rehearsals ?? []).map((p) => (p.songId === songId && p.partKey === partKey ? { ...p, removedAt: Date.now() } : p));
    await setRehearsals(list);
}

/** The project the plan's days are sung in: the song's own practice project here, else a new one on the part. */
export async function projectFor(plan, template) {
    await loadProjects();
    const found = projects.all.find((p) => p.template?.id === plan.songId && !p.deleted && !p.choir && !p.beta && !p.course && !p.shared);
    return found ?? startFromTemplate(template, { renderGuides: false, focusKey: plan.partKey });
}

/**
 * The plan as Coach has it now: each phrase day done by its own mark (once, counted), a missed day spread over the days
 * left. Kept only when something changed. Returns the plan.
 */
export async function refreshPlan(plan) {
    const template = await loadTemplate(plan.songId);
    await loadProjects();
    const project = projects.all.find((p) => p.template?.id === plan.songId && !p.deleted && !p.choir && !p.beta && !p.course && !p.shared);
    const progress = project ? progressOf(project.practice, plan.partKey) : null;
    let changed = false;
    const days = plan.days.map((d) => {
        if (d.doneAt || !phrasesDone(d, progress)) return d;
        changed = true;
        count('rr_day_done');
        return { ...d, doneAt: Date.now() };
    });
    let next = { ...plan, days };
    const spread = replan(next, template);
    if (spread !== next) {
        next = spread;
        changed = true;
    }
    if (changed) await setRehearsals(upsertPlan(learning.progress?.rehearsals ?? [], next));
    return next;
}

/**
 * The ready check passed: the plan says so (readyAt). A later check that isn't ready can't take it back yet: two devices'
 * copies merge by the later readyAt (rehearsal-merge.js), so that waits on a field of its own in the contract.
 */
export async function markReady(songId, partKey, at = Date.now()) {
    await loadLearning();
    const plan = livePlans(learning.progress?.rehearsals ?? []).find((p) => p.songId === songId && p.partKey === partKey);
    if (!plan || plan.readyAt) return;
    await setRehearsals(upsertPlan(learning.progress?.rehearsals ?? [], { ...plan, readyAt: at }));
}

const GROUP_DAYS = 60; // a plan's rehearsal at most this far ahead (the contract)

/**
 * What a group part's plan needs now (pure, for the tests): `make` (with the date), `remove`, or nothing. A group plan
 * follows its group's next rehearsal and goes when the group has none; the singer's own plan is never touched, and a
 * date whose plan they removed isn't made again. `removed`: the dates of this part's plans they removed.
 */
export function groupPlanStep({ rehearsal, groupId, plan = null, removed = [] }, today = new Date()) {
    const next = rehearsalDates(rehearsal, today).next;
    const days = next ? daysUntil(next, today) : null;
    const date = next && days >= 0 && days <= GROUP_DAYS ? isoDate(next) : null;
    if (plan && !plan.groupId) return null;
    if (!date) return plan?.groupId === groupId ? { action: 'remove' } : null;
    if (plan?.date === date || (!plan && removed.includes(date))) return null;
    return { action: 'make', date };
}

/**
 * A choir group member's plans from the group's own date (docs/rehearsal-ready-plan.md, "Groups": no question asked).
 * `parts`: Home's parts a director gave this singer (group-songs.js myGroupParts), each with its group's rehearsal.
 */
export async function planGroupParts(parts = [], today = new Date()) {
    await loadLearning();
    for (const part of parts) {
        if (!part?.templateId || !part.partKey) continue;
        const removed = (learning.progress?.rehearsals ?? []).filter((p) => p.songId === part.templateId && p.partKey === part.partKey && p.removedAt).map((p) => p.date);
        const step = groupPlanStep({ rehearsal: part.rehearsal, groupId: part.groupId, plan: planFor(part.templateId, part.partKey), removed }, today);
        if (step?.action === 'remove') await removePlan(part.templateId, part.partKey);
        if (step?.action === 'make') await startPlan({ songId: part.templateId, partKey: part.partKey, date: step.date, groupId: part.groupId }).catch((error) => console.warn('A group part\'s plan was not made', error));
    }
}

/** Start: the day's first phrase in Sing's Coach; the whole part's day (and "I know most of it"), the whole song. */
export async function startDay(plan, day = dayNow(plan)) {
    const template = await loadTemplate(plan.songId);
    const project = await projectFor(plan, template);
    const part = encodeURIComponent(plan.partKey);
    if (day?.phrases) navigate(`/p/${project.id}/sing?part=${part}&phrase=${day.phrases[0]}`);
    else navigate(`/p/${project.id}/sing?part=${part}&ready=${encodeURIComponent(plan.date)}`);
}

/** The plan's page address, tagged with where the way back came from (a source, never who). */
export const planLink = (plan, ref) => `${location.origin}/rehearse/${encodeURIComponent(plan.songId)}/${encodeURIComponent(plan.partKey)}${ref ? `?ref=${ref}` : ''}`;

/** "Alto, Silent Night": the part and the song, as the plan's title and its entries say them. */
export async function planTitle(plan) {
    const template = await loadTemplate(plan.songId);
    const part = template.parts.find((p) => p.key === plan.partKey);
    return t('rehearse.title', { part: part ? templatePartName(part) : plan.partKey, song: songTitle(template) || localized(template.title) });
}

const stamp = (d) => `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}T${String(d.getHours()).padStart(2, '0')}${String(d.getMinutes()).padStart(2, '0')}00`;
const utc = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d+/, '');
const escape = (s) => String(s).replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\n/g, '\\n');

/** One calendar file, an entry for each practice day still to do (at most five), each holding the plan's link. Pure. */
export function calendarFile(plan, title, words, now = new Date()) {
    const link = planLink(plan, 'rr-calendar');
    const events = plan.days.filter((d) => !d.doneAt).map((d) => {
        const day = localDate(d.date);
        const start = new Date(day.getFullYear(), day.getMonth(), day.getDate(), EVENING, 0);
        const end = new Date(start.getTime() + ENTRY_MINUTES * 60000);
        return ['BEGIN:VEVENT', `UID:${crypto.randomUUID()}@stackapella.com`, `DTSTAMP:${utc(now)}`, `DTSTART:${stamp(start)}`, `DTEND:${stamp(end)}`,
            `SUMMARY:${escape(title)}`, `DESCRIPTION:${escape(`${words(d)}\n${link}`)}`, `URL:${link}`, 'END:VEVENT'];
    });
    return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Stackapella//Rehearsal//EN', 'CALSCALE:GREGORIAN', ...events.flat(), 'END:VCALENDAR', ''].join('\r\n');
}

/** "Add to my calendar": the file, saved for the device's calendar to open. */
export function addToCalendar(plan, title, words) {
    count('rr_cal_add');
    const url = URL.createObjectURL(new Blob([calendarFile(plan, title, words)], { type: 'text/calendar' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: 'stackapella-rehearsal.ics' });
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000); // a page timer for a file, never audio
}

/** "Send myself the link": the device's share sheet, by their own hand. */
export async function sendPlanLink(plan, title) {
    try {
        await navigator.share({ title, text: t('rehearse.shareText', { title }), url: planLink(plan, 'rr-link') });
    } catch (error) {
        if (error?.name !== 'AbortError') console.warn('The plan link was not shared', error);
    }
}
