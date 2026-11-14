// The mic test, /tools/mic-test (docs/tools-plan.md, batch 3; the review in
// docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md, section 2), on the tools' one layout
// (js/public/tool-layout.js). It answers a singer's three questions in order: is the mic allowed (and if
// not, the two steps for this browser on this system), is it the right mic at a good level, and how do I
// sound? The hero is a level meter with the good zone and a peak that holds; the pill says the level in
// a word; the readout is the mic's name, a picker when there's more than one (js/public/mic-pick.js);
// the label says the note we hear (proof the pitch tools will work); the panel is the take: Record 5 s,
// then play it back, heard as Stackapella hears it (no call cleanup). The bar is the next step: Test my
// mic, Record 5 s, Play my take. Behind More: the details (sample rate, channels, the room's noise) and
// turning the mic off.
//
// A thin wrapper: the mic and the app's pitch tracker come from live-mic.js, the level maths from the
// app's own mic check (js/mic-level.js), what to say from mic-check-reading.js, playback from tones.js, the take's length on the audio clock from demo-player.js when().
// The mic opens only on a tap. The take is recorded only on Record, kept in this page's memory, replaced
// by the next, and never sent or saved. The words come from the page (:name, :n and :note are filled in
// here), set as text, never markup.
//
//   import { mountMicTest, WORDS } from '/js/public/tools/mic-test.js';
//   const test = mountMicTest(el, { words, keySlot, next: [{ label, href }] });
//
// The handle: start(), record(), play(), stop() and element. `open` stands in for the mic in tests: a
// function like listen() that hands back onFrame and returns { stop, stream, deviceId, use }.

import { when } from '../demo-player.js';
import { listen, TRACKER_FILES } from '../live-mic.js';
import { onPicked, preferredMic, savedMic, showPicker } from '../mic-pick.js';
import { sound } from '../tones.js';
import { sheetRow, toolLayout } from '../tool-layout.js';
import { audio } from '../voices.js';
import { meterLevel, stepVoice, voiceWatch, VOICE_DBFS } from '../../mic-level.js';
import { pitchName } from '../../notes.js';
import { browserOf, CLIP_PEAK, clipWord, fixesToShow, GOOD_DBFS, headsetLike, levelWord, LOUD_DBFS, problemOf, roomWord } from './mic-check-reading.js';

/** The files the tool loads only when the mic opens: its page keeps them, so it works offline before a first Start. */
export const WARM = TRACKER_FILES;

/** The words a page passes, each translated. The fix steps a page has for blocked mics are optional. */
export const WORDS = [
    'start', 'asking', 'record', 'recording', 'play', 'stopPlaying', 'recordAgain', 'settings', 'settingsTitle', 'done',
    'idleHint', 'howItWorks', 'whatToDo', 'step1', 'step2', 'step3', 'privacy',
    'listening', 'listeningHint', 'good', 'goodHint', 'soft', 'softHint', 'loud', 'loudHint', 'clip', 'clipHint', 'quiet', 'quietHint', 'flat', 'flatHint',
    'weHear', 'micLabel', 'yourTake', 'takeHint', 'recordingHint', 'noRecorder', 'noPlayback', 'headset', 'nextLabel',
    'denied', 'deniedHint', 'busy', 'busyHint', 'noMic', 'noMicHint', 'unsupported', 'unsupportedHint', 'fixTitle',
    'details', 'sampleRate', 'hz', 'channels', 'processing', 'room', 'roomQuiet', 'roomSome', 'roomNoisy', 'micOff', 'kept',
];
/** The fix steps a page may pass for a blocked mic (mic-check-reading.js fixKeys picks among them). */
export const FIX_WORDS = ['fixIos', 'fixChromeAndroid', 'fixSamsungAndroid', 'fixFirefoxAndroid', 'fixChrome', 'fixEdge', 'fixFirefox', 'fixSafari', 'fixMac', 'fixWindows', 'fixOther'];

