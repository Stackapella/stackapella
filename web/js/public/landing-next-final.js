// /next-final (server/resources/views/pages/next-final.blade.php): a stage that fills the screen for the first five
// scenes, the song's four lanes on it, then a page that scrolls, and a dock that stays. The sound is the song page's
// player, js/public/mixer.js, unchanged: this module only reads what it says (data-playing and --p on the page's root,
// the lanes' data-solo, its mixer-solo event) and presses its own buttons (the dock's disc, a lane). So the page has
// one clock, the player's: where the song is comes from its --p each frame, and nothing here plays, seeks, pauses or
// mutes anything. Scrolling changes the scene and never touches the sound.
//
// What it sets, for css/landing-next-final.css:
// - on <html>: the class lnf-page (the page rests on a scene of the stage as it scrolls);
// - on .lnf: data-scene (0 to 4, from the scroll: the stage's scenes), data-tail (past the stage, on the page that
//   scrolls), data-alone (the voice heard alone, while one is), data-top ("small" on the way down, gone on the way up),
//   --lnf-t, the song's place in beats (the player's while it plays; while it doesn't, a still pose for the scene, so
//   each story scene shows its moment) and --lnf-page, the sung line's first beat (the still page shows a line at a
//   time);
// - on a gem: data-lit while its note sounds, data-sung once it has; on a word of the sung line: data-now, data-done.
// It shows the line being sung (the others hidden), keeps the next one under it, and tells a screen reader, politely,
// when the song starts and stops and which voice is heard alone.
// The dock's four voices each press their lane, so a voice is heard alone from any scene; the Hear scene's button does
// the same, and it and the "Learn the ... part" links name the voice last heard alone.
// The lanes are one group for the keyboard: Tab reaches it once, the arrows move between the voices, Space or Enter
// hears one alone (the lane is a button). With reduced motion or no script the stage's scenes are five still panels (the
// CSS's base layout); this module then still lights the gems and the words, in place.
// It also keeps the top line's own switches (light or dark, as the rest of the site stores it) and sends the page's one
// anonymous count. Tested in tests/landing-next-final.test.js and tests/flows/next_final.py.
import { countAnon } from './count.js';

const root = document.querySelector('[data-lnf]');

/** How many scenes the stage holds; the rest of the page scrolls. */
export const PINNED = 5;

/** The stage's scene a scroll position shows: one screen each, the nearest, the last one from there on. Pure. */
export function sceneAt(scrollY, screen, count = PINNED) {
    if (!(screen > 0)) return 0;
    return Math.max(0, Math.min(count - 1, Math.round(scrollY / screen)));
}

/** Whether a scroll position is past the stage, on the page that scrolls (the stage more than half gone). Pure. */
export function inTail(scrollY, screen, count = PINNED) {
    return screen > 0 && scrollY > (count - 0.5) * screen;
}

/** Where a still scene poses the song (beats) while nothing plays: each story scene's own moment. Pure. */
export function poseOf(scene) {
    return [0, 5.2, 10.5, 17, 30][scene] ?? 0;
}

/** The sung line a beat falls in: the index of the phrase holding it, else the last one begun, else 0. Pure. */
export function phraseAt(phrases, beat) {
    let at = 0;
    phrases.forEach((phrase, i) => {
        if (beat >= phrase.from) at = i;
    });
    return at;
}

/** A note's state at a beat: "lit" while it sounds, "sung" once it has, else "". Pure. */
export function noteState(note, beat) {
    if (!(beat > 0)) return '';
    if (beat >= note.b + note.l) return 'sung';
    return beat >= note.b ? 'lit' : '';
}

if (root) {
    setUp();
    chrome();
}

