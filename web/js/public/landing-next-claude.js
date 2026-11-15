// /next-claude (server/resources/views/pages/next-claude.blade.php): one stage that fills the screen, the song's four
// lanes on it, and nine scenes that scroll over it. The sound is the song page's player, js/public/mixer.js, unchanged:
// this module only reads what it says (data-playing and --p on the stage, the lanes' data-solo, its mixer-solo event) and
// draws from it. So the page has one clock, the player's: where the song is comes from its --p each frame, and nothing
// here plays, seeks, pauses or mutes anything. Scrolling changes the scene and never touches the sound.
//
// What it sets, for css/input.css's .s-lncl rules:
// - on <html>: the class s-lncl-page (the page rests on a scene as it scrolls);
// - on .s-lncl: data-scene (0 to 8, from the scroll), data-alone (the voice heard alone, while one is), data-top ("small" on the way down, gone on the way up);
// - on the stage: --lncl-t, the song's place in beats (the player's while it plays; while it doesn't, a still pose for
//   the scene, so each story scene shows its moment), --lncl-page, the sung line's first beat (the still page shows a
//   line at a time), and --beats;
// - on a gem: data-lit while its note sounds, data-sung once it has; on a word of the sung line: data-now, data-done.
// It shows the line being sung (the others hidden), keeps the next one under it, and tells a screen reader, politely,
// when the song starts and stops and which voice is heard alone.
// The lanes are one group for the keyboard: Tab reaches it once, the arrows move between the voices, Space or Enter
// hears one alone (the lane is a button). With reduced motion or no script the scenes are nine still panels (the CSS's
// base layout); this module then still lights the gems and the words, in place.
// Tested in tests/flows/next_claude.py.

const root = document.querySelector('[data-lncl]');
const stage = root?.querySelector('[data-lncl-stage]');

/** The scene a scroll position shows: one screen each, the nearest. Pure (the tests check it). */
export function sceneAt(scrollY, screen, count) {
    if (!(screen > 0)) return 0;
    return Math.max(0, Math.min(count - 1, Math.round(scrollY / screen)));
}

/** Where a still scene poses the song (beats) while nothing plays: each story scene's own moment. Pure. */
export function poseOf(scene) {
    return [0, 5.2, 10.5, 17, 30, 0, 0, 0, 0][scene] ?? 0;
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

if (root && stage) setUp();

function setUp() {
    // The page itself rests on a scene (css: scroll-snap, only where the scenes are pinned).
    document.documentElement.classList.add('s-lncl-page');
    const beats = Number(stage.dataset.beats) || 1;
    stage.style.setProperty('--beats', String(beats));
    const scenes = [...root.querySelectorAll('[data-s]')];
    const count = scenes.length;
    const still = matchMedia('(prefers-reduced-motion: reduce)');
    const gems = [...stage.querySelectorAll('.s-lncl-g')].map((el) => ({ el, b: Number(el.dataset.b), l: Number(el.dataset.l), state: '' }));
    const lines = [...stage.querySelectorAll('.s-lncl-line')].map((el) => ({
        el,
        from: Number(el.dataset.from),
        to: Number(el.dataset.to),
        words: [...el.querySelectorAll('[data-b]')].map((word) => ({ el: word, b: Number(word.dataset.b) })),
    }));
    const next = stage.querySelector('[data-lncl-next]');
    const live = stage.querySelector('[data-lncl-live]');
    const dots = [...root.querySelectorAll('[data-lncl-dots] a')];
    let scene = 0;
    let shownLine = -1;
    let frame = 0;

    // ---- the song's place, drawn -------------------------------------------------------------------------------
    function draw(beat) {
        stage.style.setProperty('--lncl-t', String(beat));
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
            stage.style.setProperty('--lncl-page', String(lines[at].from));
        }
        const line = lines[at];
        line.words.forEach((word, i) => {
            const until = line.words[i + 1]?.b ?? line.to;
            word.el.toggleAttribute('data-now', beat > 0 && beat >= word.b && beat < until);
            word.el.toggleAttribute('data-done', beat > 0 && beat >= until);
        });
    }

    const playing = () => stage.hasAttribute('data-playing');
    // The player's own place, 0 to 1 of the song (it writes --p on its root each frame, from its one clock).
    const place = () => (Number.parseFloat(stage.style.getPropertyValue('--p')) || 0) * beats;
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
            if (stage.hasAttribute('data-played')) say(live?.dataset.stopped);
        }
    }).observe(stage, { attributes: true, attributeFilter: ['data-playing'] });

    function say(text) {
        if (!live || !text) return;
        live.textContent = '';
        requestAnimationFrame(() => (live.textContent = text));
    }

    // Which voice is heard alone, as the player tells it.
    stage.addEventListener('mixer-solo', (event) => {
        const key = event.detail?.key ?? null;
        // Said to the page too: a voice really heard alone outranks a story scene's picture of the lanes.
        if (key) root.dataset.alone = key;
        else delete root.dataset.alone;
        const name = key ? stage.querySelector(`[data-voice="${CSS.escape(key)}"]`)?.dataset.name : null;
        say(name ? live?.dataset.alone?.replace(':part', name) : live?.dataset.all);
    });

    // The first scene's big button is the bar's disc, pressed (still inside the press, so the sound may start).
    const disc = stage.querySelector('[data-play]');
    for (const button of root.querySelectorAll('[data-lncl-play]')) {
        button.addEventListener('pointerdown', () => disc?.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })));
        button.addEventListener('click', () => disc?.click());
    }

    // ---- the lanes, one group for the keyboard -----------------------------------------------------------------
    const hits = [...stage.querySelectorAll('[data-lncl-lanes] [data-hear-alone]')];
    // A lane is heard alone by its button and nothing else: the player also takes any click that reaches a voice's own
    // element as "switch this voice" (its tune players' buttons), which would undo the button's press, so a click stops
    // at the button, and one elsewhere in the lane (its link into the app) never reaches the lane.
    for (const lane of stage.querySelectorAll('[data-lncl-lanes] [data-voice]')) {
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
        if (!still.matches) show(sceneAt(y, screen(), count));
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

    // Focus that lands in a scene off the screen brings its scene (a pinned scene is only seen when it's the one).
    scenes.forEach((section, i) => {
        section.addEventListener('focusin', () => {
            if (still.matches || i === scene) return;
            window.scrollTo({ top: i * screen(), behavior: 'auto' });
        });
    });
    still.addEventListener('change', () => {
        show(still.matches ? 0 : sceneAt(window.scrollY, screen(), count));
        if (!playing()) rest();
    });

    onScroll();
    rest();
    root.dataset.ready = '';
}
