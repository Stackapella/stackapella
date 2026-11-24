// The landing's game-skin hero (pages/landing.blade.php, [data-cabinet]): the app's own Sing lane
// (js/embed/lane.js) singing the melody (the take file's part), with the page's HUD around it: the combo counted from
// the lane's note events (a hit adds one, a miss starts again), the current word lit, the progress
// and the time. The lane mounts only while the game skin shows (<html data-skin="game">), and takes
// the new look when the theme or the skin changes.
//
// It waits for a press (Hillel, 2026-10-31: "dont auto start the preview in game mode, instead have a 90s game style
// start button to start it"): the lane mounts paused on its first frame under a big PRESS START button (the
// slot's own "Press start" words; a real button, named for what it does), and that one press starts the take with
// sound. The play button pauses and plays as before; at the end PRESS START comes back. Turning Game mode off
// stops it. The button's blink stops with reduced motion.
//
// The game's own feel rides on it (Hillel: "popup 2x bonus, streak ... I'd like to see it in the demo"): the
// combo climbs on the notes the voice really lights (the lane hears it as Sing hears a singer), and the lessons'
// own rules and pop-up queue (game/combo.js) show "10 in a row!", "Bonus x2!" and the x2 chip only when that
// voice earns them, maybe never. The words are the app's own (its locale file, fetched on the first press). This
// device's Game effects switches are kept (pop-ups, HUD), and with reduced motion the pop-ups hold still.

const cabinet = document.querySelector('[data-cabinet]');
let loading = null;
let embed = null; // the lane module, loaded early so a press can wake the audio inside its click
let lane = null;
let startButton = null; // PRESS START, over the lane while it rests
let rules = null; // game/combo.js, loaded with the lane
let busyWords = null; // fills PRESS START's loading words once they arrive
let showBusy = null; // the loading state, at once on any press that starts the take
let popWords = null; // the app's pop-up words, { combo, rate, rateChip }, once fetched
const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)');

/** This device's Game effects (the app's switches, same site): pop-ups and the HUD unless turned off. */
function switches() {
    try {
        const kept = JSON.parse(localStorage.getItem('stackapella.gameEffects') || '{}') ?? {};
        return { popups: kept.all !== false && kept.popups !== false, hud: kept.all !== false && kept.hud !== false };
    } catch {
        return { popups: true, hud: true };
    }
}

/** The app's own pop-up words, in the page's language. */
async function loadWords() {
    if (popWords) return popWords;
    try {
        const lang = document.documentElement.lang || 'en';
        const all = await (await fetch(`/locales/${encodeURIComponent(lang)}.json`)).json();
        popWords = all.game?.popup ? { ...all.game.popup, loading: all.templates?.loading ?? '' } : null;
    } catch (error) {
        console.warn('No pop-up words: the combo count alone', error);
    }
    return popWords;
}
const fill = (message, values) => message.replace(/\{(\w+)\}/g, (_, name) => String(values[name] ?? ''));

const game = () => document.documentElement.dataset.skin === 'game';
const load = () => (loading ??= Promise.all([import('/js/embed/lane.js'), import('/js/game/combo.js')]).then(([module, combo]) => { rules = combo; return (embed = module); }));