export const TAKE_SECONDS = 5;
const SEGMENTS = 24; // the meter's bars
const PEAK_HOLD = 1.5; // seconds the peak bar stays lit
const VERDICT_WINDOW = 0.4; // the level said in words is the loudest of this last stretch, so it doesn't flicker
const NOTE_FRESH = 0.5; // a note heard this recently is still shown
const TONES = { good: 'good', soft: 'muted', listening: 'muted', loud: 'warn', clip: 'warn', quiet: 'warn', flat: 'warn' };
const PROBLEM_WORDS = { denied: 'denied', busy: 'busy', 'no-mic': 'noMic', unsupported: 'unsupported' };
const CEIL = 0; // the meter reads to full scale, so too loud has room at the top
const LOUD_LINE = meterLevel(LOUD_DBFS, CEIL);
const VOICE_LINE = meterLevel(VOICE_DBFS, CEIL);
const GOOD_LINE = meterLevel(GOOD_DBFS, CEIL);
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));

const HERO = `
<div class="grid w-full gap-2 pt-2 [@media(max-height:640px)]:pt-0" data-ui="mic-meter">
  <div class="grid h-20 grid-cols-[repeat(24,minmax(0,1fr))] items-end gap-1 [@media(max-height:640px)]:h-12" aria-hidden="true" data-segments></div>
</div>`;

const READOUT = `
<div class="flex min-h-11 items-center justify-center gap-2 text-sm" data-ui="mic-name" data-mic-row hidden>
  <span class="font-semibold text-muted" data-w="micLabel"></span>
  <span class="min-w-0 truncate font-bold text-cream" data-mic-name></span>
  <label class="min-w-0" hidden data-picker-label><span class="sr-only" data-w="micLabel"></span><select class="input w-full max-w-64 truncate" data-ui="mic-picker" data-picker></select></label>
</div>`;

