// HARMONY, MADE BY YOU: the film. One world (Amazing Grace's four lanes of glass gems, the share card
// and the end card, each at its own place in it), the real app in a drawn device, a split screen of the four
// singers drawn over it, and a director who cuts between shots on the song's beat. Everything is a
// pure function of t, so any frame can be rendered alone.

import { E, $, CX, CY, FORMAT, H, LIGHT, Q, W, bump, clamp, hash, lerp, mk, range, set, spring, vis } from './lib.js';
import { LANG, LINE_T, SITE, tr } from './words.js';
import { CUE, CUT, DUR, END as LAST, FROM, S, SC, SEASONAL, SPB, VERSE, beatAt, creditLine, sec, sung, voicing } from './score.js';
import { loadLoud, loud, onsetAt } from './loud.js';
import { PART_INK, PART_VAR, T, glow, project, setCam, sparkles, specks } from './stage.js';

const W0 = $('#world');
const hud = $('#hud');
const flashEl = $('#flash');
const lens = $('#lens');
const washEl = $('#wash');
const splitEl = $('#split');
const svgTick = '<svg class="tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
const svgCheck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M7.5 12.5l3 3 6-6.5"/></svg>';
const NAMES = ['Soprano', 'Alto', 'Tenor', 'Bass'].map(tr);
const RANGES = ['D4 to D5', 'B3 to B4', 'G3 to C4', 'G2 to D3'];
const FRIENDS = [tr('You'), 'Maya', 'Sam', 'Leo'];
const clock = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

/* ================= the lanes ================= */
// One lane per part, in the part's colour: x is time (PXB a beat), y is pitch (SP a semitone). The
// gems are big and chunky, as in the arcade lanes: each is taller than two semitones.
const PXB = 200, SP = 26, GH = 64, GAP = 70, GW = 14; // v5: the lanes closer, so the whole stack fits under the line
const X = (b) => b * PXB;
const RANGE = CUT === 'carols' ? [[64, 74], [59, 64], [55, 62], [43, 48]] : CUT === 'hanukkah' ? [[59, 75], [56, 70], [51, 61], [45, 54]] : [[62, 74], [59, 71], [55, 60], [43, 50]];
const REAL = SEASONAL ? '' : tr('a real voice'); // a season cut's voices are the app's guide, not recordings
const LANES = (() => {
    let top = 0;
    return RANGE.map(([lo, hi], i) => {
        const h = (hi - lo + 3) * SP, lane = { i, lo, hi, h, top };
        top += h + GAP;
        return lane;
    });
})();
const STACK_H = LANES[3].top + LANES[3].h;
const yOf = (lane, p) => lane.top + (lane.hi + 1.5 - p) * SP;
const laneSet = mk('div', 'set', W0);
const laneEls = LANES.map((lane) => {
    const el = mk('div', 'lane', laneSet);
    const lines = mk('div', 'lines', el);
    lines.style.cssText = `left:${X(-2.4)}px;width:${X(LAST + 6)}px;top:${lane.top}px;height:${lane.h}px`;
    // v5: the lane's own colour, once its voice has joined (it breathes with that singer's loudness), and the
    // sweep of colour that ignites it on the downbeat
    // an empty lane is a debossed glass track, named, waiting for its voice
    const track = mk('div', 'lane-track', el);
    track.style.cssText = `left:${X(-2.4)}px;width:${X(LAST + 6)}px;top:${lane.top}px;height:${lane.h}px`;
    const tint = mk('div', 'lane-tint', el);
    tint.style.cssText = `left:${X(-2.4)}px;width:${X(LAST + 6)}px;top:${lane.top}px;height:${lane.h}px;--lc:${PART_VAR[lane.i]}`;
    const sweep = mk('div', 'lane-sweep', el);
    sweep.style.cssText = `left:0;width:900px;top:${lane.top - 20}px;height:${lane.h + 40}px;--lc:${PART_VAR[lane.i]}`;
    for (let p = lane.lo - 1; p <= lane.hi + 1; p++) mk('i', p % 12 === 0 ? 'c' : '', lines).style.top = `${(lane.hi + 1.5 - p) * SP - 1}px`;
    const rim = mk('div', 'lane-rim', el);
    rim.style.cssText = `left:${X(-2.4)}px;width:${X(LAST + 6)}px;top:${lane.top}px;height:${lane.h}px;--lc:${PART_VAR[lane.i]}`;
    const name = mk('div', 'lane-name', el);
    name.style.cssText = `left:${X(-2.2)}px;top:${lane.top + 8}px;color:${PART_VAR[lane.i]}`;
    mk('span', null, name, NAMES[lane.i]);
    // the tune is a real singer's recording (v5: "Soprano, a real voice"); the others wait, named
    // a small level meter beside the name: the singer's real loudness, so a lit lane is seen to be sound
    const meter = mk('div', 'lane-meter', name);
    const bars = [0, 1, 2, 3].map(() => mk('i', null, meter));
    const who = mk('small', null, name, lane.i === 0 ? REAL : '');
    const gems = S.parts[lane.i].map((n, k) => {
        const g = mk('div', 'gem gem-bar', el);
        g.style.cssText = `left:${X(n.beat) + GW / 2}px;top:${yOf(lane, n.pitch) - GH / 2}px;width:${n.length * PXB - GW}px;height:${GH}px;--gem:${PART_VAR[lane.i]};--gem-ink:${PART_INK[lane.i]}`;
        g.innerHTML = `<span class="w">${n.lyric ?? ''}</span>`;
        if (lane.i === 0) g.insertAdjacentHTML('beforeend', svgTick);
        return { el: g, n, k, tick: g.querySelector('.tick') };
    });
    return { el, lines, name, who, gems, lane, tint, sweep, track, rim, meter, bars };
});
const beamEl = mk('div', 'beam', laneSet);
const chipEl = mk('div', 'chip', laneSet);
chipEl.innerHTML = `<i></i><span>${tr('Slide up')}</span>`;
chipEl.style.font = '700 30px/1 var(--font)';

// The pitch camera (ux-spec 5.7c), as a pure function: the frame's centre follows the notes from
// 0.4 s back to 3 s ahead, averaged over a beat each way so it glides and never jumps.
function pitchCentre(b) {
    let sum = 0;
    for (let k = -4; k <= 4; k++) {
        const c = b + k * 0.25, ahead = S.parts[0].filter((n) => n.beat + n.length > c - 0.64 && n.beat < c + 4.8);
        sum += (Math.min(...ahead.map((n) => n.pitch)) + Math.max(...ahead.map((n) => n.pitch))) / 2;
    }
    return yOf(LANES[0], sum / 9);
}

/* ================= the mark ================= */
// web/icons/icon.svg: four bars in a 512 square, x 109, y 172 + 46.2 a bar, 29.4 high.
const MARK_W = [294, 217.56, 258.72, 182.28];
const markRects = (cx, cy, k) => MARK_W.map((w, i) => ({ x: cx - (294 * k) / 2, y: cy - (168 * k) / 2 + i * 46.2 * k, w: w * k, h: 29.4 * k }));
const morphEls = [0, 1, 2, 3].map((i) => {
    const g = mk('div', 'gem gem-bar', laneSet);
    g.style.cssText = `left:0;top:0;--gem:${PART_VAR[i]}`;
    return g;
});
function morphBar(el, i, a, b, u, colour, radius, land = 0) {
    const x = lerp(a.x, b.x, u), y = lerp(a.y, b.y, u), w = lerp(a.w, b.w, u), h = lerp(a.h, b.h, u);
    set(el, 'transform', `translate(${x.toFixed(2)}px,${y.toFixed(2)}px)${land ? ` scale(${(1 + land).toFixed(4)},${(1 - land * 0.8).toFixed(4)})` : ''}`);
    set(el, 'width', `${w.toFixed(2)}px`);
    set(el, 'height', `${h.toFixed(2)}px`);
    set(el, 'borderRadius', `${(radius ?? lerp(20, h / 2, clamp(u * 1.6))).toFixed(2)}px`);
    set(el, '--gem', `color-mix(in oklab, ${PART_VAR[i]} ${((1 - colour) * 100).toFixed(1)}%, var(--m${i + 1}))`);
}
const gemRect = (i, k) => { const n = S.parts[i][k], lane = LANES[i]; return { x: X(n.beat) + GW / 2, y: yOf(lane, n.pitch) - GH / 2, w: n.length * PXB - GW, h: GH }; };
const noteIndex = (i, beat) => S.parts[i].findIndex((n) => n.beat === beat);
const lerp4 = (a, b, u) => ({ x: lerp(a.x, b.x, u), y: lerp(a.y, b.y, u), w: lerp(a.w, b.w, u), h: lerp(a.h, b.h, u) });

/* ================= the end card (in the lane set's world, where "see." is sung) ================= */
const seeNote = S.parts[0].at(-1);
const END = { cx: X(seeNote.beat + seeNote.length / 2), cy: STACK_H / 2 - 40 };
const endEl = mk('div', 'grp', laneSet);
endEl.id = 'endc';
// Laid out for the frame: on the wide one the headline is one line; portrait and square break it in two.
const ENDS = {
    wide: { mark: -250, k: 1.55, h: 520, p: 682, site: 790, br: '' },
    vertical: { mark: -470, k: 1.5, h: CY - 200, p: CY + 120, site: CY + 214, br: '<br>' },
    square: { mark: -300, k: 1.15, h: CY - 140, p: CY + 92, site: CY + 170, br: '<br>' },
    thumb: { mark: 0, k: 1, h: 0, p: 0, site: 0, br: '' },
};
const EL = ENDS[FORMAT];
const CARD = { carols: ['Christmas carols,', 'stackapella.com/songs/christmas-carols'], hanukkah: ['Hanukkah songs,', 'stackapella.com/songs/jewish-songs'] }[CUT];
endEl.innerHTML = CARD ? `<h2>${CARD[0]}${EL.br} <em>part by part.</em></h2><p></p><p class="site">${CARD[1]}</p><p class="credit"></p>`
    : `<h2>${tr('Harmony,')}${EL.br} <em>${tr('made by you.')}</em></h2><p></p><p class="site">${SITE}</p><p class="credit"></p>`;
endEl.style.cssText = `left:${END.cx - CX}px;top:${END.cy - CY}px;width:${W}px;height:${H}px`;
const [endH, endP, endSite, endCredit] = endEl.children;
// v5: the card is the mark, the headline, the address and the credit (the tagline is a line at 0:54)
endH.style.top = `${EL.h}px`; endP.style.display = 'none'; endSite.style.top = `${EL.p}px`;
// the real voices' credit, as the app gives it (score.js creditLine, from the catalogue)
endCredit.style.top = `${EL.p + 76}px`;
endCredit.textContent = creditLine();
const END_MARK = markRects(END.cx, END.cy + EL.mark, EL.k);
// the opening note as the fold leaves it: what the pre-roll's camera sees, three times its size
const OPEN = { zoom: 3, w: (PXB - GW) * 3, h: GH * 3, r: 20 * 3 };

/* ================= the share card ================= */
const SH = { x: -20000, y: 0, w: 1400, h: 780 };
const shSet = mk('div', 'set share', W0);
const shCard = mk('div', 'card', shSet);
shCard.style.cssText = `left:${SH.x}px;top:${SH.y}px;width:${SH.w}px;height:${SH.h}px`;
shCard.innerHTML = `<h3 style="left:72px;top:72px">Amazing Grace</h3><div class="meta" style="left:74px;top:160px">${tr('Sing together')}</div>`;
const pic = mk('div', 'pic', shCard);
pic.style.cssText = 'left:72px;top:226px;width:1256px;height:250px';
[0, 1, 2, 3].forEach((i) => S.parts[i].forEach((n) => {
    const b = mk('b', 'gem gem-bar', pic);
    b.style.cssText = `left:${(n.beat / LAST) * 1256 + 2}px;top:${i * 62}px;width:${(n.length / LAST) * 1256 - 4}px;height:44px;border-radius:22px;--gem:${PART_VAR[i]}`;
}));
const shChip = mk('div', 'chip', shCard);
shChip.style.cssText = 'left:72px;top:520px;--chip:var(--accent)';
shChip.innerHTML = `<i></i><span>${tr('A link for each part')}</span>`;
const discs = [0, 1, 2, 3].map((i) => {
    const d = mk('div', 'gem disc who', shCard, NAMES[i][0]);
    d.style.cssText = `left:${590 + i * 190}px;top:600px;--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}`;
    const l = mk('div', 'who-l', shCard, i ? NAMES[i] : tr('You'));
    l.style.cssText = `left:${646 + i * 190}px;top:726px`;
    return { d, l };
});
const toast = mk('div', 'toast', shSet);
toast.innerHTML = `${svgCheck}<span>${tr('Link copied.')}</span>`;
toast.style.cssText = `left:${SH.x + SH.w - 400}px;top:${SH.y + 62}px`;
const shRing = mk('div', 'ring', shCard);

