// Human-only counts (Hillel, 2026-11-14: "filter out this junk app usage data from the views so I dont get my hopes
// up"; ~74 app arrivals tagged ref=lesson-page in one morning were a crawler following the lesson pages' Start links,
// guests who did nothing):
// - A robot (navigator.webdriver: Puppeteer, Playwright, Selenium, a crawler running our JavaScript, our own headless
//   probes) sends no first-party count or live beat at all.
// - Everyone else's counts wait in memory until the page's first real interaction (a press, a key, a touch, the wheel:
//   never focus, load or a scroll the page made itself), then go. A page closed untouched sends nothing.

let check = () => navigator.webdriver === true;
let human = !!globalThis.__stackapellaPerson; // the flows' browser, as a person who has already interacted (tests/flows/flow.py)
let held = [];

/** True when automation drives this browser: its counts and beats stay unsent. */
export const automated = () => check();

const KINDS = ['pointerdown', 'keydown', 'touchstart', 'wheel'];

/** The first real interaction: whatever was held goes, once. */
function interacted() {
    if (human) return;
    human = true;
    for (const kind of KINDS) removeEventListener(kind, onInput, { capture: true });
    const now = held;
    held = [];
    for (const send of now) send();
}

function onInput(event) {
    if (event.isTrusted) interacted();
}
if (!human) for (const kind of KINDS) addEventListener(kind, onInput, { capture: true, passive: true });

/** Sends with `send` now if a person has interacted, after their first interaction if not, never for a robot. */
export function whenHuman(send) {
    if (automated()) return;
    if (human) send();
    else held.push(send);
}

/** Tests only: what automated() answers (the counts' own tests send from a headless browser on purpose). */
export function useAutomated(answer) {
    check = answer;
}

/** Tests only: a page someone has (true) or hasn't (false) interacted with yet; held sends are dropped either way. */
export function useHuman(state) {
    human = state;
    held = [];
}

/** Tests only: the first real interaction, as a trusted press would be. */
export const interactNow = interacted;
