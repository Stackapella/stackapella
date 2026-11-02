// The Perfect your pitch course page's demos (server/resources/views/site/courses/perfect-your-pitch.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js:
// - Bullseye, lesson 1's game: the guide sings five notes, one by one, and the visitor echoes each.
//   The mic is opt-in: asked for only when "Try it with your voice" is tapped (listen.js), its pitch
//   worked out on the device, nothing recorded, sent or counted, and closed when the round ends. Each
//   echo lands as a dart on the board, as far from the centre as it was off (above is sharp, below
//   flat), labelled in cents, then "4 of 5 landed within 25 cents".
// - Pull it in, the way without a mic (none, refused, or a browser that can't): the guide's note
//   sounds a little off against a steady one, and a slider pulls it in until the dart lands.
// - Tuning by the beats: two voices 3 Hz apart, beating three times a second, then in tune and still.
// The words are the view's (data attributes); a dart is placed as the lesson places it
// (docs/courses/perfect-pitch.md, "The fun"): the gold within 10 cents, the inner ring 25, the outer
// 50 and the rim a semitone, further pinned to the rim, the darts fanned sideways so each shows.

import { audio, hz, reducedMotion, voice } from './voices.js';
import { dart, listen } from './listen.js';
import { onPicked, preferredMic, savedMic, showPicker } from './mic-pick.js';
import { finish, label, play, rest, sounding, unlock, when } from './demo-player.js';

const page = document.querySelector('[data-pt]');
const board = page?.querySelector('[data-pt-board]');
const say = page?.querySelector('[data-pt-say]');
const words = page?.querySelector('[data-pt-demo]')?.dataset ?? {};
const SVG = 'http://www.w3.org/2000/svg';
const CX = 170;
const CY = 190;

// Cents off to distance from the centre, ring by ring (the gold, the inner ring, the outer, the rim).
const RINGS = [[0, 0], [10, 30], [25, 66], [50, 104], [100, 150]];
function reach(cents) {
    const off = Math.min(Math.abs(cents), 100);
    for (let i = 1; i < RINGS.length; i++) {
        const [c0, r0] = RINGS[i - 1];
        const [c1, r1] = RINGS[i];
        if (off <= c1) return r0 + ((off - c0) / (c1 - c0)) * (r1 - r0);
    }
    return 150;
}

// Where a dart lands: above the centre when sharp, below when flat, fanned sideways by its place.
function spot(cents, slot, slots) {
    const tilt = slots > 1 ? ((slot - (slots - 1) / 2) * 22 * Math.PI) / 180 : 0;
    const r = reach(cents);
    const up = cents > 0 ? -1 : 1;
    if (Math.round(cents) === 0) return { x: CX + (slot - (slots - 1) / 2) * 7, y: CY };
    return { x: CX + r * Math.sin(tilt), y: CY + up * r * Math.cos(tilt) };
}

const signed = (cents) => (cents > 0 ? `+${cents}` : cents < 0 ? `−${-cents}` : '0');
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.replaceAll(`:${key}`, String(value)), text ?? '');
const offWords = (cents) => (cents === 0 ? words.deadOn : fill(cents > 0 ? words.sharp : words.flat, { n: Math.abs(cents) }));

function clearDarts() {
    board?.querySelectorAll('[data-pt-dart]').forEach((d) => d.remove());
}

// A dart: a pin and its cents, gold within 10, landed within 25, glass beyond.
function drawDart(cents, slot, slots, { live = false } = {}) {
    const { x, y } = spot(cents, slot, slots);
    let g = live ? board.querySelector('[data-pt-dart="live"]') : null;
    if (!g) {
        g = document.createElementNS(SVG, 'g');
        g.setAttribute('data-pt-dart', live ? 'live' : '');
        g.append(document.createElementNS(SVG, 'circle'), document.createElementNS(SVG, 'text'));
        board.querySelector('[data-pt-darts]').append(g);
    }
    const off = Math.abs(cents);
    g.setAttribute('class', `cp-pt2-dart ${off <= 10 ? 'cp-pt2-dart-gold' : off <= 25 ? 'cp-pt2-dart-in' : 'cp-pt2-dart-out'}`);
    g.setAttribute('transform', `translate(${x.toFixed(1)} ${y.toFixed(1)})`);
    const [pin, text] = g.children;
    pin.setAttribute('r', '7');
    text.setAttribute('y', y < CY ? '-13' : '22');
    text.textContent = signed(Math.round(cents));
    return g;
}

function tell(text) {
    if (say) say.textContent = text;
}

// ---- Bullseye, with the visitor's voice -------------------------------------------------------------
// Five notes within a fourth of middle C, each a different one; any octave counts (the lesson folds a
// voice an octave down onto the note). The guide sings for GUIDE seconds, a short gap lets its fade
// clear the mic, then the visitor's TURN; the next note follows.
const NOTES = [60, 64, 62, 57, 65];
const GUIDE = 1;
const GAP = 0.35;
const TURN = 2.2;
const STEP = GUIDE + GAP + TURN + 0.3;