/* ================= the split screen: the four singers, each a take of you ================= */
const QUAD = { wide: { w: 954, h: 384, top: 300 }, vertical: { w: 410, h: 500, top: 540, left: 64 }, square: { w: 534, h: 400, top: 268 }, thumb: { w: 1, h: 1, top: 0 } }[FORMAT];
// (the vertical cut keeps its right 180 px and bottom 300 px clear for the platforms' buttons)
const QL = QUAD.left ?? 0, QR = QUAD.left != null ? QL + QUAD.w + 16 : W - QUAD.w;
const QPOS = [[QL, QUAD.top], [QR, QUAD.top], [QL, QUAD.top + QUAD.h + 12], [QR, QUAD.top + QUAD.h + 12]];
// the beam stands in from the quadrant's edge, clear of the page's buttons in the frame's corners
const QB0 = 34, QPX = 170, QBEAM = Math.round(QUAD.w * 0.45), QTOP = () => 104, QH = QUAD.h - 144;
const quads = [0, 1, 2, 3].map((i) => {
    const q = mk('div', 'q', splitEl);
    q.style.cssText = `left:${QPOS[i][0]}px;top:${QPOS[i][1]}px;width:${QUAD.w}px;height:${QUAD.h}px;--q:${PART_VAR[i]}`;
    const inner = mk('div', 'qi', q);
    const head = mk('div', 'qh', inner);
    head.innerHTML = `<span class="gem disc" style="--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}">${NAMES[i][0]}</span>${NAMES[i]}<span class="you" style="background:${PART_VAR[i]};color:${PART_INK[i]}">${tr('You')}</span>`;
    const strip = mk('div', 'strip', inner);
    const [lo, hi] = RANGE[i];
    const gems = S.parts[i].filter((n) => n.beat + n.length > QB0 && n.beat < QB0 + 12).map((n) => {
        const g = mk('div', 'gem gem-bar', strip);
        g.style.cssText = `left:${(n.beat - QB0) * QPX + 6}px;top:${QTOP(i) + ((hi + 1 - n.pitch) / (hi - lo + 2)) * QH - 32}px;width:${n.length * QPX - 12}px;height:64px;--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}`;
        g.textContent = n.lyric && n.lyric !== 'oo' ? n.lyric : '';
        return { el: g, n };
    });
    const beam = mk('div', 'beam', inner);
    beam.style.cssText = `left:${QBEAM}px;top:${QTOP(i) - 10}px;height:${QH + 20}px;--beam:${PART_VAR[i]}`;
    return { q, inner, strip, gems, you: head.querySelector('.you') };
});

/* ================= the line each shot carries ================= */
const cap = mk('div', null, hud);
cap.id = 'cap';
cap.innerHTML = '<div class="eb"><i></i><span></span></div><div class="ln"><span></span></div>';
const capEb = cap.querySelector('.eb span'), capLn = cap.querySelector('.ln span'), capDot = cap.querySelector('.eb i');
const ACTS = { learn: ['Learn', 0], stack: ['Stack', 1], play: ['Game mode', 2], share: ['Share', 3], songs: ['Songs', 3], courses: ['Courses', 0], tools: ['Tools', 1], choirs: ['Choirs', 2], open: ['Open source', 1], daily: ['Every day', 3] };

/* ================= the world ================= */
// v5: each joining lane's sweep peaks as its voice is first heard (loud.js onsetAt, the real recording's
// onset as the mix lets it in), two frames early so the eye meets the ear; the lane lights a quarter in
const ANTICIPATE = 1 / 30, SWEEP = 0.48;
const peakAt = (i) => (i === 0 ? -99 : onsetAt(i) - ANTICIPATE);
const litAt = (i) => (i === 0 ? -99 : peakAt(i) - SWEEP / 4);
const sungBy = (i, t) => (t >= sec(CUE.friends[0]) - 0.01 && t < sec(CUE.end) ? FRIENDS[i] : i === 0 ? tr('You') : tr('You, again'));

function lanes(t, o) {
    // o: { lt: the lanes' own clock (the pre-roll runs it below zero), fade: lines and gems, all:
    // every lane in, quiet: no rim or chip, hide: a gem per lane the mark has taken }
    const lt = o.lt, b = beatAt(lt);
    const singing = voicing(b) && lt >= 0;
    laneEls.forEach((L, i) => {
        // v5: every lane stands from the first frame, the three still to come empty and named; each ignites
        // as its voice starts to sing, a sweep of its colour across it, and its gems follow the sweep
        const ign = peakAt(i);
        const lit = o.all || lt >= litAt(i);
        vis(L.el, true);
        set(L.el, 'transform', '');
        set(L.el, 'opacity', String(o.fade ?? 1));
        set(L.lines, 'opacity', String((o.lines ?? 1) * (lit ? 1 : 0.38)));
        set(L.name, 'opacity', String((o.lines ?? 1) * (lit ? 1 : 0.62)));
        set(L.track, 'opacity', String((o.lines ?? 1) * (lit ? 0.45 : 1)));
        // the name stays at the frame's left edge, at a size that reads, however far the camera is
        if (o.cam) {
            // its name pops as the lane lights
            const dn = lt - litAt(i), pop = i > 0 && !o.all && dn >= 0 ? 1 + 0.16 * Math.exp(-dn / 0.14) * Math.cos(dn * 20) : 1;
            const z = o.cam.zoom ?? 1, left = (o.cam.x ?? CX) - CX / z + 44 / z, k = clamp(0.95 / z, 1, 1.9) * pop;
            set(L.name, 'left', `${Math.max(X(-2.2), left).toFixed(1)}px`);
            set(L.name, 'transform', `scale(${k.toFixed(3)})`);
            // close on a note (the opening, the pre-roll) the names wait until the camera pulls back
            set(L.name, 'opacity', String((o.lines ?? 1) * (lit ? 1 : 0.62) * clamp((1.5 - z) / 0.6)));
        }
        const sw = o.all || i === 0 ? 1 : range(lt, ign - SWEEP / 2, ign + SWEEP / 2);
        const sweepX = X(beatAt(ign)) + lerp(-1500, 1700, E.io(sw));
        vis(L.sweep, sw > 0 && sw < 1);
        if (sw > 0 && sw < 1) { set(L.sweep, 'transform', `translateX(${(sweepX - 450).toFixed(1)}px)`); set(L.sweep, 'opacity', (Math.sin(sw * Math.PI) * 0.75).toFixed(3)); }
        const lv = lit ? loud(i, b) : 0, flare = lit && i > 0 ? Math.exp(-Math.max(0, lt - ign) / 0.35) : 0;
        set(L.tint, 'opacity', (lit ? (o.lines ?? 1) * ((LIGHT ? 0.05 : 0.07) + (LIGHT ? 0.05 : 0.09) * lv + 0.12 * flare) : 0).toFixed(3));
        // a lit lane's glass edge takes its colour, brighter as its singer sings louder
        set(L.rim, 'opacity', (lit ? (o.lines ?? 1) * clamp(0.22 + 0.4 * lv + 0.6 * flare) : 0).toFixed(3));
        vis(L.meter, lit && lt >= 0);
        if (lit && lt >= 0) L.bars.forEach((e, k) => set(e, 'height', `${(8 + 30 * clamp(loud(i, b - k * 0.14) * [0.7, 1, 0.82, 0.55][k])).toFixed(1)}px`));
        const who = o.names ? sungBy(i, t) : i === 0 ? REAL : '';
        if (L.who._t !== who) { L.who._t = who; L.who.textContent = who; }
        for (const g of L.gems) {
            const n = g.n, s0 = sec(n.beat), s1 = sec(n.beat + n.length);
            let show = true, scale = 1, gl = 0.25, fill = 0, now = false, tick = false, alpha = 1;
            if (i === 0) {
                // the tune appears as gems along the lane, one after another, from the first
                const appear = g.k === 0 ? -99 : 0.32 + g.k * 0.07;
                show = lt >= appear;
                scale *= g.k === 0 ? 1 : E.outBack(range(lt, appear, appear + 0.32));
                alpha = g.k === 0 ? 1 : clamp((lt - appear) / 0.12);
                const hit = n.hit != null ? sec(n.hit) : null;
                fill = sung(n, b);
                now = lt >= s0 && lt < s1;
                // the next note breathes for 1.2 s before it starts
                if (lt < s0 && lt > s0 - 1.2) gl = 0.3 + 0.35 * (0.5 - 0.5 * Math.cos(((lt - s0 + 1.2) / 1.2) * Math.PI * 2));
                if (now) gl = 0.75;
                if (hit != null && lt >= hit) {
                    const d = lt - hit;
                    scale *= 1 + 0.1 * Math.exp(-d / 0.1) * Math.cos(d * 30);
                    gl = Math.max(gl, 0.5 + 0.5 * Math.exp(-d / 0.18));
                    tick = true;
                    if (!now) gl = 0.4;
                }
                // the opening note's own breath, before its pickup sounds and while it does
                if (g.k === 0) {
                    gl = Math.max(gl, 0.35 + 0.3 * (0.5 - 0.5 * Math.cos(clamp((lt + 0.625) / 0.625) * Math.PI)));
                    if (lt >= 0 && lt < 0.4) gl = Math.max(gl, 1 - lt * 1.2);
                }
            } else {
                now = lt >= s0 && lt < s1;
                // the joined voice's own loudness lights its note
                gl = now ? 0.35 + 0.6 * loud(i, b) : 0.25;
                fill = clamp((b - n.beat) / n.length);
                // a part still waiting shows its notes as faint dashed outlines; the sweep turns each to glass
                const solid = lit && (sw >= 1 || X(n.beat) <= sweepX);
                if (g.ghost !== !solid) { g.ghost = !solid; g.el.classList.toggle('ghost', !solid); }
                if (!solid) { gl = 0; fill = 0; alpha = 0.5 * (o.lines ?? 1); }
                else if (sw < 1) scale *= lerp(0.8, 1, E.outBack(clamp((sweepX - X(n.beat)) / 500)));
            }
            if (o.hide && o.hide[i] === g.k) show = false;
            vis(g.el, show);
            if (!show) continue;
            set(g.el, 'transform', scale === 1 ? '' : `scale(${scale.toFixed(4)})`);
            set(g.el, '--glow', clamp(gl).toFixed(3));
            set(g.el, '--fill', fill.toFixed(3));
            set(g.el, 'opacity', alpha.toFixed(3));
            // the note now has a cream rim, which fades in and out so the loop's seam can't pop
            set(g.el, '--rim', i === 0 && !o.quiet && now ? clamp(Math.min(lt - s0, s1 - lt) / 0.08).toFixed(3) : '0');
            if (g.tick) set(g.tick, 'opacity', tick ? '1' : '0');
        }
    });
    // one playhead across every lane that is in: the parts are exactly in time
    const last = o.all ? 3 : [0, 1, 2, 3].filter((i) => lt >= litAt(i)).pop();
    vis(beamEl, lt > 0 && !o.noBeam);
    if (lt > 0 && !o.noBeam) {
        set(beamEl, 'left', `${X(b)}px`);
        set(beamEl, 'top', '-40px');
        // it grows down into each lane as the lane lights
        let foot = LANES[0].top + LANES[0].h;
        for (let i = 1; i < 4; i++) foot = lerp(foot, LANES[i].top + LANES[i].h, o.all ? 1 : E.outExpo(range(lt, litAt(i), litAt(i) + 0.4)));
        set(beamEl, 'height', `${(foot + 80).toFixed(1)}px`);
        set(beamEl, '--beam', singing || last ? 'var(--p1)' : 'var(--cream)');
        set(beamEl, 'opacity', String((o.lines ?? 1) * clamp(lt / 0.12)));
    }
    // "Slide up", while the voice is under "grace"
    const grace = S.parts[0][4];
    const ck = SEASONAL ? 0 : bump(b, grace.beat + 0.12, grace.beat + 1.1);
    vis(chipEl, ck > 0.01 && !o.quiet);
    if (ck > 0.01) {
        set(chipEl, 'transform', `translate(${X(b) + 50}px,${yOf(LANES[0], S.voice(b)) + 52}px) scale(${(0.8 + 0.2 * E.outBack(range(b, grace.beat + 0.12, grace.beat + 0.4))).toFixed(3)})`);
        set(chipEl, 'opacity', clamp(ck * 2.2).toFixed(3));
    }
}

