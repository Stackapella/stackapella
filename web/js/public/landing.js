// The landing, the site's home page (server/resources/views/pages/landing.blade.php): a page that scrolls like a page (Hillel,
// 2026-11-23), the song's four lanes once under the first screen, the story's steps each with its picture, and a small
// player that rises from the foot only while the song plays and its lanes have gone by. The sound is the song page's
// player, js/public/mixer.js: this module only reads what it says (data-playing on the page's root, its mixer-frame, the lanes'
// data-solo, its mixer-solo event) and presses its own buttons (the dock's disc, a lane). So the page has one clock,
// the player's: where the song is comes from each of its frames (mixer-frame, its place p), and nothing here plays, seeks, pauses or mutes
// anything. Scrolling never touches the sound, and the page never scrolls by itself.
//
// The song plays as the showreel plays it (Hillel, 2026-11-16: "the song plays twice, but the first time it plays each
// voice is added one by one ... only the first row is active and then, when the second voice comes in it, it's lit
// up"): the page's data asks the player for two passes and the beat each voice joins on, and this module draws the
// same beats (a lane's data-join) from the player's place: a lane yet to join waits, dim and still (data-wait), lights
// as its voice comes in (data-in, a band of light across it), and a line on the stage says who sings. A voice heard
// alone wins: nothing waits while one is.
// The strips of lanes between the sections down the page are pictures while nothing plays; while the song plays, each
// one in view is the song itself (Hillel: "as the song is playing we make the song visible here"): its real notes
// passing a playhead, from the same place, on the player's frames (the page runs one frame loop, the player's).
//
// What it sets, for css/landing.css:
// - on .lnf: data-tail (past the lanes and the story, on the plans and the finale), data-away (the lanes scrolled past:
//   the small player may rise), data-reveal (motion allowed: a section comes in as it enters, data-in on it), data-alone
//   (the voice heard alone, while one is), data-top ("small" on the way down, gone on the way up), data-scrolled (off
//   the very top: game mode's top line takes its solid ground),
//   --lnf-t, the song's place in beats (the player's while it plays, the start while it doesn't; written on the lanes'
//   tracks and the strips' bands, not on .lnf), --lnf-page, the sung line's first beat (with reduced motion the lanes
//   show a line at a time; on the tracks too), --lnf-run on the dock's rail, how far through both passes the play is (0 to 1), and --lnf-load with
//   data-load (the percent) while the voices' download can be told;
// - on a gem (the app's glass, css/glass.css's .gem-note, copied into landing.css): data-ring while its note sounds,
//   data-state="settled" once it has, --gem-g as the next note nears (the app's ramp, docs/glass-spec.md 4), and its
//   words' place, data-fit with --gem-word-px (js/glass.js's gemFit, once the font is in and on every resize);
//   on a word of the sung line: data-now, data-done;
// - on a lane (and its band in each strip): data-wait until its voice joins, data-in once it has;
// - on a strip (.lnf-floor): data-live while it shows the song (playing, in view, motion allowed).
// It shows the line being sung (the others hidden), keeps the next one under it, and tells a screen reader, politely,
// when the song starts and stops and which voice is heard alone.
// A voice is heard alone by its lane; the Hear scene's button presses a lane too, and it and the "Learn the ... part"
// links name the voice last heard alone.
// The lanes are one group for the keyboard: Tab reaches it once, the arrows move between the voices, Space or Enter
// hears one alone (the lane is a button). With reduced motion the lanes stand on the sung line while the gems and the
// words light in place; with no script the page is the same, still. The story's pictures are copies of the lanes,
// still, outside [data-lnf-lanes]: nothing here touches them.
// Only the recorded voices sing on this page (the player's `realOnly`): a press may wait for their download, and this
// module shows how far it is (the player's mixer-load event) on the Play button and in words, and a failure in words.
// The top line's Language and appearance is the site header's own (js/public/look-menu.js); Game mode's switch, the
// page's one anonymous count and Admin > Live's heartbeat are the site's own script's (js/public/site.js, with the footer). Tested in tests/landing.test.js and tests/flows/landing_page.py.

const root = document.querySelector('[data-lnf]');

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

/**
 * The glow of a note still to come (docs/glass-spec.md 4): none until the last min(0.45 s, 1 beat) before it, then
 * rising as t squared to the "next" level. `beatSeconds`: one beat's length. Pure.
 */
