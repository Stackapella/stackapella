// Hear yourself as a choir (/your-choir; server/resources/views/site/your-choir.blade.php, css/your-choir.css). Hillel,
// 2026-11-20: "something immersive to experience one real voice becoming 2 fake ones".
//
// A stage that fills the screen for its scenes (hero, real, split, four when all four parts are there, yours), then a
// page that scrolls, and a dock that stays. One player, this module's, on the AudioContext clock: one press plays the
// whole story as one timeline (App\Support\Pages\YourChoirStage's `play`): the singer's real recording, a breath of
// silence, then the AI song made from her singing, its tune and harmony from the first note and, with four parts, the
// tenor and bass joining at the middle phrase. Every voice is decoded first (as the app's players do: app-voices.js
// realVoice, its breaths softened as every library voice's are, soften-breaths.js) and every source started on the
// clock before the first note, a join being a scheduled gain ramp (Hillel, on the home page: "play all but unmute to
// add to keep in sync"); nothing is ever scheduled with a timer. Stop is a stop: back to the start.
// Scrolling changes the picture (which lanes the view opens), never the sound; the page never scrolls by itself. The
// picture is the sound: while it plays, a lane whose voice is heard is lit (data-live) and one whose voice is silent
// waits, dim, its notes ghosts (data-wait); the line under the lanes and the dock say who is heard, and a screen reader
// hears it. While it doesn't play, each view poses its lanes at a moment of its own.
// Only the recorded voices sing here (the AI guides are recordings too): a press waits for them, with real progress on
// the button (data-load, --yc-load), and says so honestly if they can't be had; the next press tries again. They start
// coming on a hover, a focus or a touch of a Play button, so most presses don't wait.
// With reduced motion (or no script) nothing is pinned: the scenes are still panels and the lanes one picture, which
// this module still lights in place as the voices sing. Tested in tests/your-choir.test.js.

import { audio } from './voices.js';
import { playState } from './play-state.js';

/** The silence before the first note (seconds), so every source is set on the clock before anything sounds. */
export const LEAD = 0.12;

/** A join's ramp (seconds): the voice comes in over this, ending on its beat. */
export const JOIN_FADE = 0.05;

/** Where the page rests when stopped: each view's still pose, in its song's beats ({ rb: real, ab: AI }). Pure. */
export function poseOf(view, join = 0) {
    return { hero: { rb: 1, ab: 0 }, real: { rb: 5, ab: 0 }, split: { rb: 5, ab: 3 }, four: { rb: 5, ab: join > 0 ? join + 3 : 12 }, yours: { rb: 5, ab: 12 } }[view] ?? { rb: 0, ab: 0 };
}

/** The scene a scroll position shows: one screen each, the nearest, the last one from there on. Pure. */
export function sceneAt(scrollY, screen, count) {
    if (!(screen > 0) || !(count > 0)) return 0;
    return Math.max(0, Math.min(count - 1, Math.round(scrollY / screen)));
}

/** Whether a scroll position is past the stage (the stage more than half gone). Pure. */
export function inTail(scrollY, screen, count) {
    return screen > 0 && scrollY > (count - 0.5) * screen;
}