function setUp() {
    // The page itself rests on a scene of the stage (css: scroll-snap, only where the stage is pinned).
    document.documentElement.classList.add('lnf-page');
    const beats = Number(root.dataset.beats) || 1;
    const scenes = [...root.querySelectorAll('[data-lnf-stage] [data-s]')];
    const still = matchMedia('(prefers-reduced-motion: reduce)');
    const gems = [...root.querySelectorAll('.lnf-g')].map((el) => ({ el, b: Number(el.dataset.b), l: Number(el.dataset.l), state: '' }));
    const lines = [...root.querySelectorAll('.lnf-line')].map((el) => ({
        el,
        from: Number(el.dataset.from),
        to: Number(el.dataset.to),
        words: [...el.querySelectorAll('[data-b]')].map((word) => ({ el: word, b: Number(word.dataset.b) })),
    }));
    const next = root.querySelector('[data-lnf-next]');
    const live = root.querySelector('[data-lnf-live]');
    const dots = [...root.querySelectorAll('[data-lnf-dots] a')];
    let scene = 0;
    let shownLine = -1;
    let frame = 0;

    // ---- the song's place, drawn -------------------------------------------------------------------------------
    function draw(beat) {
        root.style.setProperty('--lnf-t', String(beat));
        for (const gem of gems) {
            const state = noteState(gem, beat);
            if (state === gem.state) continue;
            gem.state = state;
            gem.el.toggleAttribute('data-lit', state === 'lit');
            gem.el.toggleAttribute('data-sung', state === 'sung');
        }
        if (!lines.length) return;
        const at = phraseAt(lines, beat);
        if (at !== shownLine) {
            shownLine = at;
            lines.forEach((line, i) => (line.el.hidden = i !== at));
            if (next) next.textContent = lines[at + 1]?.words.map((word) => word.el.textContent).join(' ') ?? '';
            root.style.setProperty('--lnf-page', String(lines[at].from));
        }
        const line = lines[at];
        line.words.forEach((word, i) => {
            const until = line.words[i + 1]?.b ?? line.to;
            word.el.toggleAttribute('data-now', beat > 0 && beat >= word.b && beat < until);
            word.el.toggleAttribute('data-done', beat > 0 && beat >= until);
        });
    }

    const playing = () => root.hasAttribute('data-playing');
    // The player's own place, 0 to 1 of the song (it writes --p on its root each frame, from its one clock).
    const place = () => (Number.parseFloat(root.style.getPropertyValue('--p')) || 0) * beats;
    // Stopped, the song is back at its start (the player's Stop is a stop): the lanes show the scene's still pose.
    const rest = () => draw(still.matches ? 0 : poseOf(scene));

    function tick() {
        if (!playing()) return;
        draw(place());
        frame = requestAnimationFrame(tick);
    }

    // The player says when it starts and stops (its attribute): the lanes follow from the next frame.
    new MutationObserver(() => {
        cancelAnimationFrame(frame);
        if (playing()) {
            frame = requestAnimationFrame(tick);
            say(live?.dataset.playing);
        } else {
            rest();
            if (root.hasAttribute('data-played')) say(live?.dataset.stopped);
        }
    }).observe(root, { attributes: true, attributeFilter: ['data-playing'] });

    function say(text) {
        if (!live || !text) return;
        live.textContent = '';
        requestAnimationFrame(() => (live.textContent = text));
    }

    // ---- the voices --------------------------------------------------------------------------------------------
    const lanes = new Map([...root.querySelectorAll('[data-lnf-lanes] [data-voice]')].map((lane) => [lane.dataset.voice, {
        name: lane.dataset.name,
        hit: lane.querySelector('[data-hear-alone]'),
        learn: lane.querySelector('[data-lnf-mine]')?.getAttribute('href') ?? null,
    }]));
    const voices = [...root.querySelectorAll('[data-lnf-voice]')];
    const hear = root.querySelector('[data-lnf-hear]');
    const learns = [...root.querySelectorAll('[data-lnf-learn]')];
    // The voice the Hear scene's button and the Learn links name: the one last heard alone, the first lane's until then.
    let named = lanes.keys().next().value ?? null;

    function name(key, alone) {
        const lane = lanes.get(key);
        if (!lane) return;
        if (hear) {
            hear.textContent = alone ? hear.dataset.labelBack : hear.dataset.label.replace(':part', lane.name);
            hear.setAttribute('aria-pressed', String(alone));
        }
        for (const link of learns) {
            if (lane.learn) link.setAttribute('href', lane.learn);
            const words = link.querySelector('span') ?? link;
            words.textContent = link.dataset.label.replace(':part', lane.name);
        }
    }

    // Which voice is heard alone, as the player tells it.
    root.addEventListener('mixer-solo', (event) => {
        const key = event.detail?.key ?? null;
        // Said to the page too: a voice really heard alone outranks a story scene's picture of the lanes.
        if (key) root.dataset.alone = key;
        else delete root.dataset.alone;
        for (const voice of voices) voice.setAttribute('aria-pressed', String(voice.dataset.lnfVoice === key));
        if (key) named = key;
        if (named) name(named, key !== null);
        const heard = key ? lanes.get(key)?.name : null;
        say(heard ? live?.dataset.alone?.replace(':part', heard) : live?.dataset.all);
    });

    // The dock's voices and the Hear scene's button press a lane's own button: the player hears that, nothing else.
    for (const voice of voices) voice.addEventListener('click', () => lanes.get(voice.dataset.lnfVoice)?.hit?.click());
    hear?.addEventListener('click', () => lanes.get(root.dataset.alone ?? named)?.hit?.click());

    // The first scene's big button is the dock's disc, pressed (still inside the press, so the sound may start).
    const disc = root.querySelector('[data-play]');
    for (const button of root.querySelectorAll('[data-lnf-play]')) {
        button.addEventListener('pointerdown', () => disc?.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })));
        button.addEventListener('click', () => disc?.click());
    }

    // ---- the lanes, one group for the keyboard -----------------------------------------------------------------
    const hits = [...root.querySelectorAll('[data-lnf-lanes] [data-hear-alone]')];
    // A lane is heard alone by its button and nothing else: the player also takes any click that reaches a voice's own
    // element as "switch this voice" (its tune players' buttons), which would undo the button's press, so a click stops
    // at the button, and one elsewhere in the lane (its link into the app) never reaches the lane.
    for (const lane of root.querySelectorAll('[data-lnf-lanes] [data-voice]')) {
        lane.addEventListener('click', (event) => {
            if (!event.target.closest?.('[data-hear-alone]')) event.stopImmediatePropagation();
        }, true);
    }
    hits.forEach((hit, i) => {
        hit.tabIndex = i === 0 ? 0 : -1;
        hit.addEventListener('click', (event) => event.stopPropagation());
        hit.addEventListener('focus', () => hits.forEach((other) => (other.tabIndex = other === hit ? 0 : -1)));
        hit.addEventListener('keydown', (event) => {
            const to = { ArrowDown: i + 1, ArrowRight: i + 1, ArrowUp: i - 1, ArrowLeft: i - 1, Home: 0, End: hits.length - 1 }[event.key];
            if (to === undefined) return;
            event.preventDefault();
            hits[(to + hits.length) % hits.length].focus();
        });
    });

    // ---- the scenes, from the scroll ---------------------------------------------------------------------------
    const screen = () => window.innerHeight || 1;
    let lastY = window.scrollY;
    let queued = false;

    function show(to) {
        if (to === scene) return;
        scene = to;
        root.dataset.scene = String(to);
        dots.forEach((dot, i) => (i === to ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
        if (!playing()) rest();
    }

    function onScroll() {
        queued = false;
        const y = window.scrollY;
        if (!still.matches) show(sceneAt(y, screen()));
        // Past the stage: with nothing pinned that's past its five panels, wherever they end.
        const tail = still.matches ? (root.querySelector('[data-lnf-tail]')?.getBoundingClientRect().top ?? 1) < screen() / 2 : inTail(y, screen());
        root.toggleAttribute('data-tail', tail);
        // The top line: smaller on the way down, back on the way up (and always whole at the very top).
        if (y < 40 || y < lastY - 4) delete root.dataset.top;
        else if (y > lastY + 4) root.dataset.top = 'small';
        lastY = y;
    }
    addEventListener('scroll', () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(onScroll);
    }, { passive: true });
    addEventListener('resize', onScroll, { passive: true });

    // Focus that lands in a scene of the stage off the screen brings its scene (a pinned scene is only seen when it's
    // the one). The page below scrolls to what's focused by itself.
    scenes.forEach((section, i) => {
        section.addEventListener('focusin', () => {
            if (still.matches || (i === scene && !root.hasAttribute('data-tail'))) return;
            window.scrollTo({ top: i * screen(), behavior: 'auto' });
        });
    });
    still.addEventListener('change', () => {
        show(still.matches ? 0 : sceneAt(window.scrollY, screen()));
        if (!playing()) rest();
    });

    onScroll();
    rest();
    root.dataset.ready = '';
}

// The top line's own switches, and the page's count.
function chrome() {
    // Light or dark, the same stored choice as the rest of the site (js/theme-boot.js); this page is calm in either.
    for (const button of root.querySelectorAll('[data-lnf-theme]')) {
        button.addEventListener('click', () => {
            const light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
            const game = document.documentElement.dataset.skin !== 'serious';
            window.stackapellaTheme?.choose(game ? { gameMode: light ? 'dark' : 'light' } : { mode: light ? 'dark' : 'light' });
        });
    }
    // This page load's one anonymous count, its own kind (never a site section), only for a person (js/automated.js).
    const visit = document.querySelector('[data-lnf-visit]');
    if (visit) {
        const { kind } = JSON.parse(visit.textContent);
        if (typeof kind === 'string') countAnon(kind);
    }
}
