// The song part pages' player (docs/seo-growth-plan.md, item 1; /songs/<song>/<part>, the view
// site/song-part.blade.php): the app's own Sing lane (js/embed/lane.js) on the page, for a free song, with
// three ways to play the part, chosen in one segmented row (the free tools' segmented control):
// - Your part: the part alone;
// - With the others: the part on top, the other parts clearly there;
// - Sing it: the others under you and your part silent, while the mic (asked for on that tap, live-mic.js) is
//   followed as Sing follows a singer: your pitch drawn and each note lit as you hit it, and how many you lit beside
//   the time. Nothing is recorded or sent.
// A new way to play carries on from the same place. The mic never holds the music (Hillel, 2026-11-18: with the mic
// blocked, Sing it stopped, and the reason was a line under the lane, off the screen): the others play at once, and a
// mic that can't be had says so in a chip on the lane itself, where the eye is; once it's allowed (or plugged in) the
// player listens again by itself, mid-song.
// A small toggle at the lane's corner shows the part as moving notes or as sheet music (part-sheet.js: your part, the
// tune cued above it, lit as it sounds). Sheet music first (Hillel, 2026-11-18: "make the sheet music the default
// choice, if someone toggles game mode then auto switch the song to notes"): game mode turned on shows the notes, now
// or as the page opens; a view picked by hand is kept for that skin on this device.
// The part sings with its real singer's voice where it has one, and a small toggle swaps it for the synth.
// "Open in the app" takes the song and the part there (startUrl: /?song=<slug>&part=<key>). The words come
// from the page, in its language. A Plus song (the API refuses it signed out) never mounts a player: the
// page offers the app instead, and a load that fails hides the player the same way.
// Tested in tests/flows/part_player.py.

import { mount as mountLane } from '../embed/lane.js';
import { playState } from './play-state.js';
import { segmented } from './tool-layout.js';
import { mountPartSheet } from './part-sheet.js';