export function nextGlow(note, beat, beatSeconds, level = 0.3, lead = 0.45) {
    if (!(beat > 0) || !(beatSeconds > 0) || beat >= note.b) return 0;
    const span = Math.min(lead, beatSeconds) / beatSeconds;
    const t = 1 - (note.b - beat) / span;
    return t <= 0 ? 0 : level * t * t;
}

/**
 * Where a play in passes is: `place` (beats since the play began), `beats` (one time through), `passes`. The pass
 * (from 0, the last one from its start on) and the beat within it. Pure.
 */
export function buildAt(place, beats, passes = 1) {
    if (!(beats > 0) || !(place > 0)) return { pass: 0, beat: 0 };
    const pass = Math.max(0, Math.min(passes - 1, Math.floor(place / beats)));
    return { pass, beat: place - pass * beats };
}

/** How long before its beat a joining lane lights (beats): its light arrives with its voice, never after it. */
export const JOIN_LEAD = 0.15;

/** Whether a voice that joins on `join` (a beat of the first pass; 0 sings from the start) is still to come. Pure. */
export function waiting(join, pass, beat) {
    return join > 0 && pass === 0 && beat < join - JOIN_LEAD;
}

/**
 * What the stage's line says: "alone" while a voice is heard alone, "all" once every voice is in for good (from the
 * second pass, or a song where none joins), "first" while only the first sings, else "join", with `at`, the last
 * voice to have come in (`joins` are the voices' beats, in the lanes' order). Pure.
 */
export function captionOf(joins, pass, beat, alone = false) {
    if (alone) return { say: 'alone', at: -1 };
    if (pass > 0 || !joins.some((join) => join > 0)) return { say: 'all', at: -1 };
    let at = 0;
    joins.forEach((join, i) => {
        if (i > 0 && !waiting(join, pass, beat)) at = i;
    });
    return { say: at === 0 ? 'first' : 'join', at };
}

if (root) setUp();