const PANEL = `
<div class="absolute inset-0 overflow-y-auto p-4 text-sm leading-relaxed text-cream">
  <div class="space-y-3" data-intro>
    <ol class="list-decimal space-y-1 ps-5"><li data-w="step1"></li><li data-w="step2"></li><li data-w="step3"></li></ol>
    <p class="text-muted" data-w="privacy"></p>
  </div>
  <div class="space-y-2" data-ui="mic-fix" data-problem hidden>
    <p class="text-cream" data-problem-hint></p>
    <div class="space-y-1" data-fixes hidden><p class="font-semibold" data-w="fixTitle"></p><ul class="list-disc space-y-1 ps-5" data-fix-list></ul></div>
  </div>
  <div class="flex h-full flex-col gap-3" data-take hidden>
    <p class="text-muted" data-take-hint></p>
    <div class="h-2 overflow-hidden rounded-full bg-panel" data-progress hidden><div class="h-full w-full origin-left scale-x-0 bg-[var(--primary)]" data-progress-fill></div></div>
    <div class="relative min-h-14 flex-1" data-wave-box hidden><canvas class="absolute inset-0 size-full" data-ui="take-wave" data-wave></canvas></div>
    <div class="flex flex-wrap items-center gap-2" data-take-actions hidden><button type="button" class="btn-secondary btn-sm" data-ui="record-again" data-record-again data-w="recordAgain"></button></div>
    <p class="text-warn" data-headset hidden data-w="headset"></p>
    <p class="text-muted" data-next hidden><span data-w="nextLabel"></span> <span data-next-links></span></p>
  </div>
</div>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. The mic chosen is kept by
 * mic-pick.js, one choice for every listening tool and course page on this device, so there's nothing to Reset. */
function buildSheet(ui, w) {
    sheetRow(ui.sheet, { label: w('details') }).body.innerHTML = '<dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm" translate="no" data-details></dl><p class="mt-2 text-sm text-muted" data-w="processing"></p>';
    sheetRow(ui.sheet).body.innerHTML = '<p class="text-sm text-muted" data-w="kept"></p>';
    sheetRow(ui.sheet).body.innerHTML = '<button type="button" class="btn-secondary btn-sm" data-mic-off data-w="micOff"></button>';
}

export function mountMicTest(root, { words = {}, keySlot = null, next = [], open = listen, env = browserOf() } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle') });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    const card = ui.element;
    card.dataset.tool = 'mic-test';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    for (const el of card.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    const segments = Array.from({ length: SEGMENTS }, (_, i) => {
        const bar = document.createElement('span');
        bar.dataset.ui = 'mic-segment';
        // The bars rise a little toward the loud end, like a meter's.
        bar.style.height = `${45 + (55 * i) / (SEGMENTS - 1)}%`;
        return bar;
    });
    $('[data-segments]').append(...segments);
    $('[data-next-links]').append(...next.flatMap(({ label, href }, i) => {
        const a = Object.assign(document.createElement('a'), { href, textContent: label, className: 'font-semibold text-cream underline underline-offset-2' });
        return i ? [document.createTextNode(' · '), a] : [a];
    }));

    let phase = 'idle'; // idle, asking, listening, recording, recorded, playing, problem
    let mic = null;
    let watch = voiceWatch();
    let lastAt = null;
    let recent = []; // { at, db } over the verdict window
    let peak = { level: 0, at: -Infinity };
    let lit = '';
    let note = null; // { name, at }
    let noise = []; // the room's level between phrases
    let clipAt = -Infinity; // the last moment a sample hit full scale
    let verdict = 'listening';
    let recorder = null;
    let take = null; // Float32Array, the last take
    let playing = null;
    let permission = null; // the permission status watched while blocked
    let playhead = 0;

    function zoneOf(position) {
        // The zones say what the word says: quiet under the voice line, a little quiet to GOOD_DBFS, good, too loud.
        return position >= LOUD_LINE ? 'loud' : position >= GOOD_LINE ? 'good' : position >= VOICE_LINE ? 'soft' : 'low';
    }

    function drawMeter(level, at) {
        const count = Math.round(level * SEGMENTS);
        if (level >= peak.level || at - peak.at > PEAK_HOLD) peak = { level, at };
        const held = Math.round(peak.level * SEGMENTS) - 1;
        const key = `${count}:${held}`;
        if (key === lit) return;
        lit = key;
        segments.forEach((bar, i) => {
            const zone = zoneOf((i + 0.5) / SEGMENTS);
            const on = i < count || i === held;
            const colour = { loud: 'bg-warn', good: 'bg-good', soft: 'bg-good/60', low: 'bg-cream' }[zone];
            // Unlit bars still show the zones, faintly, so you can see where to aim.
            const faint = { loud: 'bg-warn/20', good: 'bg-good/20', soft: 'bg-good/10', low: 'bg-raised' }[zone];
            bar.className = `rounded-sm transition-opacity motion-reduce:transition-none ${on ? colour : faint} ${on && i >= count ? 'opacity-60' : ''}`;
        });
    }

    function noteText() {
        return note && lastAt !== null && lastAt - note.at <= NOTE_FRESH ? note.name : '–';
    }

    /** What the pill, label and primary say, from the phase and the level. */
    function render() {
        card.dataset.phase = phase;
        card.dataset.level = mic ? verdict : '';
        const live = ['listening', 'recording', 'recorded', 'playing'].includes(phase);
        $('[data-intro]').hidden = phase !== 'idle' && phase !== 'asking';
        $('[data-problem]').hidden = phase !== 'problem';
        $('[data-take]').hidden = !live;
        $('[data-mic-row]').hidden = !live;
        $('[data-progress]').hidden = phase !== 'recording';
        $('[data-wave-box]').hidden = !take || phase === 'recording';
        $('[data-take-actions]').hidden = !take || phase === 'recording';
        $('[data-next]').hidden = !next.length || !live || watch.voiced < 0.4;
        text('[data-take-hint]', phase === 'recording' ? w('recordingHint') : take ? w('yourTake') : typeof MediaRecorder === 'undefined' ? w('noRecorder') : w('takeHint'));
        $('[data-mic-off]').disabled = !mic;
        if (phase === 'idle') ui.setStatus({ hint: w('idleHint') });
        else if (phase === 'asking') ui.setStatus({ tone: 'muted', text: w('asking') });
        else if (live) ui.setStatus({ tone: TONES[verdict], text: w(verdict), hint: w(`${verdict}Hint`) });
        ui.setLabel(live ? w('weHear') : phase === 'problem' ? w('whatToDo') : w('howItWorks'), live ? noteText() : '');
        const left = recorder ? Math.max(1, Math.ceil(TAKE_SECONDS - (audio().currentTime - recorder.began))) : TAKE_SECONDS;
        const primary = {
            idle: { label: w('start') }, problem: { label: w('start') }, asking: { label: w('asking'), active: true },
            listening: { label: w('record') }, recording: { label: fill(w('recording'), { n: left }), active: true },
            recorded: { label: w('play') }, playing: { label: w('stopPlaying'), active: true },
        }[phase];
        ui.setPrimary(primary);
        ui.primary.disabled = phase === 'asking' || (phase === 'listening' && typeof MediaRecorder === 'undefined');
    }

    function feed(frame) {
        if (!mic) return;
        const db = frame.loudness;
        const dt = lastAt === null ? 0 : Math.max(0, Math.min(0.25, frame.at - lastAt));
        lastAt = frame.at;
        watch = stepVoice(watch, db, dt);
        recent.push({ at: frame.at, db });
        while (recent.length && frame.at - recent[0].at > VERDICT_WINDOW) recent.shift();
        const loudest = Math.max(...recent.map((r) => (Number.isFinite(r.db) ? r.db : -Infinity)));
        verdict = levelWord(watch, loudest);
        if (frame.peak >= CLIP_PEAK) clipAt = frame.at;
        verdict = clipWord(verdict, frame.at, clipAt);
        if (frame.clear && frame.midi !== null) {
            const { name, octave } = pitchName(frame.midi);
            note = { name: `${name}${octave}`, at: frame.at };
        }
        if (Number.isFinite(db) && db < VOICE_DBFS) {
            noise.push(db);
            if (noise.length > 600) noise.shift();
        }
        drawMeter(meterLevel(db, CEIL), frame.at);
        if (recorder) $('[data-progress-fill]').style.transform = `scaleX(${Math.min(1, (audio().currentTime - recorder.began) / TAKE_SECONDS)})`;
        render();
    }

    async function start() {
        if (mic || phase === 'asking') return;
        phase = 'asking';
        render();
        try {
            const opened = await open({ onFrame: feed, deviceId: savedMic() });
            if (phase !== 'asking') {
                opened.stop(); // turned off while the mic was being asked for
                return;
            }
            mic = opened;
            // Nothing chosen on this site yet: the mic chosen last here or in the app, by name.
            const named = await preferredMic(mic.deviceId()).catch((error) => { console.warn('The mics could not be listed', error); return null; });
            if (named) await mic.use(named).catch((error) => console.warn('The remembered mic could not be opened', error));
            watchPermission(false);
            watch = voiceWatch();
            noise = [];
            clipAt = -Infinity;
            phase = 'listening';
            await showMic();
        } catch (error) {
            mic = null;
            problem(problemOf(error));
        }
        render();
    }

    /** The mic's name, and the picker when there's more than one; the details behind More. */
    async function showMic() {
        const track = mic?.stream?.()?.getAudioTracks?.()[0];
        const label = track?.label ?? '';
        text('[data-mic-name]', label);
        await showPicker($('[data-picker]'), mic.deviceId()).catch((error) => console.warn('The mic picker could not be filled', error));
        $('[data-mic-name]').hidden = !$('[data-picker-label]').hidden;
        $('[data-headset]').hidden = !headsetLike(label);
        const settings = track?.getSettings?.() ?? {};
        const rows = [
            [w('micLabel'), label || '–'],
            [w('sampleRate'), fill(w('hz'), { n: settings.sampleRate ?? audio().sampleRate })],
            [w('channels'), String(settings.channelCount ?? 1)],
        ];
        $('[data-details]').replaceChildren(...rows.flatMap(([k, v]) => [Object.assign(document.createElement('dt'), { className: 'text-muted', textContent: k }), Object.assign(document.createElement('dd'), { className: 'min-w-0 truncate font-semibold', textContent: v })]));
        showRoom();
    }

    function showRoom() {
        const sorted = [...noise].sort((a, b) => a - b);
        const median = sorted.length > 30 ? sorted[sorted.length >> 1] : null;
        const room = roomWord(median);
        const list = $('[data-details]');
        list.querySelector('[data-room]')?.remove();
        list.querySelector('[data-room-value]')?.remove();
        if (!room) return;
        list.append(
            Object.assign(document.createElement('dt'), { className: 'text-muted', textContent: w('room') }),
            Object.assign(document.createElement('dd'), { className: 'font-semibold', textContent: `${w({ quiet: 'roomQuiet', some: 'roomSome', noisy: 'roomNoisy' }[room])} (${Math.round(median)} dBFS)` }),
        );
        list.lastElementChild.dataset.roomValue = '';
        list.lastElementChild.previousElementSibling.dataset.room = '';
    }

    function problem(reason) {
        phase = 'problem';
        const key = PROBLEM_WORDS[reason] ?? 'unsupported';
        card.dataset.problem = reason;
        text('[data-problem-hint]', w(`${key}Hint`));
        const fixes = reason === 'denied' ? fixesToShow(env, (k) => !!words[k]) : [];
        $('[data-fix-list]').replaceChildren(...fixes.map((k) => Object.assign(document.createElement('li'), { textContent: words[k] })));
        $('[data-fixes]').hidden = !fixes.length;
        ui.setStatus({ tone: 'warn', text: w(key) });
        if (reason === 'denied') watchPermission(true);
    }

    // Blocked: when the visitor allows the mic (in the address bar or the system's settings) and comes
    // back, the test starts again by itself.
    function watchPermission(on) {
        if (permission) permission.onchange = null;
        permission = null;
        if (!on || !navigator.permissions?.query) return;
        navigator.permissions.query({ name: 'microphone' }).then((status) => {
            if (phase !== 'problem') return;
            permission = status;
            status.onchange = () => status.state === 'granted' && phase === 'problem' && start();
        }).catch((error) => console.warn('The mic permission cannot be watched here', error));
    }

    /** Record 5 seconds of the open mic, as Stackapella hears it, into this page's memory only. */
    async function record() {
        if (!mic || recorder || typeof MediaRecorder === 'undefined') return;
        stopPlaying();
        const c = audio();
        const rec = new MediaRecorder(mic.stream());
        const chunks = [];
        rec.ondataavailable = (event) => event.data.size && chunks.push(event.data);
        const stopped = new Promise((resolve) => { rec.onstop = resolve; });
        rec.start();
        recorder = { rec, began: c.currentTime };
        // The take ends on the audio clock, 5 seconds on (or sooner, on a tap).
        when(c.currentTime + TAKE_SECONDS).then(() => recorder?.rec === rec && rec.state !== 'inactive' && rec.stop());
        phase = 'recording';
        render();
        await stopped;
        recorder = null;
        try {
            const blob = new Blob(chunks, { type: rec.mimeType });
            const decoded = await c.decodeAudioData(await blob.arrayBuffer());
            take = decoded.getChannelData(0).slice();
            phase = mic ? 'recorded' : 'idle';
        } catch (error) {
            console.warn('The take could not be played back', error);
            take = null;
            phase = mic ? 'listening' : 'idle';
            text('[data-take-hint]', w('noPlayback'));
        }
        render();
        drawWave(0); // once its box shows
    }

    function stopRecording() {
        if (recorder?.rec.state === 'recording') recorder.rec.stop();
    }

    function token(name, fallback) {
        return getComputedStyle(card).getPropertyValue(name).trim() || fallback;
    }

    /** The take's waveform, played up to `at` (0 to 1) in the primary colour. */
    function drawWave(at) {
        const canvas = $('[data-wave]');
        if (!take || canvas.closest('[hidden]')) return;
        const ratio = window.devicePixelRatio || 1;
        const width = Math.max(1, Math.round(canvas.clientWidth * ratio));
        const height = Math.max(1, Math.round(canvas.clientHeight * ratio));
        if (canvas.width !== width || canvas.height !== height) Object.assign(canvas, { width, height });
        const g = canvas.getContext('2d');
        g.clearRect(0, 0, width, height);
        const bar = 3 * ratio;
        const columns = Math.floor(width / bar);
        const per = Math.max(1, Math.floor(take.length / columns));
        let top = 0.001;
        const peaks = Array.from({ length: columns }, (_, i) => {
            let p = 0;
            for (let j = i * per; j < Math.min(take.length, (i + 1) * per); j++) p = Math.max(p, Math.abs(take[j]));
            top = Math.max(top, p);
            return p;
        });
        const played = token('--primary', '#f6c');
        const rest = token('--color-muted', '#888');
        peaks.forEach((p, i) => {
            const h = Math.max(ratio * 2, (p / top) * height * 0.9);
            g.fillStyle = i / columns <= at ? played : rest;
            g.fillRect(i * bar, (height - h) / 2, bar - ratio, h);
        });
    }

    function play() {
        if (!take) return;
        stopPlaying();
        const c = audio();
        const began = c.currentTime;
        playing = sound(take);
        phase = 'playing';
        render();
        const tick = () => {
            if (!playing) return;
            drawWave((c.currentTime - began) / (take.length / c.sampleRate));
            playhead = requestAnimationFrame(tick);
        };
        tick();
        const mine = playing;
        mine.ended.then(() => {
            if (playing !== mine) return;
            playing = null;
            cancelAnimationFrame(playhead);
            drawWave(0);
            if (phase === 'playing') phase = 'recorded';
            render();
        });
    }

    function stopPlaying() {
        if (!playing) return;
        const was = playing;
        playing = null;
        cancelAnimationFrame(playhead);
        was.stop();
        drawWave(0);
        if (phase === 'playing') phase = 'recorded';
    }

    /** Lets the mic go (the take stays until the page closes, to play again). */
    function stop() {
        stopRecording();
        stopPlaying();
        watchPermission(false);
        mic?.stop();
        mic = null;
        lastAt = null;
        peak = { level: 0, at: -Infinity };
        lit = '';
        drawMeter(0, 0);
        phase = 'idle';
        render();
    }

    ui.onPrimary(() => {
        if (phase === 'idle' || phase === 'problem') start();
        else if (phase === 'listening') record();
        else if (phase === 'recording') stopRecording();
        else if (phase === 'recorded') play();
        else if (phase === 'playing') {
            stopPlaying();
            render();
        }
    });
    $('[data-record-again]').addEventListener('click', () => record());
    $('[data-mic-off]').addEventListener('click', () => {
        stop();
        ui.openSheet(false);
    });
    ui.more.addEventListener('click', showRoom);
    onPicked($('[data-picker]'), (id) => {
        stopRecording();
        mic?.use(id).then(() => {
            watch = voiceWatch();
            noise = [];
            clipAt = -Infinity;
            return showMic();
        }).catch((error) => console.warn('That mic could not be opened', error));
    });
    drawMeter(0, 0);
    render();
    return { start, record, play, stop, element: card };
}