function format(seconds) {
    const s = Math.max(0, Math.floor(seconds));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** A person's press on play: sound comes on inside the click, then the take plays or carries on. */
function press() {
    if (!lane) return;
    // Every press answers at once (docs/design-rules.md): loading until the take sounds.
    if (!lane.playing()) showBusy?.(true);
    embed?.unlock();
    loadWords().then(() => busyWords?.());
    if (lane.muted()) {
        const playing = lane.playing();
        lane.unmute(); // carries on with sound if it was playing
        if (!playing) lane.play();
        return;
    }
    lane.toggle();
}

async function start() {
    if (lane || !game()) return;
    const { mount } = await load();
    if (lane || !game()) return;
    // The voice in white with its part's glow (data-glow), so it reads over the part's own gems.
    lane = mount(cabinet.querySelector('[data-cab-lane]'), { take: cabinet.dataset.take, zoom: 1, trail: '#ffffff', trailGlow: cabinet.dataset.glow ?? 'var(--color-part-1)', muted: false });
    const play = cabinet.querySelector('[data-cab-play]');
    const sound = cabinet.querySelector('[data-cab-sound]');
    const combo = cabinet.querySelector('[data-cab-combo]');
    const progress = cabinet.querySelector('[data-cab-progress]');
    const time = cabinet.querySelector('[data-cab-time]');
    const beats = [...cabinet.querySelectorAll('[data-beat]')].map((el) => ({ el, beat: Number(el.dataset.beat) }));
    let run = 0;
    let at = 0; // the take's time, from the lane's clock
    // The pop-up slot and the bonus chip in the HUD, beside the combo.
    const hud = cabinet.querySelector('.s-cab-hud');
    const comboBox = cabinet.querySelector('.s-cab-combo');
    const slot = document.createElement('span');
    slot.className = 'ge-slot s-cab-slot';
    slot.dataset.cabSlot = '';
    const said = document.createElement('span');
    said.className = 'sr-only';
    said.setAttribute('role', 'status');
    const chip = document.createElement('span');
    chip.className = 'ge-rate s-cab-rate';
    chip.dataset.cabRate = '';
    chip.hidden = true;
    hud.insertBefore(slot, comboBox);
    comboBox.after(chip);
    hud.append(said);
    const queue = rules.makePopupQueue();
    let shownId = 0;
    let saidId = 0;
    const paint = () => {
        const now = queue.now;
        if ((now?.id ?? 0) !== shownId) {
            shownId = now?.id ?? 0;
            slot.replaceChildren();
            if (now && popWords) {
                const pop = document.createElement('span');
                pop.className = `ge-pop${now.kind === 'combo' || now.kind === 'rate' ? '' : ' ge-pop--pink'}${REDUCE.matches ? ' ge-pop--still' : ''}`;
                pop.setAttribute('aria-hidden', 'true');
                pop.dataset.gamePopup = now.kind;
                const burst = document.createElement('span');
                burst.className = 'ge-burst';
                const text = document.createElement('span');
                text.className = 'ge-pop-text';
                text.textContent = fill(popWords[now.kind] ?? '', { count: now.count ?? 0 });
                pop.append(burst, text);
                slot.append(pop);
            }
        }
        if (queue.say && queue.say.id !== saidId && popWords) {
            saidId = queue.say.id;
            said.textContent = fill(popWords[queue.say.kind] ?? '', { count: queue.say.count ?? 0 });
        }
    };
    const showRate = () => {
        const rate = rules.bonusRate(run);
        chip.hidden = !(switches().hud && popWords && rate >= 2);
        if (!chip.hidden) {
            chip.textContent = fill(popWords.rateChip, { count: rate });
            chip.setAttribute('aria-label', fill(popWords.rateChipLabel ?? popWords.rateChip, { count: rate }));
        }
    };
    // PRESS START: the slot's own words (its boot line), named for what it does (the play button and Play the take start it too).
    const words = cabinet.parentElement.querySelector('.s-cab-boot')?.textContent.trim() ?? '';
    startButton = document.createElement('button');
    startButton.type = 'button';
    startButton.className = 's-cab-press';
    startButton.dataset.cabPress = '';
    startButton.setAttribute('aria-label', words ? `${words}: ${play.dataset.labelPlay}` : play.dataset.labelPlay);
    const arrow = document.createElement('span');
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = '\u25B8 ';
    const label = document.createElement('span');
    label.textContent = words || play.dataset.labelPlay;
    startButton.append(arrow, label);
    // Pressed, it turns into a loading state at once (Hillel: "make sure once it's clicked it shows progress and
    // can't be clicked again"): busy, not pressable, the app's "Opening the song..." said and shown, a pixel bar
    // running, until the take plays (or, if it can't start, PRESS START again).
    const status = document.createElement('span');
    status.className = 'sr-only';
    status.setAttribute('role', 'status');
    cabinet.append(status);
    const busy = (on) => {
        cabinet.toggleAttribute('data-loading', on);
        startButton.toggleAttribute('data-busy', on);
        startButton.setAttribute('aria-busy', on ? 'true' : 'false');
        startButton.setAttribute('aria-disabled', on ? 'true' : 'false');
        if (!on) {
            label.textContent = words || play.dataset.labelPlay;
            status.textContent = '';
        } else busyWords();
    };
    showBusy = busy;
    busyWords = () => {
        if (!startButton.hasAttribute('data-busy')) return;
        // The page's own words, at once (the app's own "Opening the song..." only on a page without them).
        const opening = cabinet.dataset.loadingWords || popWords?.loading || '';
        label.textContent = opening;
        status.textContent = opening;
    };
    // The pointer going down is already a press: the audio wakes then, so the click answers at once.
    for (const el of [startButton, play, ...document.querySelectorAll('[data-cab-start]')]) el.addEventListener('pointerdown', () => embed?.unlock(), { passive: true });
    startButton.addEventListener('click', () => {
        if (startButton.hasAttribute('data-busy')) return;
        busy(true);
        press();
    });
    cabinet.querySelector('[data-cab-lane]').append(startButton);
    // Someone who might press (a hover, focus or touch): the voices are made now, so the press starts at once.
    for (const el of [cabinet, ...document.querySelectorAll('[data-cab-start]')]) {
        for (const name of ['pointerenter', 'focusin', 'pointerdown']) el.addEventListener(name, () => lane?.prepare?.(), { once: true, passive: true });
    }
    play.addEventListener('click', press);
    sound.addEventListener('click', press);
    // The hero's own button in the game skin ("Play the take") starts it too.
    for (const button of document.querySelectorAll('[data-cab-start]')) button.addEventListener('click', press);
    lane.on('state', ({ playing, muted, starting }) => {
        cabinet.toggleAttribute('data-playing', playing);
        play.setAttribute('aria-label', playing ? play.dataset.labelPause : play.dataset.labelPlay);
        sound.hidden = !(playing && muted);
        if (starting && !startButton.hasAttribute('data-busy')) busy(true); // started from Play or Play the take: the same loading state
        if (playing) {
            startButton.hidden = true;
            busy(false);
        } else if (starting === false && startButton.hasAttribute('data-busy')) busy(false); // it couldn't start: press again
        if (playing && at < 0.05) queue.clear(0);
    });
    lane.on('note', ({ hit, beat }) => {
        run = hit ? run + 1 : 0;
        combo.textContent = String(run);
        // The lessons' own rules on what this voice earned: "10 in a row!", then "Bonus x2!" from 20 (x3 from 30).
        if (switches().popups && rules.comboMilestone(run)) queue.offer(rules.rateRaised(run) ? { kind: 'rate', count: rules.bonusRate(run) } : { kind: 'combo', count: run }, at);
        showRate();
        paint();
        const now = beats.filter((w) => w.beat <= beat + 0.01).pop();
        for (const w of beats) {
            w.el.classList.toggle('s-sung', w.beat < (now?.beat ?? -1));
            w.el.classList.toggle('s-now', w === now);
        }
    });
    lane.on('time', ({ seconds, duration }) => {
        at = seconds;
        queue.tick(seconds);
        paint();
        progress.style.width = `${Math.min(100, (seconds / Math.max(duration, 0.001)) * 100)}%`;
        time.textContent = `${format(seconds)} / ${format(duration)}`;
    });
    lane.on('ended', () => {
        run = 0;
        combo.textContent = '0';
        for (const w of beats) w.el.classList.remove('s-sung', 's-now');
        startButton.hidden = false;
        at = 0;
        queue.clear(0);
        showRate();
        paint();
    });
}

if (cabinet) {
    // Turning Game mode on is a press: wake the audio inside it (the skin changes a moment later).
    for (const toggle of document.querySelectorAll('[data-game-toggle]')) {
        toggle.addEventListener(
            'click',
            () => {
                if (!game()) embed?.unlock();
            },
            { capture: true },
        );
    }
    if (game()) start();
    else if ('requestIdleCallback' in window) requestIdleCallback(() => load());
    else load();
    new MutationObserver(() => {
        if (!game()) {
            // Leaving the game skin stops the take; the embed restyles itself on a theme or skin change.
            if (lane?.playing()) lane.pause();
            return;
        }
        if (!lane) start();
        else if (startButton) startButton.hidden = lane.playing();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-skin'] });
}
