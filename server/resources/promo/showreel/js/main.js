// seek(t) and the preview player. The preview plays the soundtrack too, and while it does the
// AudioContext's clock is the film's clock, so picture and sound can't drift apart.

import { $, FORMAT, H, LIGHT, Q, RENDER, W, clamp } from './lib.js';
import { DUR, load, vtt } from './score.js';

await load();
if (Math.abs(window.DUR - DUR) > 1e-9) throw new Error(`showreel.html says DUR = ${window.DUR}, the score ${DUR}`);
const { beginFrame, grain } = await import('./stage.js');
const { film, CUTS, FAST, EDIT, captions } = await import('./film.js');

function seek(t) {
    t = clamp(t, 0, DUR - 1e-4);
    beginFrame();
    film(t);
    grain(t);
}
Object.assign(window, { seek, DUR, CUTS, FAST, EDIT, FRAME: [W, H], FORMAT, THEME: LIGHT ? 'light' : 'dark' });
// render.py asks for the soundtrack as a WAV, rendered on an OfflineAudioContext
window.renderWav = async () => (await import('./audio.js')).wav();
window.renderVtt = vtt;
window.captions = captions;

const frame = $('#frame'), stage = $('#stage');
function fit() { stage.style.transform = `scale(${Q.has('scale') ? +Q.get('scale') : frame.clientWidth / W})`; }
new ResizeObserver(fit).observe(frame);
fit();
await document.fonts.load('800 100px Figtree');
await document.fonts.load('700 100px Figtree');
await document.fonts.load('500 100px Figtree');
await document.fonts.ready;
window.__ready = true;

if (RENDER) {
    document.body.classList.add('render');
    seek(Q.has('t') ? +Q.get('t') : 0);
} else {
    player();
}

function player() {
    const playBtn = $('#play'), playIco = $('#playIco'), scrub = $('#scrub'), tc = $('#tc'), soundBtn = $('#sound');
    scrub.max = DUR;
    const ICON_PLAY = '<path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/>';
    const ICON_PAUSE = '<rect x="6" y="5" width="4" height="14" rx="1.2"/><rect x="14" y="5" width="4" height="14" rx="1.2"/>';
    const from = Q.has('from') ? +Q.get('from') : 0, to = Q.has('to') ? +Q.get('to') : DUR;
    let cur = Q.has('t') ? +Q.get('t') : from, playing = false, sound = true;
    // the clock: the AudioContext's while it plays (with or without sound: muted is a zero gain)
    let ctx = null, gain = null, src = null, track = null, base = 0;
    async function start() {
        ctx ??= new AudioContext();
        if (!track) { const { soundtrack } = await import('./audio.js'); track = await soundtrack(); gain = ctx.createGain(); gain.connect(ctx.destination); }
        await ctx.resume();
        gain.gain.value = sound ? 1 : 0;
        src = ctx.createBufferSource();
        src.buffer = track; src.loop = true; src.loopStart = from; src.loopEnd = to;
        src.connect(gain);
        const at = ctx.currentTime + 0.05;
        src.start(at, cur);
        base = at - cur;
    }
    function stop() { if (src) { src.stop(); src.disconnect(); src = null; } }
    const now = () => { const raw = ctx.currentTime - base; return raw < to ? raw : from + ((raw - from) % (to - from)); };
    async function setPlaying(p) {
        playing = p;
        playIco.innerHTML = p ? ICON_PAUSE : ICON_PLAY;
        playBtn.setAttribute('aria-label', p ? 'Pause' : 'Play');
        if (p) { if (cur >= to - 0.01) cur = from; await start(); } else { if (ctx && src) cur = now(); stop(); }
    }
    const ui = () => { scrub.value = cur; tc.textContent = `${cur.toFixed(2).padStart(5, '0')} / ${DUR.toFixed(2)}`; };
    playBtn.onclick = () => setPlaying(!playing);
    soundBtn.onclick = () => { sound = !sound; soundBtn.setAttribute('aria-pressed', sound); if (gain) gain.gain.value = sound ? 1 : 0; };
    scrub.oninput = () => { cur = +scrub.value; if (playing) { stop(); start(); } seek(cur); ui(); };
    $('#theme').onclick = switchTheme;
    function switchTheme() { const q = new URLSearchParams(location.search); q.set('theme', LIGHT ? 'dark' : 'light'); q.set('t', cur.toFixed(3)); location.search = q.toString(); }
    addEventListener('keydown', (e) => {
        if (e.code === 'Space') { e.preventDefault(); setPlaying(!playing); }
        if (e.code === 'KeyT') switchTheme();
        const step = (1 / 60) * (e.shiftKey ? 30 : 1);
        if (e.code === 'ArrowRight' && !playing) { cur = Math.min(DUR - 1e-3, cur + step); seek(cur); ui(); }
        if (e.code === 'ArrowLeft' && !playing) { cur = Math.max(0, cur - step); seek(cur); ui(); }
    });
    seek(cur); ui();
    const tick = () => { if (playing && src) { cur = now(); seek(cur); ui(); } requestAnimationFrame(tick); };
    requestAnimationFrame(tick);
}