/** The voice: a ribbon behind the playhead and a bead at it, drawn in screen space. */
function voiceTrail(lt, k = 1) {
    k *= clamp(lt / 0.12);
    if (k <= 0) return;
    const b = beatAt(lt), ctx = $('#fx').getContext('2d');
    const pts = [];
    for (let x = Math.max(0, b - 3.2); x <= b + 1e-6; x += 0.025) pts.push([x, voicing(x) ? S.voice(x) : null]);
    const s = project(X(b), yOf(LANES[0], 68), 0)[2];
    ctx.save();
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    for (const pass of [0, 1]) {
        ctx.beginPath();
        let pen = false;
        for (const [x, p] of pts) {
            if (p == null) { pen = false; continue; }
            const [sx, sy] = project(X(x), yOf(LANES[0], p), 2);
            if (!pen) ctx.moveTo(sx, sy); else ctx.lineTo(sx, sy);
            pen = true;
        }
        ctx.strokeStyle = pass ? `rgba(${T.cream},${0.9 * k})` : `rgba(${T.cream},${0.16 * k})`;
        ctx.lineWidth = (pass ? 6 : 22) * s;
        ctx.globalCompositeOperation = pass ? 'source-over' : T.add;
        ctx.stroke();
    }
    ctx.restore();
    if (voicing(b)) {
        const [sx, sy, s2] = project(X(b), yOf(LANES[0], S.voice(b)), 4);
        glow(sx, sy, 60 * s2, T.cream, 0.5 * k);
        ctx.save();
        ctx.fillStyle = `rgba(${T.cream},${k})`;
        ctx.beginPath(); ctx.arc(sx, sy, 14 * s2, 0, 6.283); ctx.fill();
        ctx.restore();
    }
}

function hits(lt) {
    for (const g of laneEls[0].gems) {
        if (g.n.hit == null) continue;
        const d = lt - sec(g.n.hit);
        if (d < 0 || d > 0.6) continue;
        const r = gemRect(0, g.k), [sx, sy, s] = project(r.x + r.w / 2, r.y + r.h / 2, 0);
        sparkles(sx, sy, d, T.parts[0], s * 1.4, g.k);
        glow(sx, sy, 200 * s, T.parts[0], (LIGHT ? 0.25 : 0.55) * Math.exp(-d / 0.15));
    }
}

/** A lane igniting, in screen space: light rides the sweep's head, and the voice's first note sparkles as it sounds. */
function ignitions(lt) {
    for (let i = 1; i < 4; i++) {
        const ign = peakAt(i), sw = range(lt, ign - SWEEP / 2, ign + SWEEP / 2), cy = centreOf(i);
        if (sw > 0 && sw < 1) {
            const [sx, sy, s] = project(X(beatAt(ign)) + lerp(-1500, 1700, E.io(sw)), cy, 0);
            glow(sx, sy, 520 * s, T.parts[i], (LIGHT ? 0.3 : 0.5) * Math.sin(sw * Math.PI));
        }
        const on = onsetAt(i), d = lt - on;
        if (d < 0 || d > 0.6) continue;
        const n = S.parts[i].find((x) => sec(x.beat + x.length) > on + 0.02) ?? S.parts[i][0];
        const [sx, sy, s] = project(X(beatAt(on)), yOf(LANES[i], n.pitch), 0);
        sparkles(sx, sy, d, T.parts[i], s * 1.5, i + 3, 10);
        glow(sx, sy, 320 * s, T.parts[i], (LIGHT ? 0.3 : 0.6) * Math.exp(-d / 0.2));
    }
}

/** A fingertip that glides to `at` (card px) and taps there at `tap` seconds. */
function fingertip(p, r, t, path) {
    let i = path.findLastIndex((s) => t >= s.t - 0.32);
    if (i < 0) i = 0;
    const to = path[i], from = path[Math.max(0, i - 1)], u = E.io(range(t, to.t - 0.32, to.t - 0.04));
    const x = lerp(from.x, to.x, i ? u : 1), y = lerp(from.y, to.y, i ? u : 1);
    const press = 1 - 0.18 * bump(t, to.t - 0.06, to.t + 0.14);
    set(p, 'transform', `translate(${x.toFixed(1)}px,${y.toFixed(1)}px) scale(${press.toFixed(3)})`);
    const d = t - to.t;
    const clicks = to.click !== false && d >= 0 && d < 0.5; // a point it only starts from makes no ring
    vis(r, clicks);
    if (clicks) {
        set(r, 'transform', `translate(${to.x}px,${to.y}px) scale(${(1 + 2.2 * E.out(d / 0.5)).toFixed(3)})`);
        set(r, 'opacity', (0.8 * (1 - d / 0.5)).toFixed(3));
    }
}

function share(t) {
    // close on the parts, the song's strip at the card's top would sit under the line: it goes
    set(pic, 'opacity', (1 - E.io(range(t, sec(CUE.friends[0] - 0.25), sec(CUE.friends[0] - 0.25) + 0.3))).toFixed(3));
    const dt = t - sec(CUE.copied);
    vis(toast, dt > 0);
    if (dt > 0) {
        const u = E.outBack(clamp(dt / 0.35));
        set(toast, 'transform', `translateY(${((1 - u) * 60).toFixed(2)}px) scale(${(0.9 + 0.1 * u).toFixed(3)})`);
        set(toast, 'opacity', clamp(dt / 0.12).toFixed(3));
    }
    // a tap on the link's chip, just before "Link copied."
    const tap = sec(CUE.copied) - 0.12, dd = t - tap;
    vis(shRing, dd >= 0 && dd < 0.5);
    if (dd >= 0 && dd < 0.5) {
        set(shRing, 'transform', `translate(${72 + 150}px,${520 + 26}px) scale(${(1 + 2.4 * E.out(dd / 0.5)).toFixed(3)})`);
        set(shRing, 'opacity', (0.8 * (1 - dd / 0.5)).toFixed(3));
    }
    discs.forEach(({ d, l }, i) => {
        const at = i === 0 ? sec(CUE.copied) : sec(CUE.friends[i - 1]), d2 = t - at;
        const on = d2 >= 0;
        d.classList.toggle('ghost', !on);
        const pop = on ? 1 + 0.18 * Math.exp(-d2 / 0.12) * Math.cos(d2 * 26) : 1;
        set(d, 'transform', `scale(${pop.toFixed(4)})`);
        set(d, '--glow', on ? (0.4 + 0.6 * Math.exp(-d2 / 0.3)).toFixed(3) : '0');
        const txt = on ? FRIENDS[i] : NAMES[i];
        if (l._t !== txt) { l._t = txt; l.textContent = txt; }
        set(l, 'color', on ? PART_VAR[i] : 'var(--muted)');
    });
}

function shareSparkles(t) {
    discs.forEach((_, i) => {
        if (!i) return;
        const dd = t - sec(CUE.friends[i - 1]);
        if (dd < 0 || dd > 0.6) return;
        const [sx, sy, s] = project(SH.x + 590 + i * 190 + 56, SH.y + 600 + 56, 0);
        sparkles(sx, sy, dd, T.parts[i], s * 1.3, i);
        glow(sx, sy, 200 * s, T.parts[i], 0.6 * Math.exp(-dd / 0.2));
    });
}

function split(t, a) {
    const b = beatAt(t);
    quads.forEach((Qd, i) => {
        // each singer wipes in, one after another on the cut
        const w = E.outQuint(range(t, a + i * 0.07, a + i * 0.07 + 0.22));
        set(Qd.q, 'clipPath', `inset(0 ${((1 - w) * 100).toFixed(2)}% 0 0 round ${stageEl.classList.contains('arcade') ? 0 : 28}px)`);
        set(Qd.inner, 'transform', `scale(${lerp(1, 1.05, range(t, a, a + 1.25)).toFixed(4)})`);
        set(Qd.strip, 'transform', `translateX(${(QBEAM - (b - QB0) * QPX).toFixed(2)}px)`);
        // "You", four times: each badge lands with a small spring, one after another
        set(Qd.you, 'transform', `scale(${spring(t - a - 0.28 - i * 0.1, 2.4, 0.42).toFixed(4)})`);
        for (const g of Qd.gems) {
            const now = b >= g.n.beat && b < g.n.beat + g.n.length;
            set(g.el, '--glow', now ? '0.85' : b > g.n.beat ? '0.35' : '0.22');
            set(g.el, '--rim', now ? '1' : '0');
            set(g.el, '--fill', clamp((b - g.n.beat) / g.n.length).toFixed(3));
        }
    });
}

/** The end card's glints: a band of light across each bar of the mark, now and then. */
function glints(t, rects) {
    const g = $('#fx').getContext('2d');
    for (const start of [sec(CUE.end) + 1.6, sec(CUE.end) + 3.6]) {
        rects.forEach((r, i) => {
            const u = range(t, start + i * 0.09, start + i * 0.09 + 0.7);
            if (u <= 0 || u >= 1) return;
            const [x0, y0] = project(r.x, r.y, 0), [x1, y1] = project(r.x + r.w, r.y + r.h, 0);
            const cx = lerp(x0 - 120, x1 + 120, E.io(u)), h = y1 - y0;
            g.save();
            g.beginPath();
            g.roundRect(x0, y0, x1 - x0, h, h / 2);
            g.clip();
            const gr = g.createLinearGradient(cx - 80, 0, cx + 80, 0);
            gr.addColorStop(0, 'rgba(255,255,255,0)'); gr.addColorStop(0.5, `rgba(255,255,255,${LIGHT ? 0.6 : 0.5})`); gr.addColorStop(1, 'rgba(255,255,255,0)');
            // a slanted band: sheared about the bar's own middle, so it crosses where it is aimed
            g.translate(0, y0 + h / 2);
            g.transform(1, 0, -0.5, 1, 0, 0);
            g.fillStyle = gr;
            g.fillRect(cx - 80, -h / 2, 160, h);
            g.restore();
        });
    }
}

