// The singing test's score, scoring version 1 (docs/singing-test-v1.md; the contract's singingTests, agreed with
// ai-backend 2026-11-24). The server recomputes the same from the same numbers and its score is the one kept; this is
// the device's own, for a guest and for the result before the server answers. Shared examples pin both:
// contract/examples/singingTests.scoring.json. Provisional until calibration (scoring version 2).

/** Each event's weight, and its error's ceiling (full marks at or under) and floor (none at or over), in cents. */
export const EVENTS = {
    match: { weight: 45, ceiling: 10, floor: 150 },
    back: { weight: 40, ceiling: 15, floor: 200 },
    hold: { weight: 15, ceiling: 8, floor: 80 },
};

/** Bands from the pitch error p (cents); each edge goes to the worse band. The lowest shows words only, never a number. */
export const BANDS = [
    { band: 'precise', under: 15 },
    { band: 'inTune', under: 35 },
    { band: 'nearly', under: 60 },
    { band: 'closer', under: 100 },
    { band: 'finding', under: Infinity },
];

/** Fewer heard than these and the test is kept as practice ("Let's try that somewhere quieter"). */
export const ENOUGH = { match: 8, back: 4, hold: 1 };
/** A pattern counts as heard with this many of its four notes heard. */
export const PATTERN_HEARD = 3;

const heard = (x) => x !== null && x !== undefined && Number.isFinite(x);
const mean = (xs) => xs.reduce((s, x) => s + x, 0) / xs.length;

/** The share of an event's points for an error: 1 at the ceiling or under, 0 at the floor or over, linear between. */
export function share(error, { ceiling, floor }) {
    return Math.min(1, Math.max(0, (floor - error) / (floor - ceiling)));
}

/** Each event's error in cents from its heard items, or null when nothing was heard. */
export function errors({ match = [], back = [], hold = [] }) {
    const m = match.map((i) => i.centsOff).filter(heard).map(Math.abs);
    const b = back.flatMap((p) => p.notes.map((n) => n.centsOff)).filter(heard).map(Math.abs);
    const h = hold.filter((i) => heard(i.spreadCents) && heard(i.driftCents)).map((i) => i.spreadCents + Math.abs(i.driftCents) / 2);
    return { match: m.length ? mean(m) : null, back: b.length ? mean(b) : null, hold: h.length ? mean(h) : null, pitch: m.length + b.length ? mean([...m, ...b]) : null };
}

/** The band for a pitch error p (each edge goes to the worse band). */
export function bandOf(p) {
    if (!heard(p)) return null;
    return BANDS.find((b) => p < b.under).band;
}

/** Whether enough was heard to count: else it's practice. */
export function enoughHeard({ match = [], back = [], hold = [] }) {
    const m = match.filter((i) => heard(i.centsOff)).length;
    const b = back.filter((p) => p.notes.filter((n) => heard(n.centsOff)).length >= PATTERN_HEARD).length;
    const h = hold.filter((i) => heard(i.spreadCents) && heard(i.driftCents)).length;
    return m >= ENOUGH.match && b >= ENOUGH.back && h >= ENOUGH.hold;
}

/**
 * The test's score: `{ match, back, hold, total, band, practice, pitch }`. Event points are kept unrounded here and
 * summed for the total (rounded half up; all positive); `shown()` rounds them to 1 decimal for the screen. An event
 * with nothing heard is null.
 */
export function scoreTest(items) {
    const e = errors(items);
    const points = {};
    for (const [name, spec] of Object.entries(EVENTS)) points[name] = e[name] === null ? null : spec.weight * share(e[name], spec);
    const sum = Object.values(points).reduce((s, p) => s + (p ?? 0), 0);
    return { ...points, total: Math.floor(sum + 0.5), band: bandOf(e.pitch), practice: !enoughHeard(items), pitch: e.pitch };
}

/** An event's points as shown and sent: to 1 decimal (never summed again). */
export const shown = (points) => (points === null ? null : Math.round(points * 10) / 10);

/**
 * The result as the contract has it (schemas/singingTest.json, the server's own): `{ status, scores: { match, back,
 * hold } (to 1 decimal), total, band }`. A practice test (too little heard) has no total and no band; a warm-up is
 * scored but marked. `test`: `{ warmUp, match, back, hold }`.
 */
export function result(test) {
    const s = scoreTest(test);
    const status = s.practice ? 'practice' : test.warmUp ? 'warmup' : 'counted';
    return { status, scores: { match: shown(s.match), back: shown(s.back), hold: shown(s.hold) }, total: s.practice ? null : s.total, band: s.practice ? null : s.band };
}

/** "You've improved" only past these (provisional, until calibration): the total, or an event's error in cents. */
export const IMPROVED = { total: 8, cents: 15 };

/**
 * Against the last counted test of the same test version: 'improved' past IMPROVED, else 'same' (never "worse": a small
 * change can just be a different day). Null with nothing to compare.
 */
export function compare(previous, now) {
    if (!previous || previous.testVersion !== now.testVersion) return null;
    if (now.total - previous.total >= IMPROVED.total) return 'improved';
    const before = errors(previous.items ?? previous);
    const after = errors(now.items ?? now);
    const better = ['match', 'back', 'hold'].some((k) => heard(before[k]) && heard(after[k]) && before[k] - after[k] >= IMPROVED.cents);
    return better ? 'improved' : 'same';
}
