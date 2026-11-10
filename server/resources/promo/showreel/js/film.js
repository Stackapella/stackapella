// HARMONY, MADE BY YOU: the film. One world (Amazing Grace's four lanes of glass gems, the share card
// and the end card, each at its own place in it), the real app in a drawn device, a split screen of the four
// singers drawn over it, and a director who cuts between shots on the song's beat. Everything is a
// pure function of t, so any frame can be rendered alone.

import { E, $, CX, CY, FORMAT, H, LIGHT, Q, W, bump, clamp, hash, lerp, mk, range, set, spring, vis } from './lib.js';
import { BEATS, CUE, DUR, S, VERSE, beatAt, creditLine, sec, sung, voicing } from './score.js';
import { PART_INK, PART_VAR, T, glow, project, setCam, sparkles, specks } from './stage.js';

const W0 = $('#world');
const hud = $('#hud');
const flashEl = $('#flash');
const lens = $('#lens');
const washEl = $('#wash');
const splitEl = $('#split');
const svgTick = '<svg class="tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
const svgCheck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M7.5 12.5l3 3 6-6.5"/></svg>';
const NAMES = ['Soprano', 'Alto', 'Tenor', 'Bass'];
const RANGES = ['D4 to D5', 'B3 to B4', 'G3 to C4', 'G2 to D3'];
const FRIENDS = ['You', 'Maya', 'Sam', 'Leo'];
const clock = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