const micButton = page?.querySelector('[data-pt-mic]');
const pull = page?.querySelector('[data-pt-pull]');
const micPick = page?.querySelector('[data-pt-mic-pick]');
let asking = false;
let liveMic = null; // the open mic, while a round runs, so the picker can move it
if (micPick) onPicked(micPick, (id) => liveMic?.use(id).catch((error) => console.warn('That mic could not be opened', error)));

micButton?.addEventListener('click', async () => {
    if (asking) return;
    if (sounding(micButton)) {
        finish();
        return;
    }
    finish();
    page.querySelector('[data-pt-demo]')?.removeAttribute('data-pulling');
    // Made and resumed inside the tap, as Safari asks; the iPhone's session set for the mic.
    unlock('play-and-record');
    asking = true;
    micButton.setAttribute('aria-busy', 'true');
    tell(words.asking);
    // From a link to #bullseye the board sits above the screen on a phone: bring the whole demo up,
    // so the darts land where they can be seen.
    const box = page.querySelector('[data-pt-demo]');
    if (box.getBoundingClientRect().top < 0) box.scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' });
    let mic = null;
    let onFrame = () => {};
    try {
        mic = await listen({ onFrame: (frame) => onFrame(frame), deviceId: savedMic() });
        // Nothing chosen on this site yet: the mic chosen last here or in the app, by name.
        const named = await preferredMic(mic.deviceId()).catch((error) => { console.warn('The mics could not be listed', error); return null; });
        if (named) await mic.use(named).catch((error) => console.warn('The remembered mic could not be opened', error));
        liveMic = mic;
        if (micPick) await showPicker(micPick, mic.deviceId()).catch((error) => console.warn('The mic picker could not be filled', error));
    } catch (error) {
        unlock('playback');
        tell('');
        openPull(error?.reason ?? 'unsupported');
        return;
    } finally {
        asking = false;
        micButton.removeAttribute('aria-busy');
    }
    clearDarts();
    play(micButton, ({ live }) => round(mic, (fn) => { onFrame = fn; }, live));
});

function round(mic, follow, live) {
    const c = audio();
    const t0 = c.currentTime + 0.4;
    const landings = [];
    let turn = null; // the note being echoed, while it's the visitor's turn
    follow((frame) => {
        if (!live() || turn === null || !frame.clear || frame.cents === null) return;
        // The live pin: where the voice is now, folded to the nearest octave.
        const cents = ((((frame.cents + 600) % 1200) + 1200) % 1200) - 600;
        drawDart(Math.max(-100, Math.min(100, cents)), turn, NOTES.length, { live: true });
    });
    const voices = NOTES.map((midi, i) => {
        const at = t0 + i * STEP;
        const guide = voice(hz(midi), { at, level: 0.16 });
        guide.stop(at + GUIDE - c.currentTime);
        when(at).then(() => live() && tell(fill(words.listen, { n: i + 1, total: NOTES.length })));
        when(at + GUIDE + GAP).then(() => {
            if (!live()) return;
            mic.target(midi);
            turn = i;
            tell(words.turn);
        });
        when(at + GUIDE + GAP + TURN).then(() => {
            if (!live()) return;
            turn = null;
            board.querySelector('[data-pt-dart="live"]')?.remove();
            const from = at + GUIDE + GAP;
            const landed = dart(mic.since(from).filter((f) => f.at <= from + TURN), midi);
            landings.push(landed);
            if (landed) drawDart(landed.cents, i, NOTES.length);
            tell(landed ? offWords(landed.cents) : words.unheard);
            if (i === NOTES.length - 1) sum(landings);
        });
        return guide;
    });
    return {
        voices: [...voices, rest()],
        length: t0 + NOTES.length * STEP - c.currentTime,
        done: () => {
            mic.stop();
            liveMic = null;
            unlock('playback');
            board.querySelector('[data-pt-dart="live"]')?.remove();
        },
    };
}

function sum(landings) {
    const heard = landings.filter(Boolean);
    if (!heard.length) {
        tell(words.none);
        return;
    }
    tell(fill(words.summary, { landed: heard.filter((l) => l.landed).length, total: heard.length }));
}