function setUp() {
    const beats = Number(root.dataset.beats) || 1;
    const passes = Number(root.dataset.passes) || 1;
    const calm = matchMedia('(prefers-reduced-motion: reduce)');
    const gems = [...root.querySelectorAll('[data-lnf-lanes] .lnf-g')].map((el) => ({ el, b: Number(el.dataset.b), l: Number(el.dataset.l), state: '', glow: 0, word: el.textContent.trim() }));
    let song = {};
    try {
        song = JSON.parse(root.querySelector('[data-song]')?.textContent ?? '{}') ?? {};
    } catch {
        song = {};
    }
    const beatSeconds = 60 / (Number(song.tempo) || 80);
    const lines = [...root.querySelectorAll('.lnf-line')].map((el) => ({
        el,
        from: Number(el.dataset.from),
        to: Number(el.dataset.to),
        words: [...el.querySelectorAll('[data-b]')].map((word) => ({ el: word, b: Number(word.dataset.b) })),
    }));
    const next = root.querySelector('[data-lnf-next]');
    const live = root.querySelector('[data-lnf-live]');
    let shownLine = -1;
    // What moves with the song, each written on itself (registered, never inherited: a frame restyles these few elements,
    // not the page; an inherited --lnf-t on .lnf restyled and laid out the whole page every frame, 71% of the main
    // thread while it played, 2026-11-20): the lanes' tracks and the trace (--lnf-t, and --lnf-page, the sung line's
    // first beat), the strips' bands (--lnf-t, made with their notes) and the dock's rail (--lnf-run).
    const movers = [...root.querySelectorAll('[data-lnf-lanes] :is(.lnf-tr, .lnf-trace)')];
    const rail = root.querySelector('.lnf-rail');
    let shownT = '';
    let shownRun = '';
    function moveTo(beat) {
        const t = String(Math.round(beat * 1000) / 1000);
        if (t === shownT) return;
        shownT = t;
        for (const el of movers) el.style.setProperty('--lnf-t', t);
    }
    function runTo(p) {
        const run = String(Math.round(p * 1000) / 1000);
        if (run === shownRun) return;
        shownRun = run;
        rail?.style.setProperty('--lnf-run', run);
    }

    // ---- the song's place, drawn -------------------------------------------------------------------------------
    function draw(beat) {
        moveTo(beat);
        for (const gem of gems) {
            const state = noteState(gem, beat);
            // The next note's glow rises frame by frame while the song plays (not in a still pose, nor with reduced motion).
            const glow = state === '' && playing() && !calm.matches ? Math.round(nextGlow(gem, beat, beatSeconds) * 100) / 100 : 0;
            if (glow !== gem.glow) {
                gem.glow = glow;
                if (glow > 0) gem.el.style.setProperty('--gem-g', String(glow));
                else gem.el.style.removeProperty('--gem-g');
            }
            if (state === gem.state) continue;
            gem.state = state;
            gem.el.toggleAttribute('data-ring', state === 'lit');
            if (state === 'sung') gem.el.dataset.state = 'settled';
            else delete gem.el.dataset.state;
        }
        if (!lines.length) return;
        const at = phraseAt(lines, beat);
        if (at !== shownLine) {
            shownLine = at;
            lines.forEach((line, i) => (line.el.hidden = i !== at));
            if (next) next.textContent = lines[at + 1]?.words.map((word) => word.el.textContent).join(' ') ?? '';
            for (const el of movers) el.style.setProperty('--lnf-page', String(lines[at].from));
        }
        const line = lines[at];
        line.words.forEach((word, i) => {
            const until = line.words[i + 1]?.b ?? line.to;
            word.el.toggleAttribute('data-now', beat > 0 && beat >= word.b && beat < until);
            word.el.toggleAttribute('data-done', beat > 0 && beat >= until);
        });
    }

    const playing = () => root.hasAttribute('data-playing');
    // Stopped, the song is back at its start (the player's Stop is a stop), and every lane is lit again.
    const rest = () => {
        runTo(0);
        draw(0);
        build(null);
        strips(0);
    };

    // One frame loop, the player's: each of its frames (mixer-frame, its place p, 0 to 1 of the whole play, from its one
    // clock) draws the stage; this module runs no loop of its own.
    root.addEventListener('mixer-frame', (event) => {
        if (!playing()) return;
        const p = Number(event.detail?.p) || 0;
        const at = buildAt(p * beats * passes, beats, passes);
        runTo(p);
        draw(at.beat);
        build(at);
        strips(at.beat);
    });

    // The player says when it starts and stops (its attribute): the lanes follow its next frame.
    new MutationObserver(() => {
        liven();
        if (playing()) {
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
        el: lane,
        name: lane.dataset.name,
        title: lane.dataset.title ?? lane.dataset.name,
        join: Number(lane.dataset.join) || 0,
        wait: false,
        hit: lane.querySelector('[data-hear-alone]'),
        learn: lane.dataset.learn || null,
    }]));
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
        if (key) named = key;
        if (named) name(named, key !== null);
        const heard = key ? lanes.get(key)?.name : null;
        say(heard ? live?.dataset.alone?.replace(':part', heard) : live?.dataset.all);
    });

    // The Hear scene's button presses a lane's own button: the player hears that, nothing else.
    hear?.addEventListener('click', () => lanes.get(root.dataset.alone ?? named)?.hit?.click());

    // The first scene's big button is the dock's disc, pressed (still inside the press, so the sound may start).
    const disc = root.querySelector('[data-play]');
    for (const button of root.querySelectorAll('[data-lnf-play]')) {
        button.addEventListener('pointerdown', () => disc?.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })));
        button.addEventListener('click', () => disc?.click());
    }

    // ---- the recorded voices' download, while a press waits for it -----------------------------------------------
    // The player says how far it is (mixer-load: bytes over all the voices; total 0 until every file's length is
    // known): the Play button's ring fills (--lnf-load, data-load) and the words say "Loading the voices… 37%". A
    // failure says so in the same place, until the next press.
    const loads = [...root.querySelectorAll('[data-lnf-load]')];
    let share = null; // 0 to 1 of the download, null while it can't be told
    function loadWords() {
        const state = root.hasAttribute('data-loading') ? 'loading' : root.hasAttribute('data-failed') ? 'failed' : '';
        const known = state === 'loading' && share !== null;
        if (known) root.dataset.load = String(Math.round(share * 100));
        else delete root.dataset.load;
        const percent = known ? `${Math.round(share * 100)}%` : '';
        for (const el of loads) {
            const words = el.dataset[state] ?? '';
            if (el.dataset.lnfLoad !== 'dock') el.textContent = words === '' ? '' : `${words} ${percent}`.trim();
            else if (words === '') el.textContent = '';
            else {
                // In the dock the words give way on a phone (css: .lnf-wide); the percent stays.
                const wide = document.createElement('span');
                wide.className = 'lnf-wide';
                wide.textContent = `${words} `;
                el.replaceChildren(wide, percent);
            }
        }
    }
    root.addEventListener('mixer-load', (event) => {
        const { loaded, total } = event.detail ?? {};
        share = total > 0 ? Math.max(0, Math.min(1, loaded / total)) : null;
        root.style.setProperty('--lnf-load', String(share ?? 0));
        loadWords();
    });
    new MutationObserver(loadWords).observe(root, { attributes: true, attributeFilter: ['data-loading', 'data-failed'] });

    // ---- the song building: who has joined ---------------------------------------------------------------------
    const order = [...lanes.values()];
    const floors = [...root.querySelectorAll('.lnf-floor')].map((el) => ({ el, bands: [...el.querySelectorAll('i')], seen: false, live: false, notes: null }));
    const now = root.querySelector('[data-lnf-now]');
    let said = '';

    // `at` is where the play is ({ pass, beat }), null while nothing plays: each lane waits or is in, and the line says so.
    function build(at) {
        const alone = 'alone' in root.dataset;
        order.forEach((lane, i) => {
            const wait = at !== null && !alone && waiting(lane.join, at.pass, at.beat);
            if (at === null) lane.el.removeAttribute('data-in');
            if (wait === lane.wait) return;
            // Its voice comes in (not: a voice was heard alone meanwhile, which lights every lane without a word).
            if (lane.wait && at !== null && !alone) lane.el.setAttribute('data-in', '');
            if (wait) lane.el.removeAttribute('data-in');
            lane.wait = wait;
            lane.el.toggleAttribute('data-wait', wait);
            for (const floor of floors) floor.bands[i]?.toggleAttribute('data-wait', wait);
        });
        if (!now) return;
        const caption = at === null ? null : captionOf(order.map((lane) => lane.join), at.pass, at.beat, alone);
        const lane = caption === null ? null : caption.say === 'alone' ? lanes.get(root.dataset.alone) : order[caption.at];
        const text = caption === null ? '' : (now.dataset[caption.say] ?? '').replace(':part', (caption.say === 'alone' ? lane?.name : lane?.title) ?? '');
        if (text === said) return;
        said = text;
        now.textContent = text;
        // A screen reader hears each voice come in, and all four; the start, the stop and a voice alone have their own words.
        if (caption !== null && (caption.say === 'join' || caption.say === 'all')) say(text);
    }

    // ---- the strips down the page: the song itself while it plays --------------------------------------------------
    // A strip's notes are made the first time it shows the song: the stage's own gems (their beats, lengths and
    // heights), a bar each, in a track per band.
    function notesOf(floor) {
        const made = [];
        order.forEach((lane, i) => {
            const band = floor.bands[i];
            if (!band) return;
            const track = document.createElement('u');
            for (const gem of lane.el.querySelectorAll('.lnf-g')) {
                const bar = document.createElement('b');
                for (const name of ['--b', '--l', '--y']) bar.style.setProperty(name, gem.style.getPropertyValue(name));
                track.append(bar);
                made.push({ el: bar, b: Number(gem.dataset.b), l: Number(gem.dataset.l), state: '' });
            }
            band.append(track);
            movers.push(track);
            track.style.setProperty('--lnf-t', shownT || '0');
        });
        return made;
    }

    // Which strips show the song: the ones in view, while it plays and motion is allowed.
    function liven() {
        for (const floor of floors) {
            const on = floor.seen && playing() && !calm.matches;
            if (on === floor.live) continue;
            floor.live = on;
            if (on) floor.notes ??= notesOf(floor);
            floor.el.toggleAttribute('data-live', on);
        }
    }

    function strips(beat) {
        for (const floor of floors) {
            if (!floor.live) continue;
            for (const bar of floor.notes) {
                const state = noteState(bar, beat);
                if (state === bar.state) continue;
                bar.state = state;
                bar.el.toggleAttribute('data-lit', state === 'lit');
                bar.el.toggleAttribute('data-sung', state === 'sung');
            }
        }
    }

    if ('IntersectionObserver' in window) {
        const seen = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                const floor = floors.find((f) => f.el === entry.target);
                if (floor) floor.seen = entry.isIntersecting;
            }
            liven();
        });
        for (const floor of floors) seen.observe(floor.el);
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

    // ---- the page, as it scrolls --------------------------------------------------------------------------------
    const screen = () => window.innerHeight || 1;
    let lastY = window.scrollY;
    let queued = false;

    // A line above the page (an outreach partner's, a mudeo arrival's): the top line sits under it while it's in view.
    const note = document.querySelector('[data-lnf-note]');
    const tailEl = root.querySelector('[data-lnf-tail]');
    const world = root.querySelector('.lnf-world');

    function onScroll() {
        queued = false;
        const y = window.scrollY;
        if (note) root.style.setProperty('--lnf-note', `${Math.max(0, Math.round(note.getBoundingClientRect().bottom))}px`);
        // On the plans and the finale: the sung line gives way there while nothing plays.
        root.toggleAttribute('data-tail', (tailEl?.getBoundingClientRect().top ?? 1) < screen() / 2);
        // The small player rises only while the song plays and its lanes have gone by, scrolled past above (Play lives
        // with the lanes; lanes still to come below, as under a phone's first screen, are on their way, not away). Read
        // on every scroll, not by crossing: a jump to the top or the end passes the lanes without ever meeting them.
        root.toggleAttribute('data-away', (world?.getBoundingClientRect().bottom ?? 1) < 0);
        // The top line: smaller on the way down, back on the way up (and always whole at the very top).
        // Off the very top: the game look's top line takes its solid ground there (nothing shows through behind it).
        root.toggleAttribute('data-scrolled', y >= 8);
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

    if ('IntersectionObserver' in window) {
        // Each section comes in as it enters (motion allowed only; without a script, or with reduced motion, every section
        // is simply there). Once in, it stays.
        if (!calm.matches) {
            const sections = [...root.querySelectorAll('.lnf-story, .lnf-tail .lnf-sc')];
            const enter = new IntersectionObserver((entries) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    entry.target.dataset.in = '';
                    enter.unobserve(entry.target);
                }
            }, { rootMargin: '0px 0px -12% 0px' });
            for (const section of sections) enter.observe(section);
            root.dataset.reveal = '';
        }
    }

    onScroll();
    rest();
    root.dataset.ready = '';
    fitWords();

    // ---- the gems' words: the app's fit ladder (docs/glass-spec.md 2) ---------------------------------------------
    // Once the page is up (the glass module comes after the first paint), and again when the font is in and whenever a
    // lane changes size: each gem's word inside it, smaller, above it, or not at all, the same in every state.
    function fitWords() {
        import('../glass.js').then(({ gemFit, whenWordsReady }) => {
            const probe = document.createElement('canvas').getContext('2d');
            const place = () => {
                for (const track of root.querySelectorAll('[data-lnf-lanes] .lnf-tr')) {
                    const own = gems.filter((gem) => gem.el.parentElement === track);
                    own.forEach((gem, i) => {
                        const box = getComputedStyle(gem.el);
                        const w = Number.parseFloat(box.width);
                        const h = Number.parseFloat(box.height);
                        if (!(w > 0) || !(h > 0)) return;
                        const x = gem.el.offsetLeft;
                        const next = own[i + 1];
                        const fit = gemFit(probe, { x, y: gem.el.offsetTop, w, h, word: gem.word, nextX: next ? next.el.offsetLeft : Infinity, bounds: { top: 0, right: Infinity } });
                        const span = gem.el.querySelector('.gem-word');
                        if (span && fit.text && span.textContent !== fit.text) span.textContent = fit.text;
                        const px = /(\d+(?:\.\d+)?)px/.exec(fit.font ?? '')?.[1];
                        if (px) gem.el.style.setProperty('--gem-word-px', px);
                        gem.el.dataset.fit = fit.where;
                    });
                }
            };
            place();
            whenWordsReady(place);
            if ('ResizeObserver' in window) {
                let queued = false;
                const again = new ResizeObserver(() => {
                    if (queued) return;
                    queued = true;
                    requestAnimationFrame(() => {
                        queued = false;
                        place();
                    });
                });
                for (const lane of lanes.values()) again.observe(lane.el);
            }
        }, () => {});
    }
}