/* ================= the app, as it really is ================= */
// Real screens and recordings of the app (capture.py, captures.json), each in a device drawn here: a
// phone standing tall on the right, or a window pushed in on the panel that matters, so they read on a
// phone-sized landing. A cut films the app in its own light or dark. Its camera says where the device
// is and how big, by the point of the screen it frames (`fx`, `fy`, as a share of the screen), where that
// point sits in the frame (`sx`, `sy`) and the device's scale `s`. Taps are a ring and a soft disc.
// each language's film shows that language's app (capture.py --lang=es: captures.es.json, captures/es/)
const CAPS = (await (await fetch(LANG === 'en' ? 'captures.json' : `captures.${LANG}.json`)).json()).shots;
const CUTNAME = `${LANG === 'en' ? '' : `${LANG}/`}${LIGHT ? 'light' : 'dark'}`;
const appsEl = mk('div', 'apps', $('#stage'));
splitEl.after(appsEl);
const appGlow = mk('div', 'app-glow', appsEl);
const dev = mk('div', 'dev', appsEl);
const devBar = mk('div', 'dev-bar', dev);
devBar.innerHTML = '<i></i><i></i><i></i><span></span>';
const devUrl = devBar.querySelector('span');
const devImg = mk('img', 'dev-img', dev);
devImg.decoding = 'sync';
const tapPtr = mk('div', 'ptr', appsEl), tapRing = mk('div', 'ring', appsEl);
// a desktop's pointer: an arrow, drawn (an object, never a hand)
const cursor = mk('div', 'cursor', appsEl);
cursor.innerHTML = '<svg viewBox="0 0 24 24" width="46" height="46"><path d="M3 2l15 10.5-6.6 1.2 3.9 7.4-2.9 1.4-3.8-7.5L3 19.5z" fill="#fff" stroke="#111" stroke-width="1.4" stroke-linejoin="round"/></svg>';
const cursorRing = mk('div', 'ring', appsEl);
// The device's size on screen at scale 1: a phone 860 tall; a window 1500 wide, its bar 44 tall.
const PHONE_H = 860, WIN_W = 1500, BAR = 44;
const pending = [];
/** Resolves once every screen this frame asked for has been decoded (render.py waits for it). */
export const settle = () => Promise.all(pending.splice(0));
function app(t, shot) {
    const A = shot.app, id = typeof A.id === 'string' ? A.id : A.id(t), c = CAPS[id];
    if (!c) throw new Error(`no capture "${id}": run capture.py`);
    const phone = c.size === 'phone';
    const w = phone ? (PHONE_H * c.w) / c.h : WIN_W, h = phone ? PHONE_H : (WIN_W * c.h) / c.w, top = phone ? 0 : BAR;
    dev.className = `dev ${phone ? 'phone' : 'win'}`;
    set(dev, 'width', `${w}px`);
    set(dev, 'height', `${h + top}px`);
    if (devUrl._t !== (A.url ?? '')) { devUrl._t = A.url ?? ''; devUrl.textContent = A.url ?? ''; }
    // the frame of a recording: its own clock from the shot's start, held on its last frame
    // in step with the song (a recording that knows where in the song it began: the words lit are the
    // words sung), or from the shot's start
    const clock = A.sync && c.songAt != null ? t - c.songAt : t - shot.a + (A.skip ?? 0);
    const file = c.frames ? `${id}/${String(Math.min(c.frames - 1, Math.max(0, Math.floor(clock * c.fps)))).padStart(4, '0')}.jpg` : `${id}.jpg`;
    const src = `captures/${CUTNAME}/${file}`;
    if (devImg._src !== src) {
        devImg._src = src;
        devImg.src = src;
        pending.push(devImg.decode().catch(() => { throw new Error(`could not load ${src}`); }));
    }
    const cam = { ...A.cam(t) };
    // it arrives: a quick settle into place, unless the shot before was this same screen
    const prev = SHOTS[SHOTS.indexOf(shot) - 1];
    // (`settle: false`: a framing that must not show more than it does, like GitHub's above its counts)
    if (A.settle !== false && (!prev?.app || (typeof prev.app.id === 'string' ? prev.app.id : '') !== id)) cam.s *= lerp(0.93, 1, E.outExpo(range(t, shot.a, shot.a + 0.4)));
    const at = (fx, fy) => [cam.sx + (fx - cam.fx) * w * cam.s, cam.sy + (top + (fy - cam.fy) * h - top) * cam.s];
    const [x0, y0] = at(0, 0);
    set(dev, 'transform', `translate(${x0.toFixed(2)}px,${(y0 - top * cam.s).toFixed(2)}px) scale(${cam.s.toFixed(4)})${cam.rz ? ` rotate(${cam.rz.toFixed(3)}deg)` : ''}`);
    set(appGlow, 'transform', `translate(${(x0 + (w * cam.s) / 2).toFixed(1)}px,${(y0 + (h * cam.s) / 2).toFixed(1)}px)`);
    set(appGlow, '--glow-c', PART_VAR[ACTS[shot.act]?.[1] ?? 0]);
    // the taps: glide to each, press, and a ring spreads
    const taps = A.taps ?? (c.click ? [{ t: shot.a + c.click.at - (A.skip ?? 0), x: c.click.x, y: c.click.y }] : []);
    const [ptr, ring, other, otherRing] = phone ? [tapPtr, tapRing, cursor, cursorRing] : [cursor, cursorRing, tapPtr, tapRing];
    vis(other, false); vis(otherRing, false);
    vis(ptr, taps.length > 0 && t >= taps[0].t - 1.1 && t < taps.at(-1).t + 0.6);
    if (taps.length) {
        // it glides in from the lower right, then clicks
        const path = taps.map((p) => { const [x, y] = at(p.x, p.y); return { t: p.t, x, y }; });
        fingertip(ptr, ring, t, [{ t: path[0].t - 0.75, x: path[0].x + 380, y: path[0].y + 260, click: false }, ...path]);
    } else vis(ring, false);
}
/* ================= the tools, one a bar ================= */
// Hillel: "when showing the tools make it clear what each tool is and redesign". One tool a bar: the row of
// all four names (the one now lit), its name big with a few words on what it does, and the tool itself,
// its own panel cut from the real page, on a card that slides in on the bar as the last slides out.
const TOOLS = [
    { id: 'tuner', name: 'Tuner', what: 'See which note you\'re on.' },
    { id: 'drone', name: 'Drone', what: 'One steady note to match.' },
    { id: 'range', name: 'Vocal range test', what: 'Find the choir part to try.' },
];
// v4: three tools at four beats each (a name and its few words need about 3 s to read), and the row
// ends "and more": the site has many more free tools than the film has room for
const TOOLS_AT = sec(62), TOOL_BAR = sec(4);
const toolsEl = mk('div', 'tools', $('#stage'));
appsEl.after(toolsEl);
const toolIndex = mk('ol', 'tl-index', toolsEl);
const toolLis = TOOLS.map((x) => mk('li', null, toolIndex, x.name));
mk('li', 'more', toolIndex, 'and more');
const toolText = mk('div', 'tl-text', toolsEl);
const toolName = mk('h3', 'tl-name', toolText), toolWhat = mk('p', 'tl-what', toolText);
const CARD_H = 800;
const toolCards = [0, 1].map(() => { const c = mk('div', 'tl-card', toolsEl); const img = mk('img', null, c); img.decoding = 'sync'; return { c, img }; });
function toolCard(card, k, u, out) {
    const x = TOOLS[k], c = CAPS[x.id];
    if (!c?.crop) throw new Error(`no crop for the ${x.id}: run capture.py`);
    const cw = c.crop.w * c.w, ch = c.crop.h * c.h, k2 = CARD_H / ch, w = cw * k2;
    const src = `captures/${CUTNAME}/${x.id}.jpg`;
    if (card.img._src !== src) { card.img._src = src; card.img.src = src; pending.push(card.img.decode()); }
    set(card.img, 'width', `${(c.w * k2).toFixed(1)}px`);
    set(card.img, 'transform', `translate(${(-c.crop.x * c.w * k2).toFixed(1)}px,${(-c.crop.y * c.h * k2).toFixed(1)}px)`);
    set(card.c, 'width', `${w.toFixed(1)}px`);
    set(card.c, 'height', `${CARD_H}px`);
    // in from the right on the bar; the one before lifts away where it is, never across the words
    const dx = out ? 0 : 360 * (1 - E.outExpo(u)), dy = out ? -60 * E.in(clamp(u * 1.8)) : 0, rot = out ? 0 : 6 * (1 - E.outExpo(u));
    set(card.c, 'transform', `translate(${(1190 - w / 2 + dx).toFixed(1)}px,${250 + dy}px) rotate(${rot.toFixed(2)}deg)`);
    set(card.c, 'opacity', (out ? 1 - clamp(u * 1.8) : clamp(u * 3)).toFixed(3));
}
function tools(t) {
    const k = clamp(Math.floor((t - TOOLS_AT) / TOOL_BAR), 0, TOOLS.length - 1), local = t - TOOLS_AT - k * TOOL_BAR;
    const x = TOOLS[k];
    if (toolName._t !== x.name) { toolName._t = x.name; toolName.textContent = x.name; toolWhat.textContent = x.what; }
    toolLis.forEach((li, i) => { li.className = i === k ? 'now' : i < k ? 'done' : ''; });
    const r1 = E.rise(range(local, 0.05, 0.4)), r2 = E.rise(range(local, 0.15, 0.5));
    set(toolName, 'transform', `translateY(${((1 - r1) * 40).toFixed(1)}px)`); set(toolName, 'opacity', clamp(r1 * 1.6).toFixed(3));
    set(toolWhat, 'transform', `translateY(${((1 - r2) * 30).toFixed(1)}px)`); set(toolWhat, 'opacity', clamp(r2 * 1.6).toFixed(3));
    // this bar's card, and the last one leaving
    const cur = toolCards[k % 2], prev = toolCards[(k + 1) % 2], u = range(local, 0, 0.45);
    vis(cur.c, true);
    toolCard(cur, k, u, false);
    vis(prev.c, k > 0 && u < 1);
    if (k > 0 && u < 1) toolCard(prev, k - 1, u, true);
}

/** A camera that eases from one framing to another between two times. */
const move = (t0, t1, a, b, ease = E.io) => (t) => { const u = ease(range(t, t0, t1)); const o = {}; for (const k in a) o[k] = lerp(a[k], b[k] ?? a[k], u); return o; };
const PHONE_AT = { sx: 1230, sy: 720 }; // a phone rises from the frame's foot right of centre, clear of the line and the corners

/* ================= v5: lifted planes =================
   The real app as lifted components (docs/showreel-v5-plan.md, "App footage"): a crop of a capture, never a
   browser, as a plane in the frame's own 3D, with a soft shadow and a full-edge glow in the phrase's colour.
   A shot's `planes(t)` lists what shows: { id, crop (fractions, or a name from captures.json), clock (s into
   a recording), x, y (its centre), w (or h), z, rx, ry, rz, s, squash, o, glow, tint, tintC, scan, phone, label }. */
