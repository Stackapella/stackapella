// Two devices' rehearsal plans as one (js/rehearsal-plan.js; the contract's merge rule), on its own with no imports so the
// progress merge (course.js, on the first screens) carries no more than it needs.

export const MAX_PLANS = 20;

/**
 * Two devices' plans as one (the contract's rule): by song and part, the newer of madeAt and removedAt wins the plan; a
 * day's doneAt is kept if either side has it (days by date); readyAt the later of the two.
 */
export function mergeRehearsals(mine = [], theirs = []) {
    const key = (p) => `${p.songId}:${p.partKey}`;
    const stamp = (p) => Math.max(p.madeAt ?? 0, p.removedAt ?? 0);
    const out = new Map(theirs.map((p) => [key(p), p]));
    for (const plan of mine) {
        const other = out.get(key(plan));
        if (!other) {
            out.set(key(plan), plan);
            continue;
        }
        const [win, lose] = stamp(plan) >= stamp(other) ? [plan, other] : [other, plan];
        const doneOf = new Map((lose.days ?? []).filter((d) => d.doneAt).map((d) => [d.date, d.doneAt]));
        const days = (win.days ?? []).map((d) => (d.doneAt || !doneOf.has(d.date) ? d : { ...d, doneAt: doneOf.get(d.date) }));
        const ready = Math.max(win.readyAt ?? 0, lose.readyAt ?? 0);
        out.set(key(plan), { ...win, days, ...(ready ? { readyAt: ready } : {}) });
    }
    return [...out.values()].sort((a, b) => stamp(b) - stamp(a)).slice(0, MAX_PLANS);
}