// The view picked by hand, per skin ('lane' or 'sheet'): this device's choice, a convenience (nothing breaks without it).
const VIEW_KEY = 'stk-part-view';
const skinNow = () => (document.documentElement.dataset.skin === 'serious' ? 'serious' : 'game');
/** Game mode turned on by the singer (now or before), not the site's default look for a first visit. */
const gameChosen = () => {
    try {
        return window.stackapellaTheme?.chosen?.().skin === 'game';
    } catch (error) {
        console.warn('The look chosen could not be read', error); // i18n-ignore
        return false;
    }
};
const LANE_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="5" width="8" height="4" rx="2"/><rect x="13" y="10" width="8" height="4" rx="2"/><rect x="6" y="15" width="9" height="4" rx="2"/></svg>';
const SHEET_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 6h20M2 9.5h20M2 13h20M2 16.5h20M2 20h20" opacity=".55"/><ellipse cx="9" cy="16.5" rx="2.6" ry="1.9" fill="currentColor" stroke="none" transform="rotate(-20 9 16.5)"/><path d="M11.4 15.8V4.5" stroke-width="1.8"/></svg>';
const attr = (text) => String(text ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const clock = (seconds) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;

/**
 * Mounts the player in `el` for `part` of the song `slug` (the template from /api/templates/<slug>, or
 * `url` for a demo). `words`: hear, withOthers, sing (and hearShort, withOthersShort, singShort for a narrow phone), modes, play, pause, loading, real, synth, voice,
 * micNote, micDenied, micMissing, micUnsupported, score (":hit of :total notes lit"), open, unavailable, view, viewLane, viewSheet.
 * `listen` stands in for the mic (the flows). Returns the lane's handle.
 */
export function mountPartPlayer(el, { slug, part, url = null, startUrl = '', words = {}, listen = null } = {}) {
    el.dataset.ui = 'part-player';
    // One panel (Hillel's part page redesign, 2026-11-10: "it makes better use of space"): a header row first, so focus
    // goes in the order it's seen (Play and the time at the left, the modes at the right, the real voice's choice beside
    // them; under 640 px the modes take a line of their own), then the lane the panel's width, taller on a wide screen.
    el.innerHTML = `
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3" data-part-head>
            <button type="button" class="btn-primary min-h-14 min-w-0 gap-3 rounded-full px-8 text-[1.0625rem]" data-ui="part-play"><span class="h-4 w-3.5 shrink-0 bg-current [clip-path:polygon(0_0,100%_50%,0_100%)]" aria-hidden="true" data-play-icon></span><span data-play-label></span></button>
            <span class="font-mono text-sm tabular-nums text-muted" data-ui="part-time">0:00</span>
            <span class="hidden text-sm tabular-nums text-cream" data-ui="part-score"></span>
            <div class="w-full sm:ml-auto sm:w-auto" data-ui="part-modes"></div>
            <div class="hidden w-full sm:w-auto" data-ui="part-voice"></div>
        </div>
        <div class="relative mt-4 h-56 overflow-hidden rounded-3xl bg-raised sm:h-72 lg:h-96 data-[view=sheet]:h-[28rem] sm:data-[view=sheet]:h-96" data-ui="part-lane">
            <div class="s-score-paper absolute inset-x-0 top-0 bottom-16 z-[5] overflow-y-auto px-3 py-4 sm:px-6" translate="no" data-ui="part-sheet" hidden></div>
            <p class="absolute inset-x-3 top-3 z-10 mx-auto w-fit max-w-full rounded-2xl bg-panel/95 px-4 py-2 text-center text-sm font-semibold text-warn shadow-lg" role="alert" data-ui="part-mic" hidden></p>
            <div class="absolute right-3 bottom-3 z-10 flex gap-1 rounded-full bg-panel p-1 shadow-lg" role="group" aria-label="${attr(words.view)}" data-ui="part-view">
                <button type="button" class="seg-option min-h-11 w-11 rounded-full px-0" data-view="lane" aria-pressed="true" aria-label="${attr(words.viewLane)}" title="${attr(words.viewLane)}">${LANE_ICON}</button>
                <button type="button" class="seg-option min-h-11 w-11 rounded-full px-0" data-view="sheet" aria-pressed="false" aria-label="${attr(words.viewSheet)}" title="${attr(words.viewSheet)}">${SHEET_ICON}</button>
            </div>
        </div>
        <p class="mt-3 min-h-5 text-sm text-muted" role="status" data-ui="part-status"></p>
        <a class="mt-1 inline-block py-2 text-sm font-semibold text-cream underline underline-offset-4 hover:text-[var(--primary)]" data-ui="part-open" hidden></a>`;
    const $ = (hook) => el.querySelector(`[data-ui="${hook}"]`);
    const playButton = $('part-play');
    const status = $('part-status');
    const open = $('part-open');
    if (startUrl) {
        open.href = startUrl;
        open.textContent = words.open ?? '';
        open.hidden = false;
    }

    const lane = mountLane($('part-lane'), { song: { slug, part, url }, words, open: listen }); // listen: a stand-in mic for the flows

    // The lane or the sheet music, from the toggle at the lane's corner; a hand-picked view is kept per skin.
    const sheet = mountPartSheet($('part-sheet'), { lane, part });
    // The sheet is taller than the lane on a phone (a line of music needs the room), and ends above the toggle's strip.
    const view = (key, remember = true) => {
        $('part-lane').dataset.view = key;
        const canvas = $('part-lane').querySelector('canvas');
        if (canvas) canvas.style.visibility = key === 'sheet' ? 'hidden' : ''; // the strip under the sheet stays plain
        for (const button of $('part-view').querySelectorAll('[data-view]')) button.setAttribute('aria-pressed', String(button.dataset.view === key));
        sheet.show(key === 'sheet').catch((error) => console.warn('The sheet music could not be drawn', error)); // i18n-ignore
        if (!remember) return;
        try {
            localStorage.setItem(`${VIEW_KEY}:${skinNow()}`, key);
        } catch (error) {
            console.warn('The view choice is not kept on this device', error); // i18n-ignore
        }
    };
    $('part-view').addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-view]') : null;
        if (button) view(button.dataset.view);
    });
    /** This skin's view: the one picked by hand here, else the notes in game mode turned on, else the sheet music. */
    const preferred = () => {
        let picked = null;
        try {
            picked = localStorage.getItem(`${VIEW_KEY}:${skinNow()}`);
        } catch (error) {
            console.warn('The view choice is not read on this device', error); // i18n-ignore
        }
        if (picked === 'lane' || picked === 'sheet') return picked;
        return skinNow() === 'game' && gameChosen() ? 'lane' : 'sheet';
    };
    view(preferred(), false);
    // Game mode toggled on (or off) while the page is open: the view follows the skin.
    let skinShown = skinNow();
    document.addEventListener('stackapella:theme', () => {
        if (skinNow() === skinShown) return;
        skinShown = skinNow();
        view(preferred(), false);
    });
    const show = { playing: false, starting: false };
    let lit = 0;
    let passed = 0;
    let micProblem = null; // 'denied', 'no-mic' or 'unsupported' while singing along without the mic

    const say = (text) => {
        status.textContent = text ?? '';
    };
    const hint = () => say(mode === 'sing' ? words.micNote : '');
    /** Singing along: the mic's trouble on the lane, and how many notes you've lit beside the time. */
    function singing() {
        const sing = mode === 'sing';
        const chip = $('part-mic');
        chip.textContent = micProblem === 'denied' ? words.micDenied : micProblem === 'no-mic' ? words.micMissing : micProblem ? words.micUnsupported : '';
        chip.hidden = !sing || !micProblem;
        const score = $('part-score');
        score.textContent = fill(words.score, { hit: lit, total: passed });
        score.classList.toggle('hidden', !sing || Boolean(micProblem) || passed === 0);
    }

    // Loading, a slow load and a press that failed, shown as every player shows them (play-state.js).
    const states = playState(playButton, el);
    let cancelled = false; // a second press while it loads: it stops as soon as it starts

    function paint() {
        const label = show.starting ? words.loading : show.playing ? words.pause : words.play;
        playButton.className = `${show.playing ? 'btn-secondary' : 'btn-primary'} relative min-h-14 min-w-0 flex-1 gap-3 rounded-full text-[1.0625rem] sm:flex-none sm:px-8`;
        playButton.querySelector('[data-play-icon]').className = `shrink-0 bg-current ${show.playing ? 'size-3.5 rounded-[3px]' : 'h-4 w-3.5 [clip-path:polygon(0_0,100%_50%,0_100%)]'}`;
        playButton.querySelector('[data-play-label]').textContent = label ?? '';
        playButton.dataset.state = show.starting ? 'starting' : show.playing ? 'playing' : 'paused';
    }

    let gone = false;
    const failed = (error) => {
        if (gone) return; // the load and the press can both fail
        gone = true;
        console.warn('The part player could not load', error);
        lane.destroy();
        for (const hook of ['part-lane', 'part-modes', 'part-play', 'part-time', 'part-voice']) $(hook).hidden = true; // the sheet and its toggle are in the lane's box
        say(words.unavailable);
    };
    const start = (mode) => {
        if (mode !== lane.mode() || !lane.time()) {
            lit = 0;
            passed = 0;
        }
        singing(); // a mic problem stays said until the mic answers (no flicker as it's asked again)
        cancelled = false;
        lane.play(mode).catch((error) => {
            if (gone) return;
            console.warn('The part could not play', error); // i18n-ignore
            states.failed();
        });
    };

    let mode = 'part';
    const modes = segmented($('part-modes'), [
        // Shorter words under 400 px (Part | All | Sing), when the page gives them: the row never wraps.
        { key: 'part', label: words.hear ?? '', short: words.hearShort ?? '' },
        { key: 'all', label: words.withOthers ?? '', short: words.withOthersShort ?? '' },
        { key: 'sing', label: words.sing ?? '', short: words.singShort ?? '' },
    ], {
        current: 'part',
        label: words.modes ?? '',
        pick: (key) => {
            modes.set(key);
            const wasPlaying = show.playing;
            lane.pause();
            mode = key;
            hint();
            singing();
            if (wasPlaying) start(key);
        },
    });

    playButton.addEventListener('click', () => {
        if (show.starting) {
            cancelled = true;
            show.starting = false;
            states.idle();
            paint();
            return;
        }
        if (show.playing) lane.pause();
        else start(mode);
    });
    // The voices made ahead when someone reaches for the player, so the press starts at once.
    el.addEventListener('pointerenter', () => lane.prepare(), { once: true });
    el.addEventListener('focusin', () => lane.prepare(), { once: true });

    let voiceToggle = null;
    const offerVoice = () => {
        if (voiceToggle || !lane.hasReal()) return;
        const holder = $('part-voice');
        voiceToggle = segmented(holder, [
            { key: 'real', label: words.real ?? '' },
            { key: 'synth', label: words.synth ?? '' },
        ], {
            current: lane.voice(),
            label: words.voice ?? '',
            pick: (key) => {
                voiceToggle.set(key);
                const wasPlaying = show.playing;
                lane.voice(key); // pauses; the next play makes the part's voice again
                if (wasPlaying) start(mode);
            },
        });
        holder.classList.remove('hidden');
    };

    lane.on('state', ({ playing, starting }) => {
        if (playing && cancelled) {
            cancelled = false;
            lane.pause();
            return;
        }
        show.playing = Boolean(playing);
        show.starting = Boolean(starting) && !playing && !cancelled;
        if (show.starting) states.loading();
        else if (show.playing) states.playing();
        else if (states.state === 'loading') states.idle();
        paint();
        if (playing) offerVoice();
    });
    lane.on('time', ({ seconds, duration }) => {
        $('part-time').textContent = `${clock(seconds)} / ${clock(duration)}`;
    });
    lane.on('note', ({ hit }) => {
        passed++;
        if (hit) lit++;
        singing();
    });
    lane.on('mic', ({ error = null }) => {
        micProblem = error;
        if (!error) {
            // Listening from here (at the start, or allowed mid-song): the notes before it aren't counted as missed.
            lit = 0;
            passed = 0;
        }
        singing();
        if (error) watchForMic();
    });
    // Once the mic is allowed, or one is plugged in, the player listens again by itself (the music never stopped).
    let watching = false;
    function watchForMic() {
        if (watching) return;
        watching = true;
        const again = () => {
            if (micProblem) lane.listen();
        };
        navigator.mediaDevices?.addEventListener?.('devicechange', again);
        navigator.permissions?.query({ name: 'microphone' }).then((permission) => {
            permission.addEventListener('change', () => {
                if (permission.state !== 'denied') again();
            });
        }, (error) => console.warn('The mic\'s permission can\'t be watched here', error)); // i18n-ignore
    }
    lane.on('error', ({ error }) => failed(error));

    paint();
    return lane;
}