await loadLoud();
const planesEl = mk('div', 'planes', $('#stage'));
hud.before(planesEl);
const POOL = Array.from({ length: 14 }, () => {
    const p = mk('div', 'pl', planesEl), img = mk('img', null, p);
    img.decoding = 'sync';
    const tint = mk('i', 'tint', p), scan = mk('b', 'scan', p), sheen = mk('em', 'sheen', p), label = mk('span', 'pl-label', planesEl);
    return { p, img, tint, scan, sheen, label };
});
const pCursor = mk('div', 'cursor', planesEl);
pCursor.innerHTML = cursor.innerHTML;
const pRing = mk('div', 'ring', planesEl);
const cropOf = (c, q) => (typeof q.crop === 'string' ? c.crops?.[q.crop] : q.crop) ?? { x: 0, y: 0, w: 1, h: 1 };
/** Where a point of plane `q`'s capture (fractions of the capture) lands on the frame (ignoring its tilt). */
function onPlane(q, fx, fy) {
    const c = CAPS[q.id], cr = cropOf(c, q), w = q.w ?? q.h * (cr.w * c.w) / (cr.h * c.h), h = q.h ?? w * (cr.h * c.h) / (cr.w * c.w), k = q.s ?? 1;
    return [q.x + ((fx - cr.x) / cr.w - 0.5) * w * k, q.y + ((fy - cr.y) / cr.h - 0.5) * h * k];
}
/** How loud the voices that have joined are singing now (0 to 1): what the glows breathe with. */
function level(t) {
    const b = beatAt(t);
    let s = 0, n = 0;
    for (let i = 0; i < 4; i++) if (t >= litAt(i)) { s += loud(i, b); n++; }
    return n ? s / n : 0;
}
function drawPlanes(list, t) {
    POOL.forEach((P, k) => {
        const q = list[k];
        vis(P.p, !!q);
        vis(P.label, !!q?.label);
        if (!q) return;
        const c = CAPS[q.id];
        if (!c) throw new Error(`no capture "${q.id}": run capture.py`);
        const cr = cropOf(c, q);
        if (!cr) throw new Error(`no crop "${q.crop}" in ${q.id}: run capture.py`);
        const w = q.w ?? q.h * (cr.w * c.w) / (cr.h * c.h), h = q.h ?? w * (cr.h * c.h) / (cr.w * c.w);
        const file = c.frames ? `${q.id}/${String(Math.min(c.frames - 1, Math.max(0, Math.floor((q.clock ?? 0) * c.fps)))).padStart(4, '0')}.jpg` : `${q.id}.jpg`;
        const src = `captures/${CUTNAME}/${file}`;
        if (P.img._src !== src) { P.img._src = src; P.img.src = src; pending.push(P.img.decode().catch(() => { throw new Error(`could not load ${src}`); })); }
        const iw = w / cr.w, ih = iw * (c.h / c.w);
        set(P.img, 'width', `${iw.toFixed(1)}px`);
        set(P.img, 'transform', `translate(${(-cr.x * iw).toFixed(1)}px,${(-cr.y * ih).toFixed(1)}px)`);
        P.p.className = `pl${q.phone ? ' phone' : ''}`;
        set(P.p, 'width', `${w.toFixed(1)}px`);
        set(P.p, 'height', `${h.toFixed(1)}px`);
        const k1 = q.s ?? 1;
        set(P.p, 'transform', `translate3d(${(q.x - w / 2).toFixed(1)}px,${(q.y - h / 2).toFixed(1)}px,${(q.z ?? 0).toFixed(1)}px) rotateX(${(q.rx ?? 0).toFixed(2)}deg) rotateY(${(q.ry ?? 0).toFixed(2)}deg) rotateZ(${(q.rz ?? 0).toFixed(2)}deg) scale(${k1.toFixed(4)},${(k1 * (q.squash ?? 1)).toFixed(4)})`);
        set(P.p, 'opacity', clamp(q.o ?? 1).toFixed(3));
        set(P.p, '--glow-c', q.glow == null ? 'transparent' : q.glow === 'brand' ? 'var(--accent)' : PART_VAR[q.glow]);
        set(P.p, 'borderRadius', q.radius != null ? `${q.radius.toFixed(1)}px` : '');
        set(P.tint, 'opacity', clamp(q.tint ?? 0).toFixed(3));
        set(P.tint, 'background', q.tintC ?? 'var(--cream)');
        set(P.scan, 'opacity', clamp(q.scan ?? 0).toFixed(3));
        // glass: a band of light crosses it once as it arrives, and its edge glow breathes with the singing
        const sh = q.sheen ?? 0;
        vis(P.sheen, sh > 0 && sh < 1);
        if (sh > 0 && sh < 1) set(P.sheen, 'transform', `translateX(${lerp(-110, 110, E.ioSine(sh)).toFixed(2)}%)`);
        set(P.p, '--gk', level(t).toFixed(3));
        if (q.label) {
            if (P.label._t !== q.label) { P.label._t = q.label; P.label.textContent = q.label; }
            set(P.label, 'transform', `translate(${q.x.toFixed(1)}px,${(q.y + (h * k1) / 2 + 22).toFixed(1)}px) translateX(-50%)`);
            set(P.label, 'opacity', clamp(q.o ?? 1).toFixed(3));
        }
    });
}
/** The desktop pointer, gliding in to click at a point of a plane at `at` (song seconds). */
function planeTap(t, q, fx, fy, at) {
    const show = t >= at - 1.1 && t < at + 0.6;
    vis(pCursor, show);
    vis(pRing, false);
    if (!show) return;
    const [x, y] = onPlane(q, fx, fy);
    fingertip(pCursor, pRing, t, [{ t: at - 0.75, x: x + 380, y: y + 260, click: false }, { t: at, x, y }]);
}

/* ================= v5: the lines =================
   Figtree 800 at 76 px, top left (64 px across the vertical cut's top), one at a time, at most two rows. Each
   word rises 24 px, 40 ms after the one before, over 450 ms (expo out); one accent word takes the colour of the
   voice that just joined; the line goes over the 200 ms before the beat its next one comes on. No kickers. */
const lineEl = mk('div', 'line5', hud);
const ACCENT = (k) => (k === 'brand' ? 'var(--accent)' : PART_VAR[k]);
const L5 = (a, b, en, lit, colour) => { const [text, accent] = LINE_T(en, lit); return { a, b, text, accent, colour }; };
const L5E = (a, b, text, accent, colour) => ({ a, b, text, accent, colour }); // a season cut's line: English only
const LINE1 = 'Learn your harmony from a real singer.', LINE2 = 'Record each part over the others.', LINE3 = 'Stack your own voices, exactly in time.', LINE4 = 'The whole song is you.';
const LINES = {
    hero: [L5(-1, sec(12), LINE1, 'singer.', 0), L5(sec(12), sec(24), LINE2, 'others.', 1), L5(sec(24), sec(36), LINE3, 'time.', 2), L5(sec(36), sec(48), LINE4, 'you.', 'brand'),
        L5(sec(48), sec(66), 'Free to learn.', 'Free', 0), L5(sec(66), sec(72), 'Or play it as a game.', 'game.', 1), L5(sec(72), sec(84), 'For one voice, or a whole choir.', 'choir.', 2),
        L5(sec(84), sec(88), 'Open source on GitHub.', 'GitHub.', 3), L5(DUR - SPB, DUR + 1, LINE1, 'singer.', 0)],
    short: [L5(-1, sec(12), LINE1, 'singer.', 0), L5(sec(12), sec(24), LINE2, 'others.', 1), L5(sec(24), sec(36), LINE3, 'time.', 2), L5(sec(36), sec(43), LINE4, 'you.', 'brand'),
        L5(DUR - SPB, DUR + 1, LINE1, 'singer.', 0)],
    social: [L5(sec(24) - 1, sec(30), LINE1, 'singer.', 0), L5(sec(30), sec(36), LINE2, 'others.', 1), L5(sec(36), sec(42), LINE3, 'time.', 2), L5(sec(42), sec(48), LINE4, 'you.', 'brand')],
    // the carols cut: the page speaks for itself first; then the page's own promises, a line a voice
    carols: [L5E(sec(4.5), sec(12), 'Silent Night, in four parts.', 'parts.', 1), L5E(sec(12), sec(18), 'Hear each part, then sing yours.', 'yours.', 2), L5E(sec(18), sec(21.4), 'Free, in your browser.', 'Free,', 3)],
    // the Hanukkah cut: the same promises (its page's own), a line a voice
    hanukkah: [L5E(sec(6), sec(16), 'Maoz Tzur, in four parts.', 'parts.', 1), L5E(sec(16), sec(23.5), 'Hear each part, then sing yours.', 'yours.', 2), L5E(sec(23.5), sec(30.5), 'Free, in your browser.', 'Free,', 3)],
}[CUT];
function drawLine(t) {
    const l = LINES.findLast((x) => t >= x.a - 1e-6 && t < x.b);
    vis(lineEl, !!l);
    set(hud, '--shade', l ? '1' : '0');
    if (!l) return;
    if (lineEl._t !== l.text) {
        lineEl._t = l.text;
        lineEl.innerHTML = '';
        lineEl._w = l.text.split(' ').map((w) => { const e = mk('span', null, mk('span', 'w', lineEl), w); e._accent = w === l.accent; if (e._accent) e.style.color = ACCENT(l.colour); return e; });
    }
    // each word rises from behind its own baseline, and leaves upward the same way, a little quicker
    const n = lineEl._w.length;
    lineEl._w.forEach((e, k) => {
        const at = l.a + k * 0.04, u = E.outExpo(range(t, at, at + 0.5)), out = E.in(range(t, l.b - 0.24 + k * (0.06 / n), l.b - 0.02));
        set(e, 'transform', `translateY(${((1 - u) * 108 - out * 108).toFixed(2)}%)`);
        set(e, 'opacity', (clamp(u * 2.5) * (1 - out)).toFixed(3));
        // the accent word glows as it lands (the dark cut: light on black)
        if (e._accent) set(e, 'textShadow', LIGHT || l.a < 0 || l.a >= DUR - SPB - 1e-6 ? '' : `0 0 ${(36 * Math.exp(-Math.max(0, t - at - 0.2) / 0.5)).toFixed(1)}px currentColor`);
    });
}
/** Each line, from when it reads to when it goes: for checking that each stays long enough to read. */
export function captions() {
    // (the pre-roll's line is the first line again, set before the loop: not a line of its own)
    return LINES.filter((l) => !(l.a >= DUR - SPB - 1e-6 && CUT !== 'social')).map((l) => {
        const from = Math.max(l.a + 0.45, sec(FROM)), until = Math.min(l.b, sec(LAST));
        return { text: l.text, from: +(from - sec(FROM)).toFixed(2), until: +(until - sec(FROM)).toFixed(2), shown: +(until - from).toFixed(2), needs: +(0.3 * l.text.split(/\s+/).length + 1).toFixed(2) };
    });
}

/* ================= v5: the season's slot, and the components ================= */
// beats 54 to 57 of the hero: a songs page's top, lifted; then its songbook with Alto picked. In season the
// carols; after it `render.py --season=hymns` (or songbook) swaps the slot and nothing else.
const SEASONS = {
    carols: { hero: 'carols', strip: { x: 0.02, y: 0, w: 0.6, h: 0.45 }, book: 'carols-book', row: 'row' },
    hymns: { hero: 'hymns', strip: { x: 0.04, y: 0.04, w: 0.64, h: 0.44 }, book: 'hymns-book', row: { x: 0.08, y: 0.0, w: 0.84, h: 0.62 } },
    songbook: { hero: 'songbook', strip: { x: 0.04, y: 0.05, w: 0.6, h: 0.42 }, book: 'songbook-book', row: { x: 0.08, y: 0.1, w: 0.84, h: 0.55 } },
};
const SEASON = SEASONS[Q.get('season') ?? 'carols'];
if (!SEASON) throw new Error(`no season "${Q.get('season')}": ${Object.keys(SEASONS).join(', ')}`);
const SING_CROP = { x: 0, y: 0.17, w: 1, h: 0.63 }; // Sing's lanes and its words, no bars
const ROWS_CROP = { x: 0, y: 0.115, w: 1, h: 0.775 }; // the studio's words and its four rows
const STAGE_CROP = { x: 0.06, y: 0.16, w: 0.56, h: 0.52 }; // Home's stage card in the arcade, PRESS START in it
/** Where the verse is at song time t (either time through): what a recording made in step shows. */
const verseAt = (t) => (t >= sec(VERSE) ? t - sec(VERSE) : t);
const synced = (id, t) => verseAt(t) - (CAPS[id]?.songAt ?? 0);

/* ================= v5: the shots =================
   Each: `a` (song seconds), `set` (lanes, planes, share, split, fold, mark), its camera or planes, the wash's
   colour (verse two tints by phrase). Cuts are on the pickup; the accent lands on the next downbeat. */
