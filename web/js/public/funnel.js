// The landing's part of the funnel (docs/onboarding-review.md, item 8; the backend's Funnel): while an
// experiment is enrolling, this device draws an arm at random (crypto.getRandomValues, kept in
// localStorage so it stays the same), and every Start link carries it as from=start&v=<experiment>-<arm>
// for the app to adopt. ?v= on the page only previews an arm: it goes on the links but is never counted. The
// first Start on this device sends one anonymous count, f_site_start, as a beacon to our own server
// (no ID, no cookie), and nothing at all while counts are off. Every page load sends site_visit too,
// once, and both carry the page's ?ref= as source, which the server groups (counts.sources) and never
// keeps as written.

import { OPERATIONS } from '../api.js';

const config = JSON.parse(document.querySelector('[data-funnel]')?.textContent ?? '{}');
const experiment = config.experiment;
const forced = new URLSearchParams(location.search).get('v');
const SENT = 'stackapella.funnel.siteStart';
const ref = new URLSearchParams(location.search).get('ref');
const source = ref && /^[a-z0-9_-]{1,40}$/i.test(ref) ? ref.toLowerCase() : null;

function read(key) {
    try {
        return localStorage.getItem(key);
    } catch {
        return null; // storage blocked: nothing is kept between visits
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // storage blocked: the arm is drawn again next visit, which only adds noise
    }
}

function drawArm() {
    if (!experiment?.enrol || !Array.isArray(experiment.arms) || !experiment.arms.length) return null;
    const key = `stackapella.funnel.arm.${experiment.id}`;
    let arm = read(key);
    if (!experiment.arms.includes(arm)) {
        arm = experiment.arms[crypto.getRandomValues(new Uint32Array(1))[0] % experiment.arms.length];
        write(key, arm);
    }
    return `${experiment.id}-${arm}`;
}

const variant = forced && /^[a-z0-9-]{1,40}$/.test(forced) ? forced : drawArm();
const starts = document.querySelectorAll('[data-funnel-start]');

// Every Start says it's the landing's (from=start): the app counts that arrival as f_arrive_start, and
// keeps this device's arm (v) as its own instead of drawing again, so the promise above stays true. A
// preview (?v= on this page) passes its arm on to look at, but never as the landing's Start, so the
// app neither counts it nor keeps it.
for (const link of starts) {
    const url = new URL(link.href);
    if (!forced) url.searchParams.set('from', 'start');
    if (variant) url.searchParams.set('v', variant);
    link.href = url.toString();
}

// A promise kept for every arm but one (data-variant-except="onb1-a": the control arm gets no chord
// moment in the app): rendered hidden while that experiment enrols, and shown once this device's arm
// is known not to be it, so the landing never promises what the app won't do.
for (const el of document.querySelectorAll('[data-variant-except]')) el.hidden = !variant || variant === el.dataset.variantExcept;

function beacon(body) {
    // As js/usage.js sends its counts: a beacon to counts.create, so leaving the page still sends.
    navigator.sendBeacon?.(OPERATIONS['counts.create'][1], new Blob([JSON.stringify(body)], { type: 'application/json' }));
}

if (config.counts) {
    beacon({ events: [{ kind: 'site_visit' }], signedIn: false, ...(source ? { source } : {}) });
    for (const link of starts) {
        link.addEventListener('click', () => {
            if (read(SENT)) return;
            write(SENT, '1');
            beacon({ events: [{ kind: 'f_site_start' }], signedIn: false, ...(variant && !forced ? { variant } : {}), ...(source ? { source } : {}) });
        });
    }
}
