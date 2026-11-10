// A choir group's next rehearsal (contract/api.md, groups.show `rehearsal`): the date the director set, and the server's
// `next` and `previous`, rolled forward on this device by whole weeks when a weekly one has passed here (the server keeps
// a day until it's over everywhere). Plain local dates (YYYY-MM-DD), never a time: what matters is the day. Nothing is sent
// or reminded from here; it's information on the group's page and the singer's Home (the coordinator's call, 2026-11-08).

const DAY = 86400000;

/** A YYYY-MM-DD as a local date at midnight, or null. */
export function localDate(text) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text ?? '');
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
}

/** A local date as YYYY-MM-DD. */
export function isoDate(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Whole days from `today` (local midnight) to `date` (both local dates): 0 today, 1 tomorrow. */
export function daysUntil(date, today = new Date()) {
    const start = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    return Math.round((date - start) / DAY);
}

/**
 * The next rehearsal on or after today here, and the one before it: { next, previous } as local dates (either may be
 * null). A weekly one past here steps on by weeks; a one-off past here has no next.
 */
export function rehearsalDates(rehearsal, today = new Date()) {
    if (!rehearsal) return { next: null, previous: null };
    let next = localDate(rehearsal.next ?? rehearsal.date);
    let previous = localDate(rehearsal.previous);
    if (next && daysUntil(next, today) < 0) {
        if (!rehearsal.weekly) return { next: null, previous: next };
        while (daysUntil(next, today) < 0) {
            previous = next;
            next = new Date(next.getFullYear(), next.getMonth(), next.getDate() + 7);
        }
    }
    return { next, previous };
}