const B = sec;
const ph = (t) => X(beatAt(t));
const centreOf = (i) => LANES[i].top + LANES[i].h / 2;
const PHONE_X = 1240;
const VERT = FORMAT === 'vertical';
// the opening: close on the first note, pulling back by 1.5 s to the stack, three lanes waiting
// (the stack sits under the line: its middle at the frame's y 655)
const STACK_Y = (z) => STACK_H / 2 - (640 - CY) / z;
// (held close a moment, a quick pull back, a long settle; then it keeps drifting in)
const openCam = (t) => { const u = E.snap(range(t, 0.1, 1.7)), z = lerp(OPEN.zoom, 0.54, u) * lerp(1, 1.035, range(t, 1.3, B(6))); return { x: lerp(X(0) + PXB / 2, ph(t) + 520, u), y: lerp(yOf(LANES[0], 62), STACK_Y(0.54), u), zoom: z, rz: lerp(0, -0.8, u) }; };
const twoCam = (t) => ({ x: ph(t) + 420, y: lerp(LANES[1].top - GAP / 2 - 150, LANES[1].top - GAP / 2 - 120, range(t, B(11), B(18))), zoom: lerp(0.8, 0.84, range(t, B(11), B(18))), rx: lerp(10, 2, E.io(range(t, B(11), B(13.5)))) });
const topCam = (t) => ({ x: ph(t) + 260, y: centreOf(1) + 60, zoom: lerp(0.84, 0.9, range(t, B(23), B(30))), rx: 40, ry: -8, rz: -6 });
const wideCam = (a, b) => (t) => { const z = lerp(0.55, 0.57, range(t, a, b)); return { x: ph(t) + 520, y: STACK_Y(z), zoom: z, rz: lerp(0.6, 0, range(t, a, b)) }; };
const vCam = (a, b) => (t) => ({ x: ph(t) + 230, y: STACK_H / 2 - 40, zoom: lerp(0.66, 0.69, range(t, a, b)) });
// the carols cut: the camera frames the lanes that are in and the one about to join, craning back a bar before
// each voice, the stack kept between the line and the foot of the frame
const USABLE = { vertical: [400, 1560], square: [290, 1020], wide: [300, 1010], thumb: [0, 480] }[FORMAT];
const CRANE = [(3.5 * 60) / 66, (0.8 * 60) / 66]; // back from 3.2 s before a voice joins to 0.7 s before
const carolCam = (t) => {
    const crane = (i) => E.io(range(t, B(CUE.enter[i]) - CRANE[0], B(CUE.enter[i]) - CRANE[1]));
    const n = 2 + crane(2) + crane(3), k = Math.min(3, Math.floor(n)), f = n - k;
    const bot = (m) => LANES[m - 1].top + LANES[m - 1].h, bottom = lerp(bot(k), bot(k + 1), f), top = -56;
    const z = Math.min(0.95, (USABLE[1] - USABLE[0]) / (bottom - top + 60)) * lerp(1, 1.035, range(t, B(SC.lanes), B(CUE.end)));
    return { x: ph(t) + (0.14 * W) / z, y: (top + bottom) / 2 - ((USABLE[0] + USABLE[1]) / 2 - CY) / z, zoom: z };
};
// its end card sits a little lower in the frame (the frame's foot is the platforms')
const CAROL_END = { vertical: 80, square: 100, wide: 0, thumb: 0 }[FORMAT];
const carolEnd = (t) => { const a = B(CUE.end), u = E.io(range(t, a, a + 1.1)), c = carolCam(t); return { x: lerp(c.x, END.cx, u), y: lerp(c.y, END.cy - CAROL_END, u), zoom: lerp(c.zoom, 1, u) * lerp(1, 1.035, range(t, a + 1, B(LAST))) }; };
// its opening: the live carols page, lifted: close on the Santa hat and the lights, the snow falling, then back
// to the whole heading; in the vertical cut its songbook comes in under it and the pointer picks Alto
const HERO_FULL = { x: 0.085, y: 0, w: 0.53, h: 0.56 }, HERO_HAT = { x: 0.085, y: 0, w: 0.21, h: 0.2219 };
function carolsPage(t) {
    const b = B(SC.lanes), u = E.snap(range(t, 0.3, 1.9)), leave = E.in(range(t, b - 0.22, b)), w = W - 80;
    const crop = { x: lerp(HERO_HAT.x, HERO_FULL.x, u), y: 0, w: lerp(HERO_HAT.w, HERO_FULL.w, u), h: lerp(HERO_HAT.h, HERO_FULL.h, u) };
    const push = lerp(1, 1.04, range(t, 1.9, b)) * (1 + 0.08 * leave);
    const list = [{ id: 'carols', crop, clock: t * 0.66, x: CX, y: VERT ? lerp(880, 640, E.io(range(t, B(2) - 0.55, B(2) + 0.35))) : CY + 10, w, glow: 2, ry: lerp(-5, -1.5, range(t, 0, b)), rx: 2, s: push, sheen: range(t, 0.2, 1.2) }];
    if (VERT) {
        const a = B(2), arrive = E.outExpo(range(t, a, a + 0.75)), c = CAPS['carols-book'];
        if (t >= a) {
            const q = { id: 'carols-book', crop: { x: 0.1059, y: 0, w: 0.7778, h: 0.6191 }, clock: t - a - 0.25, x: CX, y: 1296 + lerp(46, 0, arrive), w, glow: 1, s: lerp(0.88, 1, arrive) * push, rx: lerp(7, 2, arrive), ry: lerp(-8, 0, arrive) + lerp(-5, -1.5, range(t, 0, b)), o: clamp(0.4 + arrive * 2.4), sheen: range(t, a + 0.12, a + 1.0) };
            list.push(q);
            if (c?.click) planeTap(t, q, c.click.x, c.click.y, a + 0.25 + c.click.at);
        }
    }
    return list;
}
// the Hanukkah cut's opening: the live Jewish songs page, lifted: close on its nine lights, across to the heading;
// then the next festival's card comes in under it (capture.py films the page as served, so the card's day count
// is the capture day's: film it again on the day it is posted)
function hanukkahPage(t) {
    const b = B(SC.lanes), u = E.snap(range(t, 0.3, 1.9)), leave = E.in(range(t, b - 0.22, b)), w = W - 80, C = CAPS.hanukkah.crops;
    const full = { x: C.text.x - 0.021, y: C.hero.y, w: 0.53, h: C.text.y + 0.325 - C.hero.y }, k = full.h / full.w;
    const lights = { x: C.motif.x + C.motif.w - 0.235, y: C.hero.y, w: 0.225, h: 0.225 * k };
    const crop = { x: lerp(lights.x, full.x, u), y: full.y, w: lerp(lights.w, full.w, u), h: lerp(lights.h, full.h, u) };
    const push = lerp(1, 1.04, range(t, 1.9, b)) * (1 + 0.08 * leave), a = 1.75, arrive = E.outExpo(range(t, a, a + 0.75));
    const y0 = VERT ? 610 : 400;
    const list = [{ id: 'hanukkah', crop, clock: 0, x: CX, y: lerp(VERT ? 880 : CY + 10, y0, E.io(range(t, a - 0.55, a + 0.35))), w, glow: 2, ry: lerp(-5, -1.5, range(t, 0, b)), rx: 2, s: push, sheen: range(t, 0.2, 1.2) }];
    if (t >= a) {
        const pad = 0.014, cw = VERT ? 840 : 600;
        list.push({ id: 'hanukkah', crop: { x: C.count.x - pad, y: C.count.y - pad * 1.6, w: C.count.w + 2 * pad, h: C.count.h + 3.2 * pad }, clock: 0, x: CX, y: (VERT ? 1230 : 850) + lerp(46, 0, arrive), w: cw, glow: 1,
            s: lerp(0.88, 1, arrive) * push, rx: lerp(7, 2, arrive), ry: lerp(-8, 0, arrive) + lerp(-5, -1.5, range(t, 0, b)), o: clamp(0.4 + arrive * 2.4), sheen: range(t, a + 0.12, a + 1.0) });
    }
    return list;
}
const endCam = (t) => ({ x: END.cx, y: END.cy, zoom: lerp(1, 1.04, range(t, B(CUE.end), B(LAST - 1))) });

/** A single lifted screen, drifting (the camera never stops: a push of 3 to 5%, sine in and out). */
// It arrives out of depth, tilting level as it settles, a band of light crossing its glass; it drifts at one
// speed (never easing to a stop); and it leaves by pushing at the lens, so the cut carries the motion on.
const lift = (q, a, b, drift = {}) => (t) => {
    const u = range(t, a, b), arrive = E.outExpo(range(t, a, a + 0.75)), leave = drift.stay ? 0 : E.in(range(t, b - 0.22, b));
    return [{ ...q, clock: typeof q.clock === 'function' ? q.clock(t) : q.clock, s: (q.s ?? 1) * lerp(drift.s0 ?? 1, drift.s1 ?? 1.05, u) * lerp(0.88, 1, arrive) * (1 + 0.08 * leave),
        rx: (q.rx ?? 0) + lerp(7, 0, arrive), ry: (q.ry ?? 0) + (drift.ry ?? 0) * u + lerp(-8, 0, arrive), x: q.x + (drift.x ?? 0) * u, y: q.y + (drift.y ?? 0) * u + lerp(46, 0, arrive),
        o: clamp(0.4 + arrive * 2.4), sheen: range(t, a + 0.12, a + 1.0) }];
};
const singQ = { id: 'sing-soprano', crop: SING_CROP, x: CX + 40, y: 650, w: 1560, glow: 0, rx: 4, ry: -9, clock: (t) => synced('sing-soprano', t) };
const studioQ = (id, glow) => ({ id, crop: ROWS_CROP, x: CX + 30, y: 660, w: 1500, glow, rx: 3, ry: -6, clock: (t) => synced(id, t) });

// verse two: a component a bar, flown in from depth on the downbeat, whipped out on the next pickup
const SEASON_ROW = SEASON.row;
const GROUPS = [
    { s: 48, q: { id: 'courses-card', crop: 'head', x: CX + 60, y: 640, w: 1500, glow: 0 } },
    { s: 51, q: { id: 'lesson', phone: true, x: PHONE_X, y: 600, h: 860, glow: 0 } },
    { s: 54, q: { id: SEASON.hero, crop: SEASON.strip, x: CX + 40, y: 640, w: 1500, glow: 0 }, clock: true },
    { s: 57, q: { id: SEASON.book, crop: SEASON_ROW, x: CX + 40, y: 650, w: 1500, glow: 0 }, clock: true, tap: true },
];
function flyIn(g, t, next) {
    const a = B(g.s), d = B(g.s + 1), e = next != null ? B(next) : Infinity;
    if (t < a - 0.6 || t > e + 0.32) return null;
    const q = { ...g.q };
    if (g.clock) q.clock = t - a;
    if (t < a) { const u = range(t, a - 0.6, a); return { ...q, x: q.x + 420, y: q.y - 120, z: -3200, o: 0.45 * u, s: 1 }; }
    if (t < d) { const u = E.outExpo(range(t, a, d)); return { ...q, x: lerp(q.x + 420, q.x, u), y: lerp(q.y - 120, q.y, u), z: lerp(-3200, 0, u), ry: lerp(-14, -6, u), o: lerp(0.45, 1, clamp(u * 2)) }; }
    const hold = range(t, d, e), out = E.in(range(t, e, e + 0.3));
    return { ...q, x: q.x - 1900 * out, z: lerp(0, 90, hold), ry: lerp(-6, -2, hold) + 28 * out, rz: -3 * out, o: 1, sheen: range(t, d - 0.25, d + 0.7) };
}
// the tools: four panels, working, popping in a beat each, each named
const TOOLS5 = [['tuner-live', tr('Tuner'), 0], ['drone-live', tr('Drone'), 0.4], ['metro-live', tr('Metronome'), 0], ['range-live', tr('Vocal range test'), 3]];
function toolsGrid(t) {
    return TOOLS5.map(([id, label, skip], k) => {
        const at = B(60 + k), u = spring(t - at + 0.02, 1.4, 0.55);
        if (t < at - 0.02) return null;
        const col = k % 2, row = Math.floor(k / 2), w = 400;
        return { id, label, crop: { x: 0, y: 0, w: 1, h: 0.62 }, x: 960 + col * 460, y: 405 + row * 410, w, glow: 0, clock: t - at + skip, s: lerp(0.3, 1, u), o: clamp(u * 3), z: lerp(-200, 0, u) };
    });
}
function montage(t) {
    const list = [];
    GROUPS.forEach((g, k) => { const q = flyIn(g, t, GROUPS[k + 1]?.s ?? 60); if (q) list.push(q); });
    if (t >= B(60) - 0.05) list.push(...toolsGrid(t).filter(Boolean));
    // the songbook: the pointer picks Alto under I sing, as the capture did
    const book = list.find((q) => q.id === SEASON.book), c = CAPS[SEASON.book];
    if (book && c?.click) planeTap(t, book, c.click.x, c.click.y, B(57) + c.click.at); else { vis(pCursor, false); vis(pRing, false); }
    return list.sort((p, q) => (p.z ?? 0) - (q.z ?? 0));
}
// game mode: the same moment of Sing, calm, then a CRT flip on the downbeat into the arcade; then PRESS START
function flip(t) {
    const f = B(67);
    if (t < f - 0.16) return lift(singQ, B(66), f, { stay: true })(t);
    if (t < f) { const u = E.in(range(t, f - 0.16, f)); return [{ ...lift(singQ, B(66), f, { stay: true })(t)[0], squash: lerp(1, 0.012, u), tint: u, tintC: '#fff' }]; }
    if (t < B(70)) {
        const u = E.outExpo(range(t, f, f + 0.3)), q = { ...singQ, id: 'flip-arcade', glow: 'brand', clock: synced('flip-arcade', t) };
        return [{ ...q, s: lerp(1, 1.04, range(t, f, B(70))), squash: lerp(0.012, 1, u), tint: 1 - range(t, f, f + 0.35), tintC: '#fff', scan: 0.55 }];
    }
    const u = E.io(range(t, B(70), B(72)));
    return [{ id: 'daily', crop: STAGE_CROP, x: CX + 60 - 260 * u, y: 640 + 60 * u, w: 1500, s: lerp(1, 1.5, u), glow: 'brand', o: E.outExpo(range(t, B(70), B(70) + 0.3)) * 1.6 }];
}
// the fold: every verse-two component, a grid; four colour columns; the mark's four bars, on "see."
const FOLD_ITEMS = [['courses-card', 'head'], ['lesson', null], [SEASON.hero, SEASON.strip], [SEASON.book, SEASON_ROW], ['tuner-live', null], ['drone-live', null],
    ['metro-live', null], ['range-live', null], ['flip-arcade', SING_CROP], ['choir-director', 'song'], ['choir-member', 'song'], ['github', 'card']];