// ---- Pull it in, by ear ------------------------------------------------------------------------------
// A steady A3 and the guide on the same note, a little off; the slider glides the guide on the audio
// clock. By ear means by ear (Hillel, 2026-10-31): the target, the darts and any cents stay hidden
// while you pull, and where the guide is in tune on the slider is drawn each round (its middle gives
// nothing away), the guide starting 25 to 60 cents off either way. Held within the gold (10 cents)
// for 1.5 seconds, measured on the audio clock, it's a win: the sound stops, the target comes back
// with where you landed, and Play again.
const slider = page?.querySelector('[data-pt-slider]');
const pullPlay = page?.querySelector('[data-pt-pull-play]');
const pullSay = page?.querySelector('[data-pt-pull-say]');
const winBox = page?.querySelector('[data-pt-win]');
const demoBox = page?.querySelector('[data-pt-demo]');
const pullWords = pull?.dataset ?? {};
const GOLD = 10;
const HOLD = 1.5;
let guideNote = null;
let match = 0; // the slider's value where the guide is in tune, this round
let goldSince = null; // when, on the audio clock, the guide came into the gold

const pullCents = () => Number(slider.value) - match;

function newRound() {
    const draw = crypto.getRandomValues(new Uint16Array(3));
    match = -40 + (draw[0] % 81);
    slider.value = String(match + (draw[1] % 2 ? 1 : -1) * (25 + (draw[2] % 36)));
    slider.dataset.match = String(match); // for the flows; the page never shows it
    goldSince = null;
    winBox.hidden = true;
    pullSay.textContent = pullWords.listen;
    clearDarts();
    demoBox.dataset.pulling = '';
}

function won() {
    const cents = pullCents();
    finish();
    delete demoBox.dataset.pulling;
    clearDarts();
    drawDart(cents, 0, 1);
    pullSay.textContent = '';
    page.querySelector('[data-pt-win-say]').textContent = fill(pullWords.won, { off: offWords(cents) });
    winBox.hidden = false;
    winBox.querySelector('[data-pt-again]')?.focus();
}

// In the gold and held there, while the two notes sound: the win, timed on the audio clock.
function hold() {
    if (!sounding(pullPlay) || Math.abs(pullCents()) > GOLD) {
        goldSince = null;
        return;
    }
    if (goldSince !== null) return;
    const since = audio().currentTime;
    goldSince = since;
    when(since + HOLD).then(() => {
        if (goldSince === since && sounding(pullPlay) && Math.abs(pullCents()) <= GOLD) won();
    });
}

function openPull(reason) {
    if (!pull) return;
    const why = page.querySelector('[data-pt-why]');
    const whys = { denied: words.whyDenied, 'no-mic': words.whyNoMic, unsupported: words.whyUnsupported };
    if (why) why.textContent = reason ? (whys[reason] ?? whys.unsupported) : '';
    pull.hidden = false;
    newRound();
    if (reason !== null) pull.querySelector('h3')?.focus();
}

page?.querySelector('[data-pt-ear]')?.addEventListener('click', () => {
    finish();
    openPull(null);
});

slider?.addEventListener('input', () => {
    guideNote?.tune(pullCents(), 0.08);
    hold();
});

function sound() {
    const A3 = hz(57);
    const steady = voice(A3, { level: 0.12 });
    guideNote = voice(A3, { cents: pullCents(), level: 0.12 });
    return { voices: [steady, guideNote], length: 30, done: () => { guideNote = null; goldSince = null; } };
}

pullPlay?.addEventListener('click', () => {
    if (!winBox.hidden) newRound();
    play(pullPlay, sound);
    hold();
});

page?.querySelector('[data-pt-again]')?.addEventListener('click', () => {
    newRound();
    play(pullPlay, sound);
    pullPlay.focus();
});

// ---- Tuning by the beats ------------------------------------------------------------------------------
// Two voices on A3, one 3 Hz above (23.4 cents at 220 Hz): three beats a second for 3.4 seconds, then
// the upper glides down onto the other and they ring still. The figures light with the sound.
const beats = page?.querySelector('[data-pt-beats]');
const beatPlay = page?.querySelector('[data-pt-beats-play]');
beatPlay?.addEventListener('click', () => play(beatPlay, ({ live }) => {
    const c = audio();
    const at = c.currentTime + 0.05;
    const apart = 1200 * Math.log2(223 / 220);
    const low = voice(220, { at, level: 0.13 });
    const high = voice(220, { at, cents: apart, level: 0.13 });
    high.tune(0, 0.5, at + 3.4);
    beats.dataset.now = 'apart';
    when(at + 3.6).then(() => {
        if (live()) beats.dataset.now = 'there';
    });
    return { voices: [low, high], length: 6.4, done: () => { delete beats.dataset.now; } };
}));

// ---- The sources: open on a big screen, a tap away on a phone. ------------------------------------------
const wide = matchMedia('(min-width: 900px)');
function openUnits() {
    if (wide.matches) page.querySelectorAll('[data-pt-open]').forEach((d) => { d.open = true; });
}
if (page) {
    openUnits();
    wide.addEventListener('change', openUnits);
}

// The buttons start with their own words; the player swaps them while a demo sounds.
page?.querySelectorAll('[data-play]').forEach((b) => label(b, false));