/** "1:05". Pure. */
export function clock(seconds) {
    const s = Math.max(0, Math.round(seconds));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/**
 * Where the play is at `t` seconds since its first note, in each segment's own beats: { rb, ab } (below 0 before the
 * segment begins). Pure.
 */
export function beatsAt(play, t) {
    const [real, ai] = play.segments;
    return { rb: (t - real.start) / real.spb, ab: (t - ai.start) / ai.spb };
}

/**
 * Whether a voice of a segment is heard at `t`: from its join (its segment's start, or the beat it joins on) to the end
 * of its own notes. Pure.
 */
export function heard(segment, voice, t) {
    const beat = (t - segment.start) / segment.spb;
    const last = Math.max(0, ...voice.notes.map((n) => n.beat + n.length));
    return beat >= (voice.join || 0) - 0.15 && beat <= last + 0.25 && t >= segment.start - 0.1;
}

/** What the line says at `t`: "real" while she sings, "two" once the AI song starts, "four" once all four sing. Pure. */
export function sayAt(play, t) {
    const [real, ai] = play.segments;
    if (t < ai.start - 0.2) return t >= real.start ? 'real' : '';
    const joining = ai.voices.filter((v) => v.join > 0);
    if (joining.length && joining.every((v) => (t - ai.start) / ai.spb >= v.join - 0.15)) return 'four';
    return 'two';
}

/** A note's state at a beat: "lit" while it sounds, "sung" once it has, else "". Pure. */
export function noteState(note, beat) {
    if (!(beat > 0)) return '';
    if (beat >= note.b + note.l) return 'sung';
    return beat >= note.b ? 'lit' : '';
}

const root = document.querySelector('[data-yc]');
if (root) setUp();

function setUp() {
    const still = matchMedia('(prefers-reduced-motion: reduce)');
    const views = [...root.querySelectorAll('[data-yc-stage] [data-s]')];
    const pinned = root.hasAttribute('data-stage');
    if (pinned) document.documentElement.classList.add('yc-page');
    let play = null;
    try {
        play = JSON.parse(root.querySelector('[data-yc-data]')?.textContent ?? 'null');
    } catch (error) {
        console.warn('The stage\'s data could not be read: no player', error);
        play = null;
    }

    // ---- the scenes, from the scroll -----------------------------------------------------------------------------
    const dots = [...root.querySelectorAll('[data-yc-dots] a')];
    const screen = () => window.innerHeight || 1;
    let scene = 0;
    let lastY = window.scrollY;
    let queued = false;
    const viewOf = (i) => views[i]?.dataset.s ?? 'hero';
    // What the player sets up below, once it has (a page that loads scrolled down shows its scene before then).
    let settle = () => {};

    function show(to) {
        if (to === scene && root.dataset.view === viewOf(to)) return;
        scene = to;
        root.dataset.view = viewOf(to);
        dots.forEach((dot, i) => (i === to ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
        settle();
    }

    const note = document.querySelector('[data-yc-note]');
    function onScroll() {
        queued = false;
        const y = window.scrollY;
        if (note) root.style.setProperty('--yc-note', `${Math.max(0, Math.round(note.getBoundingClientRect().bottom))}px`);
        const moving = pinned && !still.matches;
        if (moving) show(sceneAt(y, screen(), views.length));
        const tail = moving ? inTail(y, screen(), views.length) : (root.querySelector('[data-yc-tail]')?.getBoundingClientRect().top ?? 1) < screen() / 2;
        root.toggleAttribute('data-tail', tail);
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
    views.forEach((section, i) => {
        section.addEventListener('focusin', () => {
            if (!pinned || still.matches || (i === scene && !root.hasAttribute('data-tail'))) return;
            window.scrollTo({ top: i * screen(), behavior: 'auto' });
        });
    });
    still.addEventListener('change', () => {
        show(pinned && !still.matches ? sceneAt(window.scrollY, screen(), views.length) : 0);
        settle();
    });

    onScroll();
    if (!play) {
        // No demo voices: the picture (data-picture), lanes of the arrangement's notes and no player. They rest a beat
        // into the song, their words fitted to their gems; the scroll and the CSS do the rest.
        const lanes = [...root.querySelectorAll('[data-yc-lanes] .yc-ln')].map((el) => ({
            el,
            gems: [...el.querySelectorAll('.yc-g')].map((g) => ({ el: g, word: g.textContent.trim() })),
        }));
        for (const lane of lanes) lane.el.querySelector('.yc-tr')?.style.setProperty('--yc-b', '1');
        root.dataset.ready = '';
        if (lanes.length) fitWords(lanes);
        return;
    }

    // ---- the lanes -----------------------------------------------------------------------------------------------
    const [realSeg, aiSeg] = play.segments;
    const joinBeat = Math.max(0, ...aiSeg.voices.map((v) => v.join || 0));
    const lanes = [...root.querySelectorAll('[data-yc-lanes] [data-seg]')].map((el) => {
        const segment = el.dataset.seg === 'real' ? realSeg : aiSeg;
        const voice = segment.voices.find((v) => v.part === el.dataset.part) ?? segment.voices[0];
        return {
            el,
            segment,
            voice,
            live: false,
            wait: false,
            track: el.querySelector('.yc-tr'),
            b: -1,
            gems: [...el.querySelectorAll('.yc-g')].map((g) => ({ el: g, b: Number(g.dataset.b), l: Number(g.dataset.l), state: '', word: g.textContent.trim() })),
        };
    });
    const now = root.querySelector('[data-yc-now]');
    const who = root.querySelector('[data-yc-who]');
    const time = root.querySelector('[data-yc-time]');
    const live = root.querySelector('[data-yc-live]');
    const total = clock(play.seconds);
    const rail = root.querySelector('.yc-rail');
    let said = '';
    let shownTime = '';
    let railAt = -1;

    function say(text) {
        if (!live || !text) return;
        live.textContent = '';
        requestAnimationFrame(() => (live.textContent = text));
    }

    // `t`: seconds since the first note, while it plays; null while it doesn't (the view's still pose, every lane at rest).
    function draw(t) {
        const at = t === null ? (still.matches ? { rb: 0, ab: 0 } : poseOf(root.dataset.view, joinBeat)) : beatsAt(play, t);
        for (const lane of lanes) {
            const beat = lane.segment === realSeg ? at.rb : at.ab;
            place(lane, beat);
            const on = t !== null && heard(lane.segment, lane.voice, t);
            const wait = t !== null && !on;
            if (on && !lane.live) {
                lane.el.removeAttribute('data-in');
                void lane.el.offsetWidth;
                lane.el.setAttribute('data-in', '');
            }
            if (on !== lane.live) lane.el.toggleAttribute('data-live', (lane.live = on));
            if (wait !== lane.wait) lane.el.toggleAttribute('data-wait', (lane.wait = wait));
            if (t === null) lane.el.removeAttribute('data-in');
            for (const gem of lane.gems) {
                const state = t === null ? '' : noteState(gem, beat);
                if (state === gem.state) continue;
                gem.state = state;
                gem.el.toggleAttribute('data-ring', state === 'lit');
                if (state === 'sung') gem.el.dataset.state = 'settled';
                else delete gem.el.dataset.state;
            }
        }
        const line = t === null ? '' : sayAt(play, t);
        const text = line === '' ? '' : (now?.dataset[line] ?? '');
        if (text !== said) {
            said = text;
            if (now) now.textContent = text;
            if (who) who.textContent = text;
            if (text) say(text);
        }
        // The time and the rail, written only when they change (the text a second at a time).
        const shown = `${clock(t ?? 0)} / ${total}`;
        if (time && shown !== shownTime) time.textContent = (shownTime = shown);
        const run = t === null ? 0 : Math.round(Math.min(1, t / play.seconds) * 1000) / 1000;
        if (rail && run !== railAt) rail.style.setProperty('--yc-p', String((railAt = run)));
    }

    // A lane's track moves by its own --yc-b (never inherited: only it restyles), written only when it changes.
    function place(lane, beat) {
        const b = Math.round(Math.max(0, beat) * 1000) / 1000;
        if (b === lane.b) return;
        lane.b = b;
        lane.track?.style.setProperty('--yc-b', String(b));
    }
    const rest = () => {
        draw(null);
        liven();
    };

    // ---- the floors down the page: the AI song itself while it plays (as the home page's strips) ------------------
    // A floor's bands are the AI lanes, in order; its notes are made the first time it shows the song: the lanes' own
    // gems (their beats, lengths and heights), a bar each, in a track per band. A band whose voice is silent is dim.
    const aiLanes = lanes.filter((lane) => lane.segment === aiSeg);
    const floors = [...root.querySelectorAll('.yc-floor')].map((el) => ({ el, bands: [...el.querySelectorAll('i')], tracks: [], seen: false, live: false, notes: null }));

    function notesOf(floor) {
        const made = [];
        aiLanes.forEach((lane, i) => {
            const band = floor.bands[i];
            if (!band) return;
            const track = document.createElement('u');
            for (const gem of lane.gems) {
                const bar = document.createElement('b');
                for (const name of ['--b', '--l', '--y']) bar.style.setProperty(name, gem.el.style.getPropertyValue(name));
                track.append(bar);
                made.push({ el: bar, b: gem.b, l: gem.l, state: '' });
            }
            band.append(track);
            floor.tracks.push(track);
        });
        return made;
    }

    function liven() {
        for (const floor of floors) {
            const on = floor.seen && playing() && !still.matches;
            if (on === floor.live) continue;
            floor.live = on;
            if (on) floor.notes ??= notesOf(floor);
            floor.el.toggleAttribute('data-live', on);
            if (!on) floor.bands.forEach((band) => band.removeAttribute('data-wait'));
        }
    }

    function floorsAt(beat) {
        const b = String(Math.round(beat * 1000) / 1000);
        for (const floor of floors) {
            if (!floor.live) continue;
            for (const track of floor.tracks) track.style.setProperty('--yc-b', b);
            floor.bands.forEach((band, i) => band.toggleAttribute('data-wait', !aiLanes[i] || aiLanes[i].wait));
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

    // ---- the player ----------------------------------------------------------------------------------------------
    const disc = root.querySelector('[data-yc-play]');
    const loads = [...root.querySelectorAll('[data-yc-load]')];
    const playing = () => root.hasAttribute('data-playing');
    let state = null; // the play control's states (play-state.js), on the dock's disc
    let made = null; // the decoded voices, once: Promise<list of { segment, voice, buffer }>
    let share = null;
    let run = null; // { ctx, t0, master, sources }
    let ticket = 0;
    let frame = 0;

    function loadWords() {
        const what = root.hasAttribute('data-loading') ? 'loading' : root.hasAttribute('data-failed') ? 'failed' : '';
        const known = what === 'loading' && share !== null;
        if (known) root.dataset.load = String(Math.round(share * 100));
        else delete root.dataset.load;
        const percent = known ? `${Math.round(share * 100)}%` : '';
        for (const el of loads) {
            const words = el.dataset[what] ?? '';
            el.textContent = words === '' ? '' : el.dataset.ycLoad === 'dock' ? percent : `${words} ${percent}`.trim();
        }
    }
    new MutationObserver(loadWords).observe(root, { attributes: true, attributeFilter: ['data-loading', 'data-failed'] });

    // Every voice, fetched (its download told as it comes), decoded at the page's rate and its breaths softened: once.
    function voices() {
        if (!made) {
            made = (async () => {
                const [{ realVoice }, { softenBreaths }] = await Promise.all([import('./app-voices.js'), import('../soften-breaths.js')]);
                const ctx = audio();
                const all = play.segments.flatMap((segment) => segment.voices.map((voice) => ({ segment, voice })));
                const bytes = all.map(() => ({ loaded: 0, total: 0 }));
                const told = () => {
                    const known = bytes.every((b) => b.total > 0);
                    share = known ? bytes.reduce((s, b) => s + b.loaded, 0) / bytes.reduce((s, b) => s + b.total, 0) : null;
                    root.style.setProperty('--yc-load', String(share ?? 0));
                    loadWords();
                };
                return Promise.all(all.map(async ({ segment, voice }, i) => {
                    const decoded = await realVoice(voice, ctx, (loaded, length) => {
                        bytes[i] = { loaded, total: length || loaded };
                        told();
                    });
                    await softenBreaths(decoded.samples, ctx.sampleRate, voice.notes, segment.spb, decoded.offsetSeconds);
                    const buffer = new AudioBuffer({ numberOfChannels: 1, length: Math.max(1, decoded.samples.length), sampleRate: ctx.sampleRate });
                    buffer.copyToChannel(decoded.samples, 0);
                    return { segment, voice, buffer, offset: decoded.offsetSeconds };
                }));
            })();
            made.catch(() => {
                made = null;
            });
        }
        return made;
    }

    function tick() {
        if (!run) return;
        const t = run.ctx.currentTime - run.t0;
        if (t >= play.seconds + 0.3) {
            stop();
            return;
        }
        draw(Math.max(0, t));
        floorsAt(beatsAt(play, Math.max(0, t)).ab);
        frame = requestAnimationFrame(tick);
    }

    function stop() {
        ticket++;
        cancelAnimationFrame(frame);
        if (run) {
            const { ctx, master, sources } = run;
            const at = ctx.currentTime;
            master.gain.cancelScheduledValues(at);
            master.gain.setValueAtTime(master.gain.value, at);
            master.gain.linearRampToValueAtTime(0, at + 0.06);
            for (const source of sources) {
                try {
                    source.stop(at + 0.08);
                } catch (error) {
                    // A source that had already ended can't be stopped again: nothing to do, but said, never swallowed.
                    console.debug('A voice had already ended', error);
                }
            }
            run = null;
        }
        const was = playing();
        root.removeAttribute('data-playing');
        disc?.setAttribute('aria-label', disc.dataset.labelPlay ?? '');
        if (state && state.state !== 'failed') state.idle();
        rest();
        if (was) say(live?.dataset.stopped);
    }

    async function start() {
        const mine = ++ticket;
        state.loading();
        try {
            const ctx = audio();
            const decoded = await voices();
            if (mine !== ticket) return;
            const t0 = ctx.currentTime + LEAD;
            const master = ctx.createGain();
            master.connect(ctx.destination);
            // Two voices at once sound as one; four a little more: each voice's level keeps the whole about even.
            const level = (segment) => 1 / Math.sqrt(segment.voices.length);
            const sources = decoded.map(({ segment, voice, buffer, offset }) => {
                const source = ctx.createBufferSource();
                source.buffer = buffer;
                const gain = ctx.createGain();
                const on = level(segment);
                if (voice.join > 0) {
                    const joinAt = t0 + segment.start + voice.join * segment.spb;
                    gain.gain.setValueAtTime(0, t0);
                    gain.gain.setValueAtTime(0, Math.max(t0, joinAt - JOIN_FADE));
                    gain.gain.linearRampToValueAtTime(on, joinAt);
                } else {
                    gain.gain.setValueAtTime(on, t0);
                }
                source.connect(gain).connect(master);
                const when = t0 + segment.start + Math.max(0, offset);
                source.start(when, Math.max(0, -offset));
                return source;
            });
            run = { ctx, t0, master, sources };
            root.setAttribute('data-playing', '');
            liven();
            root.setAttribute('data-played', '');
            disc?.setAttribute('aria-label', disc.dataset.labelStop ?? '');
            state.playing();
            say(live?.dataset.playing);
            frame = requestAnimationFrame(tick);
        } catch (error) {
            console.warn('The voices could not play', error);
            if (mine === ticket) state.failed();
        }
    }

    function press() {
        // A press while it loads or plays is a stop (a second press cancels the wait).
        if (playing() || root.hasAttribute('data-loading')) return stop();
        start();
    }

    if (disc) state = playState(disc, root);
    const presses = [disc, ...root.querySelectorAll('[data-yc-go]')].filter(Boolean);
    for (const button of presses) {
        button.addEventListener('click', () => {
            if (!state) return;
            // The AudioContext wakes inside the press itself, as a gesture allows (iOS keeps it silent otherwise).
            audio();
            press();
        });
        // The voices start coming as a press nears.
        for (const type of ['pointerenter', 'focus', 'touchstart']) button.addEventListener(type, () => voices().catch((error) => console.debug('The voices will be fetched again on the press', error)), { once: true, passive: true });
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && playing()) stop();
    });

    // A scene change (or reduced motion turned on or off) poses the lanes again while nothing plays.
    settle = () => {
        if (!playing()) rest();
    };
    rest();
    root.dataset.ready = '';
    fitWords(lanes);
}

// ---- the gems' words: the app's fit ladder (docs/glass-spec.md 2), as the home page places them -------------------
// `lanes`: [{ el, gems: [{ el, word }] }]. Once the glass module is in, again when the font is, and as a lane resizes.
function fitWords(lanes) {
    import('../glass.js').then(({ gemFit, whenWordsReady }) => {
        const probe = document.createElement('canvas').getContext('2d');
        const place = () => {
            for (const lane of lanes) {
                lane.gems.forEach((gem, i) => {
                    const box = getComputedStyle(gem.el);
                    const w = Number.parseFloat(box.width);
                    const h = Number.parseFloat(box.height);
                    if (!(w > 0) || !(h > 0)) return;
                    const next = lane.gems[i + 1];
                    const fit = gemFit(probe, { x: gem.el.offsetLeft, y: gem.el.offsetTop, w, h, word: gem.word, nextX: next ? next.el.offsetLeft : Infinity, bounds: { top: 0, right: Infinity } });
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
            let waiting = false;
            const again = new ResizeObserver(() => {
                if (waiting) return;
                waiting = true;
                requestAnimationFrame(() => {
                    waiting = false;
                    place();
                });
            });
            for (const lane of lanes) again.observe(lane.el);
        }
    }, (error) => console.warn('The gems\' words could not be placed', error));
}