const SCREEN_MARK = END_MARK.map((r) => ({ x: r.x - END.cx + CX, y: r.y - END.cy + CY, w: r.w, h: r.h }));
function fold(t) {
    const a = B(CUE.all), b1 = B(CUE.all + 1), b2 = B(CUE.all + 2), see = B(CUE.end);
    return FOLD_ITEMS.map(([id, crop], k) => {
        const col = k % 4, row = Math.floor(k / 4), pop = spring(t - a - k * 0.03, 1.5, 0.6), land = clamp(pop);
        const cell = { x: CX + (col - 1.5) * 420, y: 360 + row * 250, w: 380, h: 214 };
        // each column becomes a small stack of three bars in its voice's colour, before the mark takes them
        const column = { x: CX + (col - 1.5) * 420, y: 600 + (row - 1) * 78, w: 380, h: 60 };
        const m = SCREEN_MARK[col], bar = { x: m.x + m.w / 2, y: m.y + m.h / 2, w: m.w, h: m.h };
        const u1 = E.io(range(t, b1 + col * 0.03, b2 - (3 - col) * 0.03)), u2 = E.snap(range(t, b2 + col * 0.035, see - (3 - col) * 0.035));
        const pos = lerp4(lerp4(cell, column, u1), bar, u2);
        return { id, crop, clock: 0.5, x: pos.x, y: pos.y, w: pos.w, h: pos.h, s: lerp(0.5, 1, pop), z: lerp(-1500, 0, land), rz: (hash(k + 3) - 0.5) * 26 * (1 - land), ry: lerp(-24, 0, land), o: clamp(pop * 3), tint: clamp(u1 * 1.3), tintC: PART_VAR[col], radius: lerp(14, pos.h / 2, u1), glow: col };
    });
}

const SHOTS_BY_CUT = {
    hero: [
        // VERSE ONE: one voice becomes four. The stack opens on the tune, three lanes waiting
        { a: 0, set: 'lanes', cam: openCam, trail: true },
        { a: B(6), set: 'planes', wash: 0, soft: true, planes: lift(singQ, B(6), B(11), { ry: 5 }) },
        // back to the lanes a beat before the alto sings (9.0), so its lane is seen to ignite; the camera
        // cranes up to two lanes
        { a: B(11), set: 'lanes', cam: twoCam, trail: true },
        { a: B(18), set: 'planes', wash: 1, soft: true, planes: lift(studioQ('studio-alto', 1), B(18), B(23), { y: -24, s1: 1.13 }) },
        // the tenor sings on 18.0, a beat into v4's top-down tilt, three lanes lit
        { a: B(23), set: 'lanes', cam: topCam, trail: true },
        { a: B(30), set: 'planes', planes: lift({ id: 'sing-tenor-phone', phone: true, x: PHONE_X, y: 600, h: 860, glow: 2, clock: (t) => synced('sing-tenor-phone', t) }, B(30), B(36), { y: -16 }) , wash: 2, soft: true },
        // the bass sings on 27.7, the whole stack; on 30.0 the four "You" tiles
        { a: B(36), set: 'lanes', cam: wideCam(B(36), B(40)), trail: true },
        { a: B(40), set: 'split' },
        { a: B(42), set: 'planes', wash: 3, soft: true, planes: lift(studioQ('studio-bass', 3), B(42), B(48), { y: 24, s0: 1.12, s1: 1 }) },
        // VERSE TWO: a montage over the full chord, a component a bar, tinted by phrase
        { a: B(48), set: 'planes', wash: 0, planes: montage },
        { a: B(66), set: 'planes', wash: 1, planes: flip },
        { a: B(72), set: 'share', wash: 2, cam: (t) => { const a = B(72), u = E.outExpo(range(t, a, a + 0.8)), o = range(t, a, B(78)); return { x: SH.x + SH.w / 2 + lerp(-60, 60, o), y: SH.y + 345, dz: lerp(-700, 0, u), zoom: lerp(0.9, 1.02, o), ry: lerp(-10, 6, E.io(o)) }; } },
        { a: B(78), set: 'planes', wash: 2, planes: lift({ id: 'choir-director', crop: 'song', x: CX + 160, y: 640, w: 1500, glow: 2 }, B(78), B(81), { x: -260, ry: 6 }) },
        { a: B(81), set: 'planes', wash: 2, planes: lift({ id: 'choir-member', crop: 'song', x: CX + 40, y: 640, w: 1500, glow: 2 }, B(81), B(84), { ry: 4 }) },
        { a: B(84), set: 'planes', wash: 3, planes: lift({ id: 'github', crop: 'card', x: CX, y: 640, w: 1300, glow: 3 }, B(84), B(88), { y: -20 }) },
        { a: B(CUE.all), set: 'fold', planes: fold },
        { a: B(CUE.end), set: 'lanes', mark: 'end', atMark: true, cam: endCam, wordsAt: B(CUE.end + 2) },
        { a: B(LAST - 1), set: 'lanes', preroll: true, cam: (t) => openCam(t - DUR) },
    ],
    short: [
        { a: 0, set: 'lanes', cam: openCam, trail: true },
        { a: B(6), set: 'planes', wash: 0, soft: true, planes: lift(singQ, B(6), B(11), { ry: 5 }) },
        { a: B(11), set: 'lanes', cam: twoCam, trail: true },
        { a: B(18), set: 'planes', wash: 1, soft: true, planes: lift(studioQ('studio-alto', 1), B(18), B(23), { y: -24, s1: 1.13 }) },
        { a: B(23), set: 'lanes', cam: topCam, trail: true },
        { a: B(30), set: 'planes', planes: lift({ id: 'sing-tenor-phone', phone: true, x: PHONE_X, y: 600, h: 860, glow: 2, clock: (t) => synced('sing-tenor-phone', t) }, B(30), B(36), { y: -16 }) , wash: 2, soft: true },
        { a: B(36), set: 'lanes', cam: wideCam(B(36), B(40)), trail: true },
        { a: B(40), set: 'split' },
        // "see.": the four held notes become the mark, and the card
        { a: B(CUE.end), set: 'lanes', mark: 'end', wordsAt: B(CUE.end) + 0.7, cam: (t) => { const a = B(CUE.end), u = E.io(range(t, a, a + 1)); return { x: lerp(X(CUE.end) + 380, END.cx, u), y: lerp(STACK_H / 2, END.cy, u), zoom: lerp(0.5, 1, u) }; } },
        { a: B(LAST - 1), set: 'lanes', preroll: true, cam: (t) => openCam(t - DUR) },
    ],
    carols: [
        { a: 0, set: 'planes', wash: 2, soft: true, planes: carolsPage },
        // the lanes a beat and a half before the alto joins; one shot through all three joining
        { a: B(4.5), set: 'lanes', cam: carolCam, trail: true, hold: true },
        // "bright": the four held notes become the mark, and the card with the carols' address
        { a: B(CUE.end), set: 'lanes', mark: 'end', wordsAt: B(CUE.end) + 0.7, cam: carolEnd },
    ],
    hanukkah: [
        { a: 0, set: 'planes', wash: 3, soft: true, planes: hanukkahPage },
        // the lanes a beat and a half before the alto's pickup; one shot through all three joining
        { a: B(6), set: 'lanes', cam: carolCam, trail: true, hold: true },
        // the last "ach.": the four held notes become the mark, and the card with the page's address
        { a: B(CUE.end), set: 'lanes', mark: 'end', wordsAt: B(CUE.end) + 0.7, cam: carolEnd },
    ],
    social: [
        { a: B(24), set: 'lanes', cam: vCam(B(24), B(30)), trail: true },
        // the alto sings on 22.5: its lane ignites, then a beat on, its studio take
        { a: B(31), set: 'planes', wash: 1, soft: true, planes: lift({ id: 'studio-alto-phone', phone: true, x: CX, y: 1010, h: 1380, glow: 1, clock: (t) => synced('studio-alto-phone', t) }, B(31), B(35), { y: -20 }) },
        // back a beat before the tenor sings (26.9); the bass is let in mid-note at 32.1
        { a: B(35), set: 'lanes', cam: vCam(B(35), B(44)), trail: true },
        { a: B(44), set: 'split', url: B(45) },
        { a: B(48), set: 'mark', url: B(45) },
    ],
};
const SHOTS = SHOTS_BY_CUT[CUT];
SHOTS.forEach((s, i) => { s.b = SHOTS[i + 1] ? SHOTS[i + 1].a : sec(LAST); });
export const CUTS = SHOTS.slice(1).map((s) => s.a - sec(FROM));
export const EDIT = { wide: [[0, DUR]], vertical: [[0, DUR]], square: [[0, DUR]], thumb: [[0, 3.75]] }[FORMAT];
if (FORMAT === 'square' && !SEASONAL) throw new Error('v5 has no square cut but the season cuts');
const FRAMING = { zoom: 1, down: 0 };
const reframe = (c) => c;
// windows fast enough to earn extra motion-blur samples: every cut's first moments, the montage's whips,
// the flip, the fold and the mark forming (film seconds)
export const FAST = [...SHOTS.map((s) => [s.a - 0.24, s.a + 0.6]), ...[1, 2, 3].map((i) => [peakAt(i) - SWEEP / 2, peakAt(i) + SWEEP / 2]), ...GROUPS.map((g, k) => [B(GROUPS[k + 1]?.s ?? 60), B(GROUPS[k + 1]?.s ?? 60) + 0.35]),
    [B(CUE.all), B(CUE.end) + 0.3], [B(CUE.fold[0]), B(LAST - 1)]].map(([a, b]) => [a - sec(FROM), b - sec(FROM)]);
const FLASHES = CUT === 'hero' ? [[B(VERSE), 0.05], [B(CUE.end), 0.08]] : [[B(CUE.end), 0.08]];

/* ================= v5: the mark in the frame (the social cut's tiles fold into it) ================= */
const markV = mk('div', 'markv', $('#stage'));
hud.before(markV);
const MARKV = markRects(CX, 1010, 1.9);
const markVBars = MARKV.map((r, i) => { const g = mk('div', 'gem gem-bar', markV); g.style.cssText = `left:0;top:0;--gem:var(--m${i + 1})`; return g; });
const urlEl = mk('div', 'url5', hud, SITE);
function drawMarkV(t, a) {
    MARKV.forEach((r, i) => {
        const q = QPOS[i], from = { x: q[0] + QUAD.w / 2 - 40, y: q[1] + QUAD.h / 2 - 20, w: 80, h: 40 };
        const u = E.snap(range(t, a + i * 0.05, a + 0.7 + i * 0.05));
        morphBar(markVBars[i], i, from, r, u, E.io(range(t, a + 0.5, a + 1.2)), lerp(20, r.h / 2, u));
        set(markVBars[i], '--glow', (0.35 + 0.65 * Math.exp(-Math.max(0, t - a - 0.8) / 0.4)).toFixed(3));
    });
}

/* ================= the CRT (v4's full-frame power on and off): v5 flips on the plane alone ================= */
const ARC0 = -99, ARC1 = -99;