/* ================= the lanes ================= */
// One lane per part, in the part's colour: x is time (PXB a beat), y is pitch (SP a semitone). The
// gems are big and chunky, as in the arcade lanes: each is taller than two semitones.
const PXB = 200, SP = 26, GH = 64, GAP = 120, GW = 14;
const X = (b) => b * PXB;
const RANGE = [[62, 74], [59, 71], [55, 60], [43, 50]];
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
    lines.style.cssText = `left:${X(-2.4)}px;width:${X(BEATS + 6)}px;top:${lane.top}px;height:${lane.h}px`;
    for (let p = lane.lo - 1; p <= lane.hi + 1; p++) mk('i', p % 12 === 0 ? 'c' : '', lines).style.top = `${(lane.hi + 1.5 - p) * SP - 1}px`;
    const name = mk('div', 'lane-name', el);
    name.style.cssText = `left:${X(-2.2)}px;top:${lane.top + 8}px;color:${PART_VAR[lane.i]}`;
    mk('span', null, name, NAMES[lane.i]);
    const who = mk('small', null, name, RANGES[lane.i]);
    const gems = S.parts[lane.i].map((n, k) => {
        const g = mk('div', 'gem gem-bar', el);
        g.style.cssText = `left:${X(n.beat) + GW / 2}px;top:${yOf(lane, n.pitch) - GH / 2}px;width:${n.length * PXB - GW}px;height:${GH}px;--gem:${PART_VAR[lane.i]};--gem-ink:${PART_INK[lane.i]}`;
        g.innerHTML = `<span class="w">${n.lyric ?? ''}</span>`;
        if (lane.i === 0) g.insertAdjacentHTML('beforeend', svgTick);
        return { el: g, n, k, tick: g.querySelector('.tick') };
    });
    return { el, lines, name, who, gems, lane };
});
const beamEl = mk('div', 'beam', laneSet);
const chipEl = mk('div', 'chip', laneSet);
chipEl.innerHTML = '<i></i><span>Slide up</span>';
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
function morphBar(el, i, a, b, u, colour, radius) {
    const x = lerp(a.x, b.x, u), y = lerp(a.y, b.y, u), w = lerp(a.w, b.w, u), h = lerp(a.h, b.h, u);
    set(el, 'transform', `translate(${x.toFixed(2)}px,${y.toFixed(2)}px)`);
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
endEl.innerHTML = `<h2>Harmony,${EL.br} <em>made by you.</em></h2><p>For one voice, or a whole choir.</p><p class="site">stackapella.com</p><p class="credit"></p>`;
endEl.style.cssText = `left:${END.cx - CX}px;top:${END.cy - CY}px;width:${W}px;height:${H}px`;
const [endH, endP, endSite, endCredit] = endEl.children;
endH.style.top = `${EL.h}px`; endP.style.top = `${EL.p}px`; endSite.style.top = `${EL.site}px`;
// the real voices' credit, as the app gives it (score.js creditLine, from the catalogue)
endCredit.style.top = `${EL.site + 70}px`;
endCredit.textContent = creditLine();
const END_MARK = markRects(END.cx, END.cy + EL.mark, EL.k);
// the opening note as the fold leaves it: what the pre-roll's camera sees, three times its size
const OPEN = { zoom: 3, w: (PXB - GW) * 3, h: GH * 3, r: 20 * 3 };

/* ================= the share card ================= */
const SH = { x: -20000, y: 0, w: 1400, h: 780 };
const shSet = mk('div', 'set share', W0);
const shCard = mk('div', 'card', shSet);
shCard.style.cssText = `left:${SH.x}px;top:${SH.y}px;width:${SH.w}px;height:${SH.h}px`;
shCard.innerHTML = '<h3 style="left:72px;top:72px">Amazing Grace</h3><div class="meta" style="left:74px;top:160px">Sing together</div>';
const pic = mk('div', 'pic', shCard);
pic.style.cssText = 'left:72px;top:226px;width:1256px;height:250px';
[0, 1, 2, 3].forEach((i) => S.parts[i].forEach((n) => {
    const b = mk('b', 'gem gem-bar', pic);
    b.style.cssText = `left:${(n.beat / BEATS) * 1256 + 2}px;top:${i * 62}px;width:${(n.length / BEATS) * 1256 - 4}px;height:44px;border-radius:22px;--gem:${PART_VAR[i]}`;
}));
const shChip = mk('div', 'chip', shCard);
shChip.style.cssText = 'left:72px;top:520px;--chip:var(--accent)';
shChip.innerHTML = '<i></i><span>A link for each part</span>';
const discs = [0, 1, 2, 3].map((i) => {
    const d = mk('div', 'gem disc who', shCard, NAMES[i][0]);
    d.style.cssText = `left:${590 + i * 190}px;top:600px;--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}`;
    const l = mk('div', 'who-l', shCard, i ? NAMES[i] : 'You');
    l.style.cssText = `left:${646 + i * 190}px;top:726px`;
    return { d, l };
});
const toast = mk('div', 'toast', shSet);
toast.innerHTML = `${svgCheck}<span>Link copied.</span>`;
toast.style.cssText = `left:${SH.x + SH.w - 400}px;top:${SH.y + 62}px`;
const shRing = mk('div', 'ring', shCard);

/* ================= the split screen: the four singers, each a take of you ================= */
const QUAD = { wide: { w: 954, h: 384, top: 300 }, vertical: { w: 534, h: 520, top: 600 }, square: { w: 534, h: 400, top: 268 }, thumb: { w: 1, h: 1, top: 0 } }[FORMAT];
const QPOS = [[0, QUAD.top], [W - QUAD.w, QUAD.top], [0, QUAD.top + QUAD.h + 12], [W - QUAD.w, QUAD.top + QUAD.h + 12]];
// the beam stands in from the quadrant's edge, clear of the page's buttons in the frame's corners
const QB0 = 34, QPX = 170, QBEAM = Math.round(QUAD.w * 0.45), QTOP = () => 104, QH = QUAD.h - 144;
const quads = [0, 1, 2, 3].map((i) => {
    const q = mk('div', 'q', splitEl);
    q.style.cssText = `left:${QPOS[i][0]}px;top:${QPOS[i][1]}px;width:${QUAD.w}px;height:${QUAD.h}px;--q:${PART_VAR[i]}`;
    const inner = mk('div', 'qi', q);
    const head = mk('div', 'qh', inner);
    head.innerHTML = `<span class="gem disc" style="--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}">${NAMES[i][0]}</span>${NAMES[i]}<span class="you" style="background:${PART_VAR[i]};color:${PART_INK[i]}">You</span>`;
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
    return { q, inner, strip, gems };
});

/* ================= the line each shot carries ================= */
const cap = mk('div', null, hud);
cap.id = 'cap';
cap.innerHTML = '<div class="eb"><i></i><span></span></div><div class="ln"><span></span></div>';
const capEb = cap.querySelector('.eb span'), capLn = cap.querySelector('.ln span'), capDot = cap.querySelector('.eb i');
const ACTS = { learn: ['Learn', 0], stack: ['Stack', 1], play: ['Game mode', 2], share: ['Share', 3], courses: ['Courses', 0], tools: ['Tools', 1], choirs: ['Choirs', 2], daily: ['Every day', 3] };

/* ================= the world ================= */
const sungBy = (i, t) => (t >= sec(CUE.friends[0]) - 0.01 && t < sec(CUE.end) ? FRIENDS[i] : i === 0 ? 'You' : 'You, again');

function lanes(t, o) {
    // o: { lt: the lanes' own clock (the pre-roll runs it below zero), fade: lines and gems, all:
    // every lane in, quiet: no rim or chip, hide: a gem per lane the mark has taken }
    const lt = o.lt, b = beatAt(lt);
    const singing = voicing(b) && lt >= 0;
    laneEls.forEach((L, i) => {
        const enter = i === 0 ? -99 : sec(CUE.enter[i]);
        const shown = lt >= enter - 0.35 || o.all;
        vis(L.el, shown);
        if (!shown) return;
        // the slide in: hinged at its top, it swings up into place and settles on a spring
        const u = o.all || i === 0 ? 1 : spring(lt - enter + 0.35, 1.25, 0.62);
        set(L.el, 'transform', u === 1 ? '' : `translate3d(0,${((1 - u) * 620).toFixed(2)}px,${((1 - u) * -300).toFixed(2)}px) rotateX(${((1 - u) * -70).toFixed(2)}deg)`);
        set(L.el, 'opacity', String(clamp(u * 1.4) * (o.fade ?? 1)));
        set(L.lines, 'opacity', String(o.lines ?? 1));
        set(L.name, 'opacity', String(o.lines ?? 1));
        const who = o.names ? sungBy(i, t) : RANGES[i];
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
                gl = now ? 0.65 : 0.25;
                fill = clamp((b - n.beat) / n.length);
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
    const last = o.all ? 3 : [0, 1, 2, 3].filter((i) => i === 0 || lt >= sec(CUE.enter[i])).pop();
    vis(beamEl, lt > 0 && !o.noBeam);
    if (lt > 0 && !o.noBeam) {
        set(beamEl, 'left', `${X(b)}px`);
        set(beamEl, 'top', '-40px');
        set(beamEl, 'height', `${LANES[last].top + LANES[last].h + 80}px`);
        set(beamEl, '--beam', singing || last ? 'var(--p1)' : 'var(--cream)');
        set(beamEl, 'opacity', String((o.lines ?? 1) * clamp(lt / 0.12)));
    }
    // "Slide up", while the voice is under "grace"
    const grace = S.parts[0][4];
    const ck = bump(b, grace.beat + 0.12, grace.beat + 1.1);
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
        const at = i === 0 ? -99 : sec(CUE.friends[i - 1]), d2 = t - at;
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
const CAPS = (await (await fetch('captures.json')).json()).shots;
const CUTNAME = LIGHT ? 'light' : 'dark';
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
    if (!prev?.app || (typeof prev.app.id === 'string' ? prev.app.id : '') !== id) cam.s *= lerp(0.93, 1, E.outExpo(range(t, shot.a, shot.a + 0.4)));
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
    { id: 'metronome', name: 'Metronome', what: 'Steady time for practice.' },
    { id: 'range', name: 'Vocal range test', what: 'Find the choir part to try.' },
];
const TOOLS_AT = sec(VERSE + 12), TOOL_BAR = sec(3);
const toolsEl = mk('div', 'tools', $('#stage'));
appsEl.after(toolsEl);
const toolIndex = mk('ol', 'tl-index', toolsEl);
const toolLis = TOOLS.map((x) => mk('li', null, toolIndex, x.name));
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

/* ================= the director =================
   Each shot: when it starts, which set it shows (the lanes, the split, the share card, or the app), where
   the camera is (it may move inside the shot), and the act and line it carries. A shot runs until the
   next begins. Cuts are hard and on the beat; a whip is a camera move fast enough to smear. The first
   time through the verse the harmony is built a voice at a time; the second, what comes with it. */
const INVITE_LINE = 'Invite friends to sing the other parts.';
const RECORD_LINE = 'Record each part over the others.';
const PLAY_LINE = 'Or play it as a game.';
const TOOLS_LINE = 'Tools for every practice.';
const ph = (t) => X(beatAt(t));
const centreOf = (i) => LANES[i].top + LANES[i].h / 2;
const shake = (t, at, amp) => { const d = t - at; if (d < 0) return [0, 0]; const s = Math.exp(-d / 0.09) * amp, f = Math.floor(t * 60); return [(hash(f) - 0.5) * s, (hash(f + 9) - 0.5) * s]; };
const whipIn = (t, b, dx) => dx * (1 - E.outExpo(range(t, sec(b), sec(b) + 0.32)));
/** A phone shot: the phone rises a little into place and drifts closer. */
const phoneCam = (a, b, fy = 0.5, s0 = 1.1, s1 = 1.16) => (t) => { const u = E.outExpo(range(t, a, a + 0.5)); return { fx: 0.5, fy, sx: PHONE_AT.sx, sy: PHONE_AT.sy + (1 - u) * 80, s: lerp(s0, s1, range(t, a, b)), rz: lerp(1.2, 0, u) }; };
/** A window shot: it hangs from just under the line (its bar's top near y 270) and pushes in from there. */
const winCam = (a, b, fx, s0, s1, sx = 980, sy = 330) => move(a, b, { fx, fy: 0, sx, sy, s: s0 }, { s: s1 });
// THE ARCADE: from Game mode switching on (the landing's own switch, clicked) to the end of the first time
// through, the film itself is game mode; then it switches off again into the calm second pass. Why back:
// the app's rule is "arcade where people play, calm where people read", and the second pass is for
// reading (courses, tools, a choir's parts); and seeing it switch both ways says it's one toggle.
const ARC0 = sec(27), ARC1 = sec(VERSE);
const TOGGLE = CAPS['landing-toggle'];
const SHOTS = [
    // LEARN. A close-up on the opening note: it breathes, the pickup sounds, the tune appears beside it
    { a: 0, set: 'lanes', act: 'learn', cap: 'Learn your harmony from a real singer.', capAt: 0.1, cam: (t) => { const u = E.io(range(t, 0.15, 1.05)); return { x: lerp(X(0) + PXB / 2, X(1.4) + 100, u), y: yOf(LANES[0], 62), zoom: (OPEN.zoom + 0.16 * range(t, -0.75, 0.15)) * lerp(1, 0.55, u), rz: lerp(0, -1.2, u) }; } },
    // the lane, low and close, pulling focus into it
    { a: sec(2), set: 'lanes', follow: true, act: 'learn', cap: 'Learn your harmony from a real singer.', focus: [sec(2), 0.5, 16], cam: (t) => ({ x: ph(t) + 300, y: pitchCentre(beatAt(t)) - 110, zoom: lerp(1.25, 1.32, range(t, sec(2), sec(4))), ry: -14, rx: 10 }) },
    // "grace": very close, a little low, Slide up, the hit
    { a: sec(4), set: 'lanes', act: 'learn', cap: 'Learn your harmony from a real singer.', cam: (t) => { const [dx, dy] = shake(t, sec(S.parts[0][4].hit), 14); return { x: X(4) + 260 + dx + 40 * range(t, sec(4), sec(6)), y: yOf(LANES[0], 70.4) - 70 + dy, zoom: lerp(1.85, 1.96, range(t, sec(4), sec(6))), rz: 1.5 }; } },
    // the app: Sing, the alto's notes lighting as they're hit, its words in step with the song
    { a: sec(6), set: 'app', act: 'learn', cap: 'Your notes light up as you hit them.', app: { id: 'sing', sync: true, url: 'app.stackapella.com', cam: winCam(sec(6), sec(12), 0.5, 0.9, 0.96, 1000, 300) } },
    // STACK. The studio: three parts sung and playing, the bass next
    { a: sec(12), set: 'app', act: 'stack', cap: RECORD_LINE, app: { id: 'studio', sync: true, url: 'app.stackapella.com', cam: winCam(sec(12), sec(18), 0.45, 1.08, 1.16) } },
    // top down on the lanes: two voices now, the tenor landing as the phrase turns
    { a: sec(18), set: 'lanes', follow: true, act: 'stack', cap: RECORD_LINE, cam: (t) => ({ x: ph(t) + 260, y: centreOf(1) + 60, zoom: lerp(0.86, 0.9, range(t, sec(18), sec(24))), rx: 40, ry: -8, rz: -6 }) },
    // GAME MODE. The landing page: the pointer clicks its Game mode switch on the half beat
    { a: sec(24), set: 'app', act: 'play', cap: PLAY_LINE, app: { id: 'landing-toggle', skip: (TOGGLE?.click?.at ?? 1.2) - (sec(25.5) - sec(24)), url: 'stackapella.com', cam: (t) => ({ fx: 0.56, fy: TOGGLE?.click?.y ?? 0.4, sx: 1000, sy: 600, s: lerp(1.15, 1.3, E.io(range(t, sec(24), sec(27)))) }) } },
    // the screen switches on as the arcade: the same take, in game mode, in step with the song
    { a: ARC0, set: 'app', arcade: true, act: 'play', cap: PLAY_LINE, app: { id: 'arcade', sync: true, url: 'app.stackapella.com', cam: winCam(ARC0, sec(32), 0.5, 0.9, 0.96, 1000, 300) } },
    // the arcade's Home: its stage card, STAGE 01 and the lanes
    { a: sec(32), set: 'app', arcade: true, act: 'play', cap: PLAY_LINE, app: { id: 'home-game', url: 'app.stackapella.com', cam: winCam(sec(32), sec(36), 0.5, 1.25, 1.34) } },
    // the split screen, in the arcade: four singers, each a take of you, the bass joining
    { a: sec(CUE.enter[3]), set: 'split', arcade: true, act: 'stack', cap: 'Stack your own voices, exactly in time.' },
    // SHARE. The song as a share card, flying in
    { a: sec(CUE.share), set: 'share', arcade: true, act: 'share', cap: INVITE_LINE, cam: (t) => { const a = sec(CUE.share), u = E.outExpo(range(t, a, a + 0.8)); return { x: SH.x + SH.w / 2 + 30, y: SH.y + 250, dz: lerp(-700, 0, u) - 40 * range(t, a + 0.8, sec(CUE.friends[0])), zoom: 0.88, ry: lerp(24, -3, u), rx: lerp(-10, 2, u) }; } },
    // close on the parts: friends take them, one beat each; then the screen switches off
    { a: sec(CUE.friends[0] - 0.25), set: 'share', arcade: true, act: 'share', cap: INVITE_LINE, cam: (t) => ({ x: SH.x + 930 + lerp(-30, 30, range(t, sec(CUE.friends[0]), sec(VERSE))), y: SH.y + 690, zoom: lerp(1.6, 1.68, range(t, sec(CUE.friends[0]), sec(VERSE))), rz: -1 }) },
    // COURSES. The courses page, scrolled through its groups
    { a: ARC1, set: 'app', act: 'courses', cap: 'Every course is free.', app: { id: 'courses', url: 'stackapella.com/courses', cam: winCam(ARC1, sec(VERSE + 6), 0.5, 1.5, 1.58) } },
    // the first lesson, on a phone, with no account
    { a: sec(VERSE + 6), set: 'app', act: 'courses', cap: 'Try the first lesson without an account.', app: { id: 'lesson', cam: phoneCam(sec(VERSE + 6), sec(VERSE + 12), 0.45) } },
    // TOOLS, a bar each: each one named, with the tool itself on its card
    { a: TOOLS_AT, set: 'tools', act: 'tools', cap: TOOLS_LINE },
    // CHOIRS. The director's group: each song's parts, who sings them, how far they've got
    { a: sec(VERSE + 24), set: 'app', act: 'choirs', cap: 'Give each singer a part.', app: { id: 'choir-director', url: 'app.stackapella.com', cam: winCam(sec(VERSE + 24), sec(VERSE + 30), 0.5, 1.75, 1.85) } },
    // a member's view: their part first, Learn your part
    { a: sec(VERSE + 30), set: 'app', act: 'choirs', cap: 'Singers join free.', app: { id: 'choir-member', url: 'app.stackapella.com', cam: winCam(sec(VERSE + 30), sec(VERSE + 36), 0.5, 1.75, 1.85) } },
    // EVERY DAY. Today's song, on Home
    { a: sec(VERSE + 36), set: 'app', act: 'daily', cap: 'A new challenge every day.', app: { id: 'daily', url: 'app.stackapella.com', cam: winCam(sec(VERSE + 36), sec(CUE.all), 0.45, 1.65, 1.75) } },
    // wide, high and whipped in: everyone, singing together, through "but now I"
    { a: sec(CUE.all), set: 'lanes', follow: true, names: true, all: true, cam: (t) => { const u = E.io(range(t, sec(CUE.all), sec(CUE.end - 1))); return { x: ph(t) + lerp(500, 200, u) - whipIn(t, CUE.all, 2400), y: STACK_H / 2 - 60, zoom: lerp(0.46, 0.54, u), rx: lerp(38, 20, u), ry: lerp(-36, -12, u) }; } },
    // the end card: "see." becomes the mark, and the words, held through the last chord
    { a: sec(CUE.end - 1), set: 'lanes', follow: true, names: true, all: true, quiet: true, cam: (t) => ({ x: lerp(X(CUE.end) + 200, X(CUE.end) + 380, range(t, sec(CUE.end - 1), sec(CUE.end))), y: STACK_H / 2, zoom: 0.5, rx: 6, ry: -10 }) },
    { a: sec(CUE.end), set: 'lanes', mark: 'end', cam: (t) => { const a = sec(CUE.end), u = E.io(range(t, a, a + 1)); return { x: lerp(X(CUE.end) + 380, END.cx, u), y: lerp(STACK_H / 2, END.cy, u), zoom: lerp(0.5, 1, u) * lerp(1, 1.035, range(t, a + 1, sec(CUE.fold[0]))), rx: lerp(6, 0, u), ry: lerp(-10, 0, u) }; } },
    // the pre-roll: back to the opening note, a beat before the loop starts again
    { a: sec(BEATS - 1), set: 'lanes', preroll: true, cam: (t) => SHOTS[0].cam(t - DUR) },
];
SHOTS.forEach((s, i) => { s.b = SHOTS[i + 1] ? SHOTS[i + 1].a : DUR; });
export const CUTS = SHOTS.slice(1).map((s) => s.a);
// The launch cuts (vertical, square) were made from the first film and posted as they are; this one is
// for the landing's frame only. The thumbnail (the mark alone) still renders.
const THUMB_T = 3.75;
if (FORMAT === 'vertical' || FORMAT === 'square') throw new Error('The launch cuts come from the first film (git history before the second cut); this film is wide only');
export const EDIT = { wide: [[0, DUR]], thumb: [[0, THUMB_T]] }[FORMAT];
const FRAMING = { zoom: 1, down: 0 };
function reframe(c) {
    return c;
}
// windows fast enough to earn extra motion-blur samples: the split's wipes, the card flying in, the
// whip, the mark forming, the fold, and every phone rising into place
export const FAST = [[sec(CUE.enter[3]), sec(CUE.enter[3]) + 0.5], [sec(CUE.share), sec(CUE.share) + 0.8], [sec(CUE.all), sec(CUE.all) + 0.35],
    [sec(CUE.end), sec(CUE.end) + 1.2], [sec(CUE.fold[0]), sec(BEATS - 1)], [ARC0 - 0.16, ARC0 + 0.32], [ARC1 - 0.4, ARC1 + 0.25],
    ...SHOTS.filter((s) => s.set === 'app' && typeof s.app.id === 'string' && CAPS[s.app.id]?.size === 'phone').map((s) => [s.a, s.a + 0.4]),
    ...TOOLS.map((_, k) => [TOOLS_AT + k * TOOL_BAR, TOOLS_AT + k * TOOL_BAR + 0.45])];
const FLASHES = [[sec(VERSE), 0.06], [sec(CUE.end), 0.08]];

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

/** Each line, from when it reads (most of its rise done) to when it goes: for checking that each stays long enough to read. */
export function captions() {
    const out = [];
    SHOTS.forEach((s) => {
        const last = out.at(-1);
        if (s.cap && last && last.text === s.cap && Math.abs(last.until - s.a) < 1e-6) last.until = s.b;
        else if (s.cap) out.push({ text: s.cap, from: (s.capAt ?? s.a) + 0.3, until: s.b });
    });
    return out.map((c) => ({ ...c, shown: +(c.until - c.from).toFixed(2), needs: +(0.3 * c.text.split(/\s+/).length + 1).toFixed(2) }));
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
// ?cam={"rx":40} nudges the camera of whatever shot is showing: for trying a framing in the preview
const NUDGE = Q.has('cam') ? JSON.parse(Q.get('cam')) : null;

export function film(t) {
    let shot = SHOTS[0];
    for (const s of SHOTS) if (t >= s.a) shot = s;
    for (const k in sets) vis(sets[k], shot.set === k);
    vis(splitEl, shot.set === 'split');
    vis(appsEl, shot.set === 'app');
    stageEl.classList.toggle('arcade', !!shot.arcade);
    vis(toolsEl, shot.set === 'tools');
    if (shot.set === 'tools') tools(t);
    crt(t);
    set(washEl, 'opacity', shot.wash ? '1' : '0');
    if (FORMAT === 'thumb') return thumb(t);
    const cam = shot.cam ? reframe(shot.cam(t)) : null;
    setCam(cam ? (NUDGE ? { ...cam, ...NUDGE } : cam) : {});
    // a focus pull: the picture comes into focus at the start of a shot
    const f = shot.focus ? shot.focus[2] * (1 - E.io(range(t, shot.focus[0], shot.focus[0] + shot.focus[1]))) : 0;
    set(lens, 'filter', f > 0.05 ? `blur(${f.toFixed(2)}px)` : '');
    const lt = shot.preroll ? t - DUR : t;

    if (shot.set === 'lanes') {
        let o = { lt, names: shot.names, all: shot.all, quiet: shot.quiet };
        vis(endEl, shot.mark === 'end');
        morphEls.forEach((e) => vis(e, !!shot.mark));
        if (shot.mark) {
            // the four held notes leave their lanes and become the four bars of the mark
            const beat = CUE.end, t0 = sec(beat);
            const idx = [0, 1, 2, 3].map((i) => noteIndex(i, beat));
            const dst = END_MARK;
            const fade = 1 - E.io(range(t, t0 + 0.1, t0 + 0.7));
            o = { ...o, all: true, hide: idx, fade, lines: fade, quiet: true, noBeam: fade < 0.05 };
            const fold = shot.mark === 'end' ? E.io(range(t, sec(CUE.fold[0]), sec(BEATS - 1))) : 0;
            // the opening note, in this set's world, at the size and place the pre-roll will show it
            // (its shot is reframed for the format: zoomed by FRAMING.zoom and moved down by .down)
            const z = cam.zoom, ow = OPEN.w * FRAMING.zoom, oh = OPEN.h * FRAMING.zoom;
            const gem = { x: END.cx - ow / 2 / z, y: END.cy + FRAMING.down / z - oh / 2 / z, w: ow / z, h: oh / z };
            morphEls.forEach((e, i) => {
                const u = E.snap(range(t, t0 + 0.05 + i * 0.06, t0 + 0.85 + i * 0.06));
                let to = dst[i], radius;
                if (fold > 0) {
                    // the fold: the lower bars slide up into the tune's, which becomes the opening note
                    const f1 = E.io(clamp(fold * 1.6)), f2 = E.io(clamp(fold * 1.6 - 0.6));
                    to = i === 0 ? lerp4(dst[0], gem, f2) : lerp4(lerp4(dst[i], { ...dst[0], w: dst[i].w * 0.6 }, f1), gem, f2);
                    radius = lerp(to.h / 2, (OPEN.r * FRAMING.zoom) / z, f2);
                    set(e, 'opacity', i === 0 ? '1' : (1 - clamp(fold * 1.6 - 0.5) * 2).toFixed(3));
                } else set(e, 'opacity', '1');
                const colour = E.io(range(t, t0 + 0.4, t0 + 1.2)) * (shot.mark === 'end' ? 1 - E.io(range(t, sec(CUE.fold[0]) + 0.4, sec(BEATS - 1))) : 1);
                morphBar(e, i, gemRect(i, idx[i]), to, u, colour, radius);
                set(e, '--glow', (0.3 + 0.7 * Math.exp(-Math.max(0, t - t0 - 0.9) / 0.3)).toFixed(3));
            });
            if (shot.mark === 'end') {
                const out = E.in(range(t, sec(CUE.fold[0]), sec(CUE.fold[0]) + 0.3));
                const r1 = E.rise(range(t, t0 + 0.7, t0 + 1.25)), r2 = E.rise(range(t, t0 + 0.95, t0 + 1.5)), r3 = E.rise(range(t, t0 + 1.15, t0 + 1.7));
                const r4 = E.rise(range(t, t0 + 1.5, t0 + 2.1));
                [[endH, r1], [endP, r2], [endSite, r3], [endCredit, r4]].forEach(([e, r]) => {
                    set(e, 'transform', `translateY(${((1 - r) * 40 + out * -30).toFixed(2)}px)`);
                    set(e, 'opacity', (clamp(r * 1.5) * (1 - out)).toFixed(3));
                });
            }
        }
        lanes(t, o);
        if (shot.mark === 'end' && t < sec(CUE.fold[0])) glints(t, END_MARK);
    }
    if (shot.set === 'app') app(t, shot);
    if (shot.set === 'share') share(t);
    if (shot.set === 'split') split(t, shot.a);

    /* screen space: the voice, the sparks, the specks */
    if (shot.set === 'lanes' && !shot.mark && !shot.all) { voiceTrail(lt); hits(lt); }
    if (shot.set === 'share') shareSparkles(t);
    if (shot.arcade) { /* the arcade's own dotted ground instead */ } else if (shot.set === 'lanes' || shot.set === 'share') specks(t, DUR, shot.set === 'share' ? 0.5 : 1, shot.set === 'lanes' ? cam.x * 0.8 - 1000 : SH.x);
    else if (shot.set === 'app' || shot.set === 'tools') specks(t, DUR, 0.45, -400);

    /* the line this shot carries: it rises once, and stays put across cuts that keep it */
    const text = shot.cap ?? null;
    vis(cap, !!text);
    set(hud, '--shade', text ? '1' : '0');
    if (text) {
        let at = shot.capAt ?? shot.a;
        for (let i = SHOTS.indexOf(shot) - 1; i >= 0 && SHOTS[i].cap === text; i--) at = SHOTS[i].capAt ?? SHOTS[i].a;
        let actAt = at;
        for (let i = SHOTS.indexOf(shot) - 1; i >= 0 && SHOTS[i].act === shot.act; i--) actAt = SHOTS[i].a;
        if (capLn._t !== text) { capLn._t = text; capLn.textContent = text; }
        const [eb, dot] = ACTS[shot.act];
        if (capEb._t !== eb) { capEb._t = eb; capEb.textContent = eb; capDot.style.setProperty('--dot', PART_VAR[dot]); }
        const r = E.rise(range(t, at + 0.03, at + 0.42)), re = E.rise(range(t, actAt, actAt + 0.35));
        set(capLn, 'transform', `translateY(${((1 - r) * 34).toFixed(2)}px)`);
        set(capLn, 'opacity', clamp(r * 1.6).toFixed(3));
        set(capEb.parentNode, 'opacity', clamp(re * 1.6).toFixed(3));
    }
    let fl = 0;
    for (const [a, k] of FLASHES) if (t >= a) fl += k * Math.exp(-(t - a) / 0.08);
    set(flashEl, 'opacity', Math.min(0.4, fl).toFixed(4));
}
