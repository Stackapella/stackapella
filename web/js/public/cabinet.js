// The landing's game-skin hero (pages/landing.blade.php, [data-cabinet]): the app's own Sing lane
// (js/embed/lane.js) singing the melody (the take file's part), with the page's HUD around it: the combo counted from
// the lane's note events (a hit adds one, a miss starts again), the current word lit, the progress
// and the time. The lane mounts only while the game skin shows (<html data-skin="game">), and takes
// the new look when the theme or the skin changes.
//
// It plays by itself (Hillel: "when you toggle game mode start playing the song game part
// automatically, if it needs to be quiet on launch then ask to click for sound"): turning Game mode
// on is a press, so the take starts with sound; opening the page already in game mode, it plays the
// picture with no sound and a "Press for sound" button, which carries on from where it is. Twice
// through, then it rests, and the play button plays it again. Turning Game mode off stops it. With
// reduced motion nothing starts by itself.

const cabinet = document.querySelector('[data-cabinet]');
const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)');
const PASSES = 2;
let loading = null;
let embed = null; // the lane module, loaded early so a press can wake the audio inside its click
let lane = null;
let pressed = false; // Game mode was turned on by a press on this page, so sound may start
let passes = 0; // times through on its own; 0 once a person has pressed play

const game = () => document.documentElement.dataset.skin === 'game';
const load = () => (loading ??= import('/js/embed/lane.js').then((module) => (embed = module)));

function format(seconds) {
    const s = Math.max(0, Math.floor(seconds));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** A person's press on play: sound comes on inside the click, then the take plays or carries on. */
function press() {
    passes = 0;
    if (!lane) return;
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
    lane = mount(cabinet.querySelector('[data-cab-lane]'), { take: cabinet.dataset.take, zoom: 1, trail: '#ffffff', trailGlow: cabinet.dataset.glow ?? 'var(--color-part-1)', muted: !pressed });
    const play = cabinet.querySelector('[data-cab-play]');
    const sound = cabinet.querySelector('[data-cab-sound]');
    const combo = cabinet.querySelector('[data-cab-combo]');
    const progress = cabinet.querySelector('[data-cab-progress]');
    const time = cabinet.querySelector('[data-cab-time]');
    const words = [...cabinet.querySelectorAll('[data-beat]')].map((el) => ({ el, beat: Number(el.dataset.beat) }));
    let run = 0;
    play.addEventListener('click', press);
    sound.addEventListener('click', press);
    // The hero's own button in the game skin ("Play the take") starts it too.
    for (const button of document.querySelectorAll('[data-cab-start]')) button.addEventListener('click', press);
    lane.on('state', ({ playing, muted }) => {
        cabinet.toggleAttribute('data-playing', playing);
        play.setAttribute('aria-label', playing ? play.dataset.labelPause : play.dataset.labelPlay);
        sound.hidden = !(playing && muted);
    });
    lane.on('note', ({ hit, beat }) => {
        run = hit ? run + 1 : 0;
        combo.textContent = String(run);
        const now = words.filter((w) => w.beat <= beat + 0.01).pop();
        for (const w of words) {
            w.el.classList.toggle('s-sung', w.beat < (now?.beat ?? -1));
            w.el.classList.toggle('s-now', w === now);
        }
    });
    lane.on('time', ({ seconds, duration }) => {
        progress.style.width = `${Math.min(100, (seconds / Math.max(duration, 0.001)) * 100)}%`;
        time.textContent = `${format(seconds)} / ${format(duration)}`;
    });
    lane.on('ended', () => {
        run = 0;
        combo.textContent = '0';
        for (const w of words) w.el.classList.remove('s-sung', 's-now');
        if (passes > 0 && passes < PASSES && game()) {
            passes += 1;
            lane.play();
        }
    });
    autoplay();
}

/** Plays by itself as the game comes on: with sound after a press, else the picture alone. */
function autoplay() {
    if (!lane || !game() || REDUCE.matches || lane.playing()) return;
    passes = 1;
    if (pressed && lane.muted()) lane.unmute();
    else if (!pressed && !lane.muted()) lane.mute(); // came on from elsewhere (another tab): quiet until a press
    lane.play();
}

if (cabinet) {
    // Turning Game mode on is a press: wake the audio inside it (the skin changes a moment later).
    for (const toggle of document.querySelectorAll('[data-game-toggle]')) {
        toggle.addEventListener(
            'click',
            () => {
                if (game()) return;
                pressed = true;
                embed?.unlock();
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
            pressed = false;
            return;
        }
        if (!lane) start();
        else autoplay();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-skin'] });
}