/* ================= the CRT: game mode switching on and off ================= */
const crtEl = $('#crt'), [crtTop, crtBottom, crtLine, crtWash] = crtEl.children;
const stageEl = $('#stage');
function crt(t) {
    let h = 0, line = 0, lw = 1, wash = 0;
    const close = (a, b) => { const u = E.in(range(t, a, b)); h = Math.max(h, 540 * u); line = Math.max(line, clamp((u - 0.55) / 0.45)); };
    const open = (a, b) => { const u = range(t, a, b); if (u <= 0 || u >= 1) return; h = Math.max(h, 540 * (1 - E.outExpo(u))); line = Math.max(line, 1 - u); wash = Math.max(wash, 0.55 * (1 - u) ** 2); };
    // on: the page closes to a bright line on the beat, and the line opens as the arcade
    if (t >= ARC0 - 0.16 && t < ARC0) close(ARC0 - 0.16, ARC0);
    open(ARC0, ARC0 + 0.34);
    // off: the arcade closes to a line, the line shrinks to a dot, and the calm screen opens
    if (t >= ARC1 - 0.42 && t < ARC1) {
        close(ARC1 - 0.42, ARC1 - 0.2);
        if (t >= ARC1 - 0.2) { h = 540; line = 1; lw = Math.max(0.006, 1 - E.in(range(t, ARC1 - 0.2, ARC1 - 0.04))); }
    }
    open(ARC1, ARC1 + 0.26);
    vis(crtEl, h > 0.5 || wash > 0.01);
    set(crtTop, 'height', `${h.toFixed(1)}px`);
    set(crtBottom, 'height', `${h.toFixed(1)}px`);
    set(crtLine, 'opacity', line.toFixed(3));
    set(crtLine, 'transform', `scaleX(${lw.toFixed(4)})`);
    set(crtWash, 'opacity', wash.toFixed(3));
}

/* ================= the thumbnail: the mark, its voices lit one by one ================= */
// Product Hunt's 240 px loop (drawn at 480): the four bars as glass, each lit on its beat (soprano,
// alto, tenor, bass), then all four together with a glint, over six beats that loop.
const thumbEl = FORMAT === 'thumb' ? mk('div', 'thumb', $('#stage')) : null;
const THUMB_MARK = markRects(CX, CY, 1.24);
const thumbBars = thumbEl ? THUMB_MARK.map((r, i) => {
    const g = mk('div', 'gem gem-bar', thumbEl);
    g.style.cssText = `left:${r.x}px;top:${r.y}px;width:${r.w}px;height:${r.h}px;border-radius:${r.h / 2}px;--gem:var(--m${i + 1})`;
    return g;
}) : [];
function thumb(t) {
    setCam({});
    for (const k in sets) vis(sets[k], false);
    vis(splitEl, false);
    vis(cap, false);
    const T6 = THUMB_T, at = (b) => ((t - sec(b)) % T6 + T6) % T6; // seconds since a beat, around the loop
    thumbBars.forEach((g, i) => {
        const d = at(i), all = at(4);
        const lit = Math.exp(-d / 0.32), together = Math.exp(-all / 0.5);
        const pop = 1 + 0.07 * Math.exp(-d / 0.1) * Math.cos(d * 30) + 0.04 * Math.exp(-all / 0.12) * Math.cos(all * 30);
        set(g, 'transform', `scale(${pop.toFixed(4)})`);
        set(g, '--glow', clamp(0.25 + 0.75 * Math.max(lit, together)).toFixed(3));
    });
    // the glint, as on the end card, when all four sing together
    const g = $('#fx').getContext('2d'), u = range(at(4), 0.1, 0.8);
    if (u > 0 && u < 1) THUMB_MARK.forEach((r, i) => {
        const v = range(at(4), 0.1 + i * 0.06, 0.8 + i * 0.06);
        if (v <= 0 || v >= 1) return;
        const cx = lerp(r.x - 60, r.x + r.w + 60, E.io(v));
        g.save(); g.beginPath(); g.roundRect(r.x, r.y, r.w, r.h, r.h / 2); g.clip();
        g.translate(0, r.y + r.h / 2); g.transform(1, 0, -0.5, 1, 0, 0);
        const gr = g.createLinearGradient(cx - 40, 0, cx + 40, 0);
        gr.addColorStop(0, 'rgba(255,255,255,0)'); gr.addColorStop(0.5, 'rgba(255,255,255,0.55)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = gr; g.fillRect(cx - 40, -r.h / 2, 80, r.h); g.restore();
    });
    specks(t * (DUR / T6), DUR, 0.6, -700);
    set(flashEl, 'opacity', '0');
    set(hud, '--shade', '0');
}

const sets = { lanes: laneSet, share: shSet };
// where the fold back to the first note ends (the carols cut never folds: its fold is past its end)
const FOLD_B = Math.max(sec(LAST - 1), sec(CUE.fold[0]) + 0.5);
// ?cam={"rx":40} nudges the camera of whatever shot is showing: for trying a framing in the preview
const NUDGE = Q.has('cam') ? JSON.parse(Q.get('cam')) : null;

export function film(t) {
    // t is the song's clock (main.js adds the cut's start)
    let shot = SHOTS[0];
    for (const s of SHOTS) if (t >= s.a) shot = s;
    for (const k in sets) vis(sets[k], shot.set === k);
    vis(splitEl, shot.set === 'split');
    vis(appsEl, false);
    vis(toolsEl, false);
    vis(planesEl, shot.set === 'planes' || shot.set === 'fold');
    vis(markV, shot.set === 'mark');
    vis(cap, false);
    stageEl.classList.toggle('arcade', false);
    crt(t);
    // the colour script: verse two tints by phrase
    // (verse one too, softer: each lifted screen sits in the light of the voice that just joined; it breathes with the singing)
    set(washEl, 'opacity', shot.wash != null ? ((shot.soft ? 0.6 : 0.8) + 0.2 * level(t)).toFixed(3) : '0');
    if (shot.wash != null) set(washEl, 'background', `radial-gradient(ellipse 80% 70% at 62% 62%, color-mix(in oklab, ${PART_VAR[shot.wash]} ${LIGHT ? 12 : 17}%, transparent), transparent 76%)`);
    if (FORMAT === 'thumb') return thumb(t);
    const cam = shot.cam ? reframe(shot.cam(t)) : null;
    if (cam && shot.trail) {
        // the camera settles in from each cut and pushes at the lens into the next, so cuts carry the motion;
        // and it takes a small kick as each voice starts to sing
        let k = shot.hold ? 1 : 1 + 0.07 * E.in(range(t, shot.b - 0.22, shot.b));
        if (shot.a > sec(FROM)) k *= lerp(0.93, 1, E.outExpo(range(t, shot.a, shot.a + 0.7)));
        for (let i = 1; i < 4; i++) { const d = t - peakAt(i); if (d > 0) k *= 1 + 0.016 * (1 - Math.exp(-d / 0.035)) * Math.exp(-d / 0.32); }
        cam.zoom *= k;
    }
    setCam(cam ? (NUDGE ? { ...cam, ...NUDGE } : cam) : {});
    set(lens, 'filter', '');
    const lt = shot.preroll ? t - DUR : t;

    if (shot.set === 'lanes') {
        let o = { lt, names: shot.names, all: shot.all, quiet: shot.quiet, cam };
        vis(endEl, shot.mark === 'end');
        morphEls.forEach((e) => vis(e, !!shot.mark));
        if (shot.mark) {
            // "see.": the four held notes (or, after the fold, the four bars it made) are the mark
            const beat = CUE.end, t0 = sec(beat);
            const idx = [0, 1, 2, 3].map((i) => noteIndex(i, beat));
            const fade = shot.atMark ? 0 : 1 - E.io(range(t, t0 + 0.1, t0 + 0.7));
            o = { ...o, all: true, hide: idx, fade, lines: fade, quiet: true, noBeam: fade < 0.05 };
            const fold = E.io(range(t, sec(CUE.fold[0]), FOLD_B));
            const z = cam.zoom, ow = OPEN.w * FRAMING.zoom, oh = OPEN.h * FRAMING.zoom;
            const gem = { x: END.cx - ow / 2 / z, y: END.cy + FRAMING.down / z - oh / 2 / z, w: ow / z, h: oh / z };
            morphEls.forEach((e, i) => {
                const u = shot.atMark ? 1 : E.snap(range(t, t0 + 0.05 + i * 0.06, t0 + 0.85 + i * 0.06));
                let to = END_MARK[i], radius;
                if (fold > 0) {
                    // the fold: the lower bars slide up into the tune's, which becomes the opening note
                    const f1 = E.io(clamp(fold * 1.6)), f2 = E.io(clamp(fold * 1.6 - 0.6));
                    to = i === 0 ? lerp4(END_MARK[0], gem, f2) : lerp4(lerp4(END_MARK[i], { ...END_MARK[0], w: END_MARK[i].w * 0.6 }, f1), gem, f2);
                    radius = lerp(to.h / 2, (OPEN.r * FRAMING.zoom) / z, f2);
                    set(e, 'opacity', i === 0 ? '1' : (1 - clamp(fold * 1.6 - 0.5) * 2).toFixed(3));
                } else set(e, 'opacity', '1');
                const colour = E.io(range(t, t0 + 0.4, t0 + 1.2)) * (1 - E.io(range(t, sec(CUE.fold[0]) + 0.4, FOLD_B)));
                // (after the fold, each bar lands with a small squash)
                const dl = t - t0 - i * 0.035, land = shot.atMark && dl > 0 ? 0.08 * Math.exp(-dl / 0.11) * Math.cos(dl * 24) : 0;
                morphBar(e, i, gemRect(i, idx[i]), to, u, colour, radius, land);
                // the mark breathes
                // and glows with its own voice's loudness on the held chord
                set(e, '--glow', clamp(0.3 + 0.7 * Math.exp(-Math.max(0, t - t0 - (shot.atMark ? 0 : 0.9)) / 0.3) + 0.08 * Math.sin((t - t0) * 2.4 + i) + 0.3 * loud(i, beatAt(t))).toFixed(3));
            });
            const wa = shot.wordsAt, out = E.in(range(t, sec(CUE.fold[0]), sec(CUE.fold[0]) + 0.3));
            [[endH, 0], [endSite, 0.25], [endCredit, 0.55]].forEach(([e, d]) => {
                const r = E.outExpo(range(t, wa + d, wa + d + 0.55));
                set(e, 'transform', `translateY(${((1 - r) * 24 + out * -30).toFixed(2)}px)`);
                set(e, 'opacity', (clamp(r * 1.5) * (1 - out)).toFixed(3));
            });
        }
        lanes(t, o);
        if (shot.mark === 'end' && t < sec(CUE.fold[0])) glints(t, END_MARK);
        if (shot.atMark) END_MARK.forEach((r, i) => {
            // the fold lands: a spark at each bar's end, in its colour
            const d = t - sec(CUE.end) - i * 0.035;
            if (d < 0 || d > 0.6) return;
            const [sx, sy, s] = project(r.x + r.w, r.y + r.h / 2, 0);
            sparkles(sx, sy, d, T.parts[i], s * 1.2, i + 7, 8);
            glow(sx - (r.w * s) / 2, sy, 250 * s, T.parts[i], (LIGHT ? 0.2 : 0.36) * Math.exp(-d / 0.22));
        });
    }
    if (shot.set === 'planes' || shot.set === 'fold') {
        if (shot.planes !== montage) { vis(pCursor, false); vis(pRing, false); }
        drawPlanes(shot.planes(t), t);
    }
    if (shot.set === 'share') share(t);
    if (shot.set === 'split') split(t, shot.a);
    if (shot.set === 'mark') drawMarkV(t, shot.a);
    const ua = shot.url ?? null;
    vis(urlEl, ua != null && t >= ua);
    if (ua != null) { const r = E.outExpo(range(t, ua, ua + 0.6)); set(urlEl, 'opacity', clamp(r * 1.4).toFixed(3)); set(urlEl, 'transform', `translateX(-50%) translateY(${((1 - r) * 20).toFixed(1)}px)`); }

    /* screen space: the voice, the sparks, the specks */
    if (shot.set === 'lanes' && shot.trail) { voiceTrail(lt); hits(lt); ignitions(lt); }
    if (shot.set === 'share') shareSparkles(t);
    if (shot.set === 'lanes' || shot.set === 'share') specks(t, DUR, shot.set === 'share' ? 0.5 : 1, shot.set === 'lanes' ? cam.x * 0.8 - 1000 : SH.x);
    else specks(t, DUR, 0.4, -400);

    drawLine(t);
    let fl = 0;
    for (const [a, k] of FLASHES) if (t >= a) fl += k * Math.exp(-(t - a) / 0.08);
    set(flashEl, 'opacity', Math.min(0.4, fl).toFixed(4));
}
