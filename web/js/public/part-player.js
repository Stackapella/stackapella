// The song part pages' player (docs/seo-growth-plan.md, item 1; /songs/<song>/<part>, the view
// site/song-part.blade.php): the app's own Sing lane (js/embed/lane.js) on the page, for a free song, with
// three ways to play the part, chosen in one segmented row (the free tools' segmented control):
// - Your part: the part alone;
// - With the others: the part on top, the other parts clearly there;
// - Sing it: the others under you and the part quietly as a guide, while the mic (asked for on that tap,
//   live-mic.js) is followed as Sing follows a singer: your pitch drawn and each note lit as you hit it,
//   then how many you lit. Nothing is recorded or sent.
// The part sings with its real singer's voice where it has one, and a small toggle swaps it for the synth.
// "Open in the app" takes the song and the part there (startUrl: /?song=<slug>&part=<key>). The words come
// from the page, in its language. A Plus song (the API refuses it signed out) never mounts a player: the
// page offers the app instead, and a load that fails hides the player the same way.
// Tested in tests/flows/part_player.py.

import { mount as mountLane } from '../embed/lane.js';
import { segmented } from './tool-layout.js';

const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const clock = (seconds) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;

/**
 * Mounts the player in `el` for `part` of the song `slug` (the template from /api/templates/<slug>, or
 * `url` for a demo). `words`: hear, withOthers, sing (and hearShort, withOthersShort, singShort for a narrow phone), modes, play, pause, loading, real, synth, voice,
 * micNote, micDenied, micMissing, micUnsupported, score (":hit of :total notes lit"), open, unavailable.
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
            <div class="w-full sm:ml-auto sm:w-auto" data-ui="part-modes"></div>
            <div class="hidden w-full sm:w-auto" data-ui="part-voice"></div>
        </div>
        <div class="relative mt-4 h-56 overflow-hidden rounded-3xl bg-raised sm:h-72 lg:h-96" data-ui="part-lane"></div>
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
    const show = { playing: false, starting: false };
    let lit = 0;
    let passed = 0;

    const say = (text, tone = 'muted') => {
        status.textContent = text ?? '';
        status.className = `mt-3 min-h-5 text-sm ${tone === 'warn' ? 'text-warn' : 'text-muted'}`;
    };
    const hint = () => say(mode === 'sing' ? words.micNote : '');

    function paint() {
        const label = show.starting ? words.loading : show.playing ? words.pause : words.play;
        playButton.className = `${show.playing ? 'btn-secondary' : 'btn-primary'} min-h-14 min-w-0 flex-1 gap-3 rounded-full text-[1.0625rem] sm:flex-none sm:px-8`;
        playButton.querySelector('[data-play-icon]').className = `shrink-0 bg-current ${show.playing ? 'size-3.5 rounded-[3px]' : 'h-4 w-3.5 [clip-path:polygon(0_0,100%_50%,0_100%)]'}`;
        playButton.querySelector('[data-play-label]').textContent = label ?? '';
        playButton.setAttribute('aria-busy', String(show.starting));
        playButton.dataset.state = show.starting ? 'starting' : show.playing ? 'playing' : 'paused';
    }

    let gone = false;
    const failed = (error) => {
        if (gone) return; // the load and the press can both fail
        gone = true;
        console.warn('The part player could not load', error);
        lane.destroy();
        for (const hook of ['part-lane', 'part-modes', 'part-play', 'part-time', 'part-voice']) $(hook).hidden = true;
        say(words.unavailable);
    };
    const start = (mode) => {
        if (mode !== lane.mode() || !lane.time()) {
            lit = 0;
            passed = 0;
        }
        lane.play(mode).catch(failed);
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
            if (wasPlaying) start(key);
        },
    });

    playButton.addEventListener('click', () => {
        if (show.starting) return;
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
        show.playing = Boolean(playing);
        show.starting = Boolean(starting) && !playing;
        paint();
        if (playing) offerVoice();
        if (playing && lane.mode() === 'sing') say(words.micNote);
    });
    lane.on('time', ({ seconds, duration }) => {
        $('part-time').textContent = `${clock(seconds)} / ${clock(duration)}`;
    });
    lane.on('note', ({ hit }) => {
        passed++;
        if (hit) lit++;
        if (lane.mode() === 'sing') say(fill(words.score, { hit: lit, total: passed }));
    });
    lane.on('ended', () => {
        if (lane.mode() !== 'sing') hint();
    });
    lane.on('mic', ({ error }) => {
        say(error === 'denied' ? words.micDenied : error === 'no-mic' ? words.micMissing : words.micUnsupported, 'warn');
    });
    lane.on('error', ({ error }) => failed(error));

    paint();
    return lane;
}
