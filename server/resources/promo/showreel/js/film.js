// HARMONY, MADE BY YOU: the film. One world (Amazing Grace's four lanes of glass gems, the mixer, the
// creator, the share card and the end card, each at its own place in it), a split screen of the four
// singers drawn over it, and a director who cuts between shots on the song's beat. Everything is a
// pure function of t, so any frame can be rendered alone.

import { E, $, CX, CY, FORMAT, H, LIGHT, Q, W, bump, clamp, hash, lerp, mk, range, set, spring, vis } from './lib.js';
import { BEATS, CUE, DUR, S, VERSE, beatAt, chordName, sec, sung, voicing } from './score.js';
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
const seeNote = S.parts[0].at(-1), meNote = S.parts[0].find((n) => n.beat === CUE.mark);
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
endEl.innerHTML = `<h2>Harmony,${EL.br} <em>made by you.</em></h2><p>For one voice, or a whole choir.</p><p class="site">stackapella.com</p>`;
endEl.style.cssText = `left:${END.cx - CX}px;top:${END.cy - CY}px;width:${W}px;height:${H}px`;
const [endH, endP, endSite] = endEl.children;
endH.style.top = `${EL.h}px`; endP.style.top = `${EL.p}px`; endSite.style.top = `${EL.site}px`;
const END_MARK = markRects(END.cx, END.cy + EL.mark, EL.k);
const MID = { cx: X(meNote.beat + meNote.length / 2), cy: STACK_H / 2 };
const MID_MARK = markRects(MID.cx, MID.cy, 2.5);
// the opening note as the fold leaves it: what the pre-roll's camera sees, three times its size
const OPEN = { zoom: 3, w: (PXB - GW) * 3, h: GH * 3, r: 20 * 3 };

/* ================= the mixer, as the landing's hero draws it ================= */
const MIX = { x: -6000, y: 0, w: 1560, h: 900 };
const mixSet = mk('div', 'set', W0);
const mixCard = mk('div', 'card', mixSet);
mixCard.style.cssText = `left:${MIX.x}px;top:${MIX.y}px;width:${MIX.w}px;height:${MIX.h}px`;
const mixHead = mk('div', 'lane-name', mixCard);
mixHead.style.cssText = 'left:64px;top:52px;color:var(--cream);font-size:36px';
mixHead.innerHTML = '<span>Amazing Grace · first line</span><small></small>';
const mixTime = mixHead.querySelector('small');
const ROLES = ['The tune', 'Just under the tune', 'Fills the chord', 'Holds the roots'];
const TRACK = { x: 560, w: 780, b0: 0, b1: CUE.hum[0] };
const ROW = (i) => 140 + i * 145;
const mixRows = [0, 1, 2, 3].map((i) => {
    const row = mk('div', 'mrow', mixCard);
    row.style.cssText = `left:64px;top:${ROW(i)}px;width:${MIX.w - 128}px;height:120px`;
    const disc = mk('div', 'gem disc', row, NAMES[i][0]);
    disc.style.cssText = `position:relative;flex:none;--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}`;
    const nm = mk('div', 'nm', row);
    nm.innerHTML = `${NAMES[i]}<span class="you" style="background:${PART_VAR[i]};color:${PART_INK[i]}">You</span><small>${ROLES[i]}</small>`;
    const you = nm.querySelector('.you');
    const track = mk('div', 'grp', row);
    track.style.cssText = `left:${TRACK.x - 64}px;top:0;width:${TRACK.w}px;height:120px`;
    const [lo, hi] = RANGE[i];
    const bars = S.parts[i].filter((n) => n.beat < TRACK.b1).map((n) => {
        const b = mk('div', 'gem gem-bar', track);
        const x = ((n.beat - TRACK.b0) / (TRACK.b1 - TRACK.b0)) * TRACK.w, w = (Math.min(n.beat + n.length, TRACK.b1) - n.beat) / (TRACK.b1 - TRACK.b0) * TRACK.w;
        b.style.cssText = `left:${x + 2}px;top:${8 + ((hi - n.pitch) / Math.max(1, hi - lo)) * 70}px;width:${w - 4}px;height:34px;border-radius:17px;--gem:${PART_VAR[i]}`;
        return { el: b, n };
    });
    const sw = mk('div', 'gem gem-bar sw', row);
    sw.style.setProperty('--gem', PART_VAR[i]);
    return { row, disc, nm, you, bars };
});
const mixBeam = mk('div', 'beam', mixCard);
const words = mk('div', 'words', mixCard);
words.style.top = '745px';
const wordEls = S.parts[0].filter((n) => n.lyric && n.beat < TRACK.b1).map((n) => {
    const w = n.lyric.endsWith('-') ? n.lyric.slice(0, -1) : n.lyric + ' ';
    return { el: mk('span', null, words, w), n };
});
const ptr = mk('div', 'ptr', mixCard);
const ring = mk('div', 'ring', mixCard);

/* ================= the creator ================= */
// One roll for all four parts (lowest bass to the top of the tune), from beat 24 to 36, on a wash
// of the accent: the one act that doesn't stand on the plain stage.
const CR = { x: 20000, y: 0, pxb: 170, sp: 16, gh: 48, lo: 42, hi: 76, b0: CUE.hum[0], b1: S.phrases[2][1] };
const crX = (b) => CR.x + (b - CR.b0) * CR.pxb;
const crY = (p) => CR.y + (CR.hi - p) * CR.sp;
const crSet = mk('div', 'set', W0);
const crLines = mk('div', 'lines', crSet);
crLines.style.cssText = `left:${crX(CR.b0 - 1)}px;width:${CR.pxb * (CR.b1 - CR.b0 + 2)}px;top:${CR.y}px;height:${(CR.hi - CR.lo) * CR.sp}px`;
for (let p = CR.lo + 1; p < CR.hi; p++) mk('i', p % 12 === 0 ? 'c' : '', crLines).style.top = `${(CR.hi - p) * CR.sp - 1}px`;
const humCv = mk('canvas', null, crSet);
const HUM = { x: crX(CR.b0 - 1), y: CR.y - 40, w: CR.pxb * (CR.b1 - CR.b0 + 2), h: (CR.hi - CR.lo) * CR.sp + 80, k: 1.5 };
humCv.width = HUM.w * HUM.k; humCv.height = HUM.h * HUM.k;
humCv.style.cssText = `position:absolute;left:${HUM.x}px;top:${HUM.y}px;width:${HUM.w}px;height:${HUM.h}px`;
const hum = humCv.getContext('2d');
const crGems = [0, 1, 2, 3].map((i) => S.parts[i].filter((n) => n.beat >= CR.b0 && n.beat < CR.b1).map((n) => {
    const g = mk('div', 'gem gem-bar', crSet);
    g.style.cssText = `left:${crX(n.beat) + 4}px;top:${crY(n.pitch) - CR.gh / 2}px;width:${n.length * CR.pxb - 8}px;height:${CR.gh}px;border-radius:16px;--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]};font-size:24px`;
    if (i === 0 && n.lyric) g.textContent = n.lyric;
    return { el: g, n, i };
}));
// When each note of the tune becomes a gem: as you finish humming it. The harmony under a note
// blooms with it, or at the bloom for the notes hummed before it.
const formed = (n) => n.beat + Math.min(0.62, n.length * 0.62);
const tuneAt = (b) => S.parts[0].find((n) => n.beat <= b + 1e-6 && n.beat + n.length > b + 1e-6);
const bloomBeat = (n, i) => {
    if (i === 0) return formed(n);
    const tn = tuneAt(n.beat);
    return Math.max(CUE.bloom + i * 0.18 + (n.beat - CR.b0) * 0.04, tn ? formed(tn) + i * 0.08 : n.beat);
};
const CR_CHORDS = S.chords.filter((c) => c.beat > CR.b0 && c.beat < CR.b1).map((c, k, all) => ({ c, b0: c.beat, b1: all[k + 1]?.beat ?? CR.b1 }));
const BLOCK_Y = crY(CR.lo) + 44, BLOCK_H = 130;
const blocks = CR_CHORDS.map(({ c, b0, b1 }) => {
    const e = mk('div', 'block', crSet, chordName(c));
    e.style.cssText = `left:${crX(b0) + 5}px;top:${BLOCK_Y}px;width:${(b1 - b0) * CR.pxb - 10}px;height:${BLOCK_H}px`;
    return e;
});
const crBeam = mk('div', 'beam', crSet);
crBeam.style.cssText = `top:${CR.y - 30}px;height:${(CR.hi - CR.lo) * CR.sp + 60}px`;

/* ================= the share card ================= */
const SH = { x: -20000, y: 0, w: 1400, h: 780 };
const shSet = mk('div', 'set share', W0);
const shCard = mk('div', 'card', shSet);
shCard.style.cssText = `left:${SH.x}px;top:${SH.y}px;width:${SH.w}px;height:${SH.h}px`;
shCard.innerHTML = '<h3 style="left:72px;top:72px">Amazing Grace</h3><div class="meta" style="left:74px;top:160px">4 voices · stacked</div>';
const pic = mk('div', 'pic', shCard);
pic.style.cssText = 'left:72px;top:226px;width:1256px;height:250px';
[0, 1, 2, 3].forEach((i) => S.parts[i].forEach((n) => {
    const b = mk('b', 'gem gem-bar', pic);
    b.style.cssText = `left:${(n.beat / BEATS) * 1256 + 2}px;top:${i * 62}px;width:${(n.length / BEATS) * 1256 - 4}px;height:44px;border-radius:22px;--gem:${PART_VAR[i]}`;
}));
const shChip = mk('div', 'chip', shCard);
shChip.style.cssText = 'left:72px;top:520px;--chip:var(--accent)';
shChip.innerHTML = '<i></i><span>Anyone with the link</span>';
const discs = [0, 1, 2, 3].map((i) => {
    const d = mk('div', 'gem disc who', shCard, NAMES[i][0]);
    d.style.cssText = `left:${590 + i * 190}px;top:600px;--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}`;
    const l = mk('div', 'who-l', shCard, i ? 'Open part' : 'You');
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
const QB0 = 10, QPX = 170, QBEAM = Math.round(QUAD.w * 0.45), QTOP = () => 104, QH = QUAD.h - 144;
const quads = [0, 1, 2, 3].map((i) => {
    const q = mk('div', 'q', splitEl);
    q.style.cssText = `left:${QPOS[i][0]}px;top:${QPOS[i][1]}px;width:${QUAD.w}px;height:${QUAD.h}px;--q:${PART_VAR[i]}`;
    const inner = mk('div', 'qi', q);
    const head = mk('div', 'qh', inner);
    head.innerHTML = `<span class="gem disc" style="--gem:${PART_VAR[i]};--gem-ink:${PART_INK[i]}">${NAMES[i][0]}</span>${NAMES[i]}<span class="you" style="background:${PART_VAR[i]};color:${PART_INK[i]}">You</span>`;
    const strip = mk('div', 'strip', inner);
    const [lo, hi] = RANGE[i];
    const gems = S.parts[i].filter((n) => n.beat + n.length > QB0 && n.beat < 22).map((n) => {
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
const ACTS = { learn: ['Learn', 0], stack: ['Stack', 1], make: ['Make your own', 2], share: ['Share', 3] };

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
    vis(r, d >= 0 && d < 0.5);
    if (d >= 0 && d < 0.5) {
        set(r, 'transform', `translate(${to.x}px,${to.y}px) scale(${(1 + 2.2 * E.out(d / 0.5)).toFixed(3)})`);
        set(r, 'opacity', (0.8 * (1 - d / 0.5)).toFixed(3));
    }
}

function mixer(t) {
    const b = beatAt(t);
    const k = CUE.forwardPart[Math.max(0, CUE.forward.findLastIndex((f) => b >= f))];
    mixRows.forEach((r, i) => {
        const fwd = i === k;
        set(r.row, 'opacity', fwd ? '1' : '0.62');
        set(r.disc, '--glow', fwd ? '0.9' : '0.3');
        vis(r.you, fwd);
        for (const bar of r.bars) {
            const lit = b >= bar.n.beat;
            set(bar.el, '--glow', lit && b < bar.n.beat + bar.n.length ? '0.8' : lit ? '0.3' : '0.1');
        }
    });
    for (const w of wordEls) w.el.classList.toggle('lit', b >= w.n.beat);
    const now = `4 voices · ${clock(t)} / ${clock(sec(VERSE))}`;
    if (mixTime._t !== now) { mixTime._t = now; mixTime.textContent = now; }
    const px = TRACK.x + ((Math.min(b, TRACK.b1) - TRACK.b0) / (TRACK.b1 - TRACK.b0)) * TRACK.w;
    set(mixBeam, 'left', `${px}px`); set(mixBeam, 'top', `${ROW(0) - 10}px`); set(mixBeam, 'height', `${ROW(3) + 140 - ROW(0)}px`);
    set(mixBeam, '--beam', PART_VAR[k]);
    // tap a voice to bring it forward: the landing's own words for its mixer
    fingertip(ptr, ring, t, CUE.forward.map((fb, j) => ({ t: sec(fb), x: 64 + 180, y: ROW(CUE.forwardPart[j]) + 60 })));
}

function creator(t) {
    const b = beatAt(t);
    // the hum: your voice drawn as a line in the accent, as you sing it
    hum.setTransform(HUM.k, 0, 0, HUM.k, 0, 0);
    hum.clearRect(0, 0, HUM.w, HUM.h);
    hum.lineCap = 'round'; hum.lineJoin = 'round';
    const line = (LIGHT ? T.accent : T.cream).join(','), haze = T.accent.join(',');
    const wob = (x) => 0.22 * Math.sin(x * 9.1) + 0.14 * Math.sin(x * 17.3 + 2);
    for (const pass of [0, 1]) {
        hum.beginPath();
        let pen = false;
        for (let x = CR.b0; x <= Math.min(b, CR.b1); x += 0.02) {
            if (!voicing(x)) { pen = false; continue; }
            const px = crX(x) - HUM.x, py = crY(S.voice(x) + wob(x)) - HUM.y;
            if (!pen) hum.moveTo(px, py); else hum.lineTo(px, py);
            pen = true;
        }
        hum.strokeStyle = pass ? `rgba(${line},0.95)` : `rgba(${haze},0.35)`;
        hum.lineWidth = pass ? 6 : 26;
        hum.stroke();
    }
    if (b <= CR.b1 && voicing(b)) {
        const px = crX(b) - HUM.x, py = crY(S.voice(b) + wob(b)) - HUM.y;
        const gr = hum.createRadialGradient(px, py, 0, px, py, 56);
        gr.addColorStop(0, `rgba(${haze},0.7)`); gr.addColorStop(1, `rgba(${haze},0)`);
        hum.fillStyle = gr; hum.fillRect(px - 56, py - 56, 112, 112);
        hum.fillStyle = `rgba(${line},1)`; hum.beginPath(); hum.arc(px, py, 11, 0, 6.283); hum.fill();
    }
    crGems.forEach((list, i) => list.forEach((g) => {
        const at = bloomBeat(g.n, i), d = b - at;
        vis(g.el, d >= 0);
        if (d < 0) return;
        const u = E.outBack(clamp(d / 0.5));
        const tn = i ? tuneAt(g.n.beat) : null;
        const dy = tn ? (crY(tn.pitch) - crY(g.n.pitch)) * (1 - E.outQuint(clamp(d / 0.55))) : 0;
        set(g.el, 'transform', `translateY(${dy.toFixed(2)}px) scale(${(0.3 + 0.7 * u).toFixed(4)})`);
        set(g.el, 'opacity', clamp(d / 0.08).toFixed(3));
        const sounding = b >= g.n.beat && b < g.n.beat + g.n.length;
        set(g.el, '--glow', (sounding ? 0.75 : 0.3 + 0.6 * Math.exp(-d / 0.25)).toFixed(3));
    }));
    blocks.forEach((e, k) => {
        const at = sec(CUE.blocks[k]), d = t - at;
        vis(e, d > -0.3);
        if (d <= -0.3) return;
        const u = d < 0 ? E.in(clamp((d + 0.3) / 0.3)) : 1;
        const y = d < 0 ? lerp(-300, 0, u) : 14 * Math.exp(-d / 0.08) * Math.sin(d * 40);
        set(e, 'transform', `translateY(${y.toFixed(2)}px)`);
        set(e, 'opacity', clamp((d + 0.3) / 0.12).toFixed(3));
        const sounding = b >= CR_CHORDS[k].b0 && b < CR_CHORDS[k].b1;
        set(e, '--glow', (d < 0 ? 0 : Math.max(sounding ? 0.5 : 0.12, Math.exp(-d / 0.25))).toFixed(3));
    });
    set(crBeam, 'left', `${crX(clamp(b, CR.b0, CR.b1))}px`);
    set(crBeam, '--beam', b >= CUE.bloom ? 'var(--cream)' : 'var(--accent)');
}

function share(t) {
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
        const txt = on ? FRIENDS[i] : 'Open part';
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
        set(Qd.q, 'clipPath', `inset(0 ${((1 - w) * 100).toFixed(2)}% 0 0 round 28px)`);
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

/* ================= the director =================
   Each shot: when it starts, which set it shows, where the camera is (it may move inside the shot),
   the act and the line it carries. A shot runs until the next begins. Cuts are hard and on the beat;
   a whip is a camera move fast enough to smear, ending or starting a shot. */
const STACK_LINE = 'Stack your own voices, exactly in time.';
const blocksCam = (t) => { const u = E.io(range(t, sec(CUE.pad), sec(CUE.share))); return { x: crX((CR.b0 + CR.b1) / 2 + 0.5), y: crY(59), zoom: lerp(0.66, 0.7, u), rx: lerp(18, 10, u), ry: lerp(-14, 6, u) }; };
const INVITE_LINE = 'Invite friends to sing the other parts.';
const ph = (t) => X(beatAt(t));
const centreOf = (i) => LANES[i].top + LANES[i].h / 2;
const shake = (t, at, amp) => { const d = t - at; if (d < 0) return [0, 0]; const s = Math.exp(-d / 0.09) * amp, f = Math.floor(t * 60); return [(hash(f) - 0.5) * s, (hash(f + 9) - 0.5) * s]; };
const whipOut = (t, b, dx) => dx * E.inExpo(range(t, sec(b) - 0.28, sec(b)));
const whipIn = (t, b, dx) => dx * (1 - E.outExpo(range(t, sec(b), sec(b) + 0.32)));
const SHOTS = [
    // LEARN. A close-up on the opening note: it breathes, its pickup sounds, the tune appears beside it
    { a: 0, set: 'lanes', act: 'learn', cap: 'Learn a part with a guide voice.', capAt: 0.2, cam: (t) => { const u = E.io(range(t, 0.3, 1.25)); return { x: lerp(X(0) + PXB / 2, X(1.4) + 100, u), y: yOf(LANES[0], 62), zoom: (OPEN.zoom + 0.16 * range(t, -0.625, 0.3)) * lerp(1, 0.55, u), rz: lerp(0, -1.2, u) }; } },
    // the lane, low and close, pulling focus into it
    { a: sec(2), set: 'lanes', follow: true, act: 'learn', cap: 'Learn a part with a guide voice.', focus: [sec(2), 0.5, 16], cam: (t) => ({ x: ph(t) + 300, y: pitchCentre(beatAt(t)) - 110, zoom: lerp(1.25, 1.32, range(t, sec(2), sec(4))), ry: -14, rx: 10 }) },
    // "grace": very close, a little low, Slide up, the hit
    { a: sec(4), set: 'lanes', act: 'learn', cap: 'Learn a part with a guide voice.', cam: (t) => { const [dx, dy] = shake(t, sec(S.parts[0][4].hit), 14); return { x: X(4) + 260 + dx + 40 * range(t, sec(4), sec(6)), y: yOf(LANES[0], 70.4) - 70 + dy, zoom: lerp(1.85, 1.96, range(t, sec(4), sec(6))), rz: 1.5 }; } },
    // the pitch camera pushes in on the held "sweet"
    { a: sec(6), set: 'lanes', follow: true, act: 'learn', cap: 'Your notes light up as you hit them.', cam: (t) => { const push = E.io(range(t, sec(7.6), sec(8.9))); return { x: ph(t) + lerp(300, 160, push), y: pitchCentre(beatAt(t)) - lerp(120, 170, push), zoom: lerp(1.15, 1.6, push), ry: lerp(-14, -4, push) }; } },
    // wide: the line sung, every note lit, the camera craning up
    { a: sec(9), set: 'lanes', follow: true, act: 'learn', cap: 'Your notes light up as you hit them.', cam: (t) => { const u = E.io(range(t, sec(9), sec(12))); return { x: lerp(ph(t) - 300, X(6), u), y: lerp(centreOf(0) - 140, centreOf(0) - 40, u), zoom: lerp(0.8, 0.66, u), rx: lerp(0, 12, u) }; } },
    // STACK. Tilted: the tune alone, room below it
    { a: sec(12), set: 'lanes', follow: true, act: 'stack', cap: STACK_LINE, cam: (t) => ({ x: ph(t) + 380, y: centreOf(0) + 230, zoom: lerp(0.76, 0.8, range(t, sec(12), sec(13))), rx: 16, ry: -24 }) },
    // low and close: the alto swings up into place
    { a: sec(CUE.enter[1]), set: 'lanes', follow: true, act: 'stack', cap: STACK_LINE, cam: (t) => ({ x: ph(t) + 320, y: (centreOf(0) + centreOf(1)) / 2 - 40, zoom: lerp(0.84, 0.9, range(t, sec(13), sec(15))), rx: 24, ry: -34 }) },
    // top down: the tenor lands, the lanes laid out like a table
    { a: sec(CUE.enter[2]), set: 'lanes', follow: true, act: 'stack', cap: STACK_LINE, cam: (t) => ({ x: ph(t) + 260, y: centreOf(1) + 60, zoom: lerp(0.86, 0.9, range(t, sec(15), sec(16))), rx: 40, ry: -8, rz: -6 }) },
    // the split screen, under the line: four singers, each a take of you, the bass joining
    { a: sec(CUE.enter[3]), set: 'split', act: 'stack', cap: STACK_LINE },
    // along the beam: every part on it at once
    { a: sec(18), set: 'lanes', follow: true, act: 'stack', cap: STACK_LINE, cam: (t) => ({ x: ph(t) + 80, y: STACK_H / 2 - 40, zoom: lerp(0.78, 0.84, range(t, sec(18), sec(19))), rx: 8, ry: -62 }) },
    // "me.": the four held notes become the mark
    { a: sec(CUE.mark), set: 'lanes', mark: 'mid', cam: (t) => { const u = E.io(range(t, sec(19), sec(20.4))); return { x: lerp(ph(t) + 300, MID.cx, u), y: lerp(STACK_H / 2, MID.cy, u), zoom: lerp(0.5, 0.86, u), rx: lerp(10, 0, u), ry: lerp(-18, 0, u) }; } },
    // the mixer, flat and square on, as on the page; tap a voice to bring it forward; then a whip out
    { a: sec(CUE.forward[0]), set: 'mixer', act: 'stack', cap: 'Sing the part that suits you.', cam: (t) => ({ x: MIX.x + MIX.w / 2 + whipOut(t, CUE.hum[0], 2600), y: MIX.y + MIX.h / 2 - 140, zoom: lerp(0.86, 0.9, range(t, sec(21), sec(CUE.hum[0]))), rz: 4 * E.inExpo(range(t, sec(CUE.hum[0]) - 0.28, sec(CUE.hum[0]))) }) },
    // MAKE YOUR OWN, on the accent's wash. Whip in on the hum
    { a: sec(CUE.hum[0]), set: 'creator', wash: true, act: 'make', cap: 'Hum a tune.', cam: (t) => { const b = beatAt(t), h = CUE.hum[0]; return { x: crX(b) + 120 - whipIn(t, h, 1800), y: crY(S.voice(Math.max(h, b))) - 60, zoom: lerp(1.75, 1.85, range(t, sec(h), sec(CUE.bloom))), rz: -1.5 - 4 * (1 - E.outExpo(range(t, sec(h), sec(h) + 0.32))) }; } },
    // from above: the harmony blooms under the tune
    { a: sec(CUE.bloom), set: 'creator', wash: true, act: 'make', cap: 'Hear it harmonised.', cam: (t) => ({ x: crX(CUE.bloom + 2) + lerp(0, 120, range(t, sec(CUE.bloom), sec(CUE.pad))), y: crY(60) + 20, zoom: lerp(0.9, 0.94, range(t, sec(CUE.bloom), sec(CUE.pad))), rx: 36, rz: -4 }) },
    // the chords land as glass blocks; a slow orbit, and the line changes once they are down
    { a: sec(CUE.pad), set: 'creator', wash: true, act: 'make', cap: 'Hear it harmonised.', cam: (t) => blocksCam(t) },
    { a: sec(CUE.write), set: 'creator', wash: true, act: 'make', cap: 'Write a song.', cam: (t) => blocksCam(t) },
    // SHARE. The song as a share card, flying in on the held "found,"
    { a: sec(CUE.share), set: 'share', act: 'share', cap: 'Share the whole harmony.', cam: (t) => { const a = sec(CUE.share), u = E.outExpo(range(t, a, a + 0.8)); return { x: SH.x + SH.w / 2 + 30, y: SH.y + 250, dz: lerp(-700, 0, u) - 40 * range(t, a + 0.8, sec(CUE.friends[0])), zoom: 0.88, ry: lerp(24, -3, u), rx: lerp(-10, 2, u) }; } },
    // close on the parts: friends take them, one beat each
    { a: sec(CUE.friends[0]), set: 'share', act: 'share', cap: INVITE_LINE, cam: (t) => ({ x: SH.x + 930 + lerp(-30, 30, range(t, sec(CUE.friends[0]), sec(CUE.all))), y: SH.y + 690, zoom: lerp(1.72, 1.8, range(t, sec(CUE.friends[0]), sec(CUE.all))), rz: -1 }) },
    // wide, high and whipped in: everyone, singing together, through the held "now"
    { a: sec(CUE.all), set: 'lanes', follow: true, names: true, all: true, act: 'share', cap: INVITE_LINE, cam: (t) => { const u = E.io(range(t, sec(CUE.all), sec(CUE.end - 1))); return { x: ph(t) + lerp(500, 200, u) - whipIn(t, CUE.all, 2400), y: STACK_H / 2 - 60, zoom: lerp(0.46, 0.54, u), rx: lerp(38, 20, u), ry: lerp(-36, -12, u) }; } },
    // the end card: "see." becomes the mark, and the words, held through the fermata
    { a: sec(CUE.end - 1), set: 'lanes', follow: true, names: true, all: true, quiet: true, cam: (t) => ({ x: lerp(X(CUE.end) + 200, X(CUE.end) + 380, range(t, sec(CUE.end - 1), sec(CUE.end))), y: STACK_H / 2, zoom: 0.5, rx: 6, ry: -10 }) },
    { a: sec(CUE.end), set: 'lanes', mark: 'end', cam: (t) => { const a = sec(CUE.end), u = E.io(range(t, a, a + 1)); return { x: lerp(X(CUE.end) + 380, END.cx, u), y: lerp(STACK_H / 2, END.cy, u), zoom: lerp(0.5, 1, u) * lerp(1, 1.035, range(t, a + 1, sec(CUE.fold[0]))), rx: lerp(6, 0, u), ry: lerp(-10, 0, u) }; } },
    // the pre-roll: back to the opening note, a beat before the loop starts again
    { a: sec(BEATS - 1), set: 'lanes', preroll: true, cam: (t) => SHOTS[0].cam(t - DUR) },
];
SHOTS.forEach((s, i) => { s.b = SHOTS[i + 1] ? SHOTS[i + 1].a : DUR; });
export const CUTS = SHOTS.slice(1).map((s) => s.a);
// What each format plays, as stretches of the film end to end. The joins fall where the music can
// take them: the vertical cut goes from "like" to the last chord, "see." (G after G/B), and the square
// one also leaves "grace," for "that saved"; both still end on the opening note, so they loop too.
const THUMB_T = sec(6);
export const EDIT = {
    wide: [[0, DUR]],
    vertical: [[0, sec(CUE.mark)], [sec(CUE.end), DUR]],
    square: [[0, sec(6)], [sec(12), sec(CUE.mark)], [sec(CUE.end), DUR]],
    thumb: [[0, THUMB_T]],
}[FORMAT];
// How each format reframes the film's camera: a zoom, and a shift down (screen px) that keeps the
// picture under the line each shot carries. The end card and the split screen are laid out for it.
const FRAMING = { wide: { zoom: 1, down: 0 }, vertical: { zoom: 1, down: 150 }, square: { zoom: 0.8, down: 80 }, thumb: { zoom: 1, down: 0 } }[FORMAT];
function reframe(c, shot, t) {
    // the end card is laid out for the frame itself, so its shot eases out of the framing as it arrives
    const w = shot.mark === 'end' ? 1 - E.io(range(t, sec(CUE.end), sec(CUE.end) + 1)) : 1;
    const zoom = c.zoom * lerp(1, FRAMING.zoom, w);
    // a shot that follows the playhead keeps it the same share of the way across a narrower frame
    const x = shot.follow ? ph(t) + (c.x - ph(t)) * Math.min(1, W / 1920 / FRAMING.zoom) : c.x;
    return { ...c, x, zoom, y: c.y - (FRAMING.down * w) / zoom };
}
// windows fast enough to earn extra motion-blur samples: the lanes swinging in, the split's wipes,
// the mark forming, the whips, the card flying in, the fold
export const FAST = [[sec(13) - 0.4, sec(13) + 0.5], [sec(15) - 0.4, sec(15) + 0.5], [sec(16), sec(16) + 0.5], [sec(19), sec(20.4)],
    [sec(CUE.hum[0]) - 0.3, sec(CUE.hum[0]) + 0.35], [sec(CUE.share), sec(CUE.share) + 0.8], [sec(CUE.all), sec(CUE.all) + 0.35],
    [sec(CUE.end), sec(CUE.end) + 1.2], [sec(CUE.fold[0]), sec(BEATS - 1)]];
const FLASHES = [[sec(CUE.mark), 0.1], [sec(CUE.bloom), 0.06], [sec(CUE.blocks[0]), 0.06], [sec(CUE.end), 0.08]];

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

const sets = { lanes: laneSet, mixer: mixSet, creator: crSet, share: shSet };
// ?cam={"rx":40} nudges the camera of whatever shot is showing: for trying a framing in the preview
const NUDGE = Q.has('cam') ? JSON.parse(Q.get('cam')) : null;

export function film(t) {
    let shot = SHOTS[0];
    for (const s of SHOTS) if (t >= s.a) shot = s;
    for (const k in sets) vis(sets[k], shot.set === k);
    vis(splitEl, shot.set === 'split');
    set(washEl, 'opacity', shot.wash ? '1' : '0');
    if (FORMAT === 'thumb') return thumb(t);
    const cam = shot.cam ? reframe(shot.cam(t), shot, t) : null;
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
            const beat = shot.mark === 'mid' ? CUE.mark : CUE.end, t0 = sec(beat);
            const idx = [0, 1, 2, 3].map((i) => noteIndex(i, beat));
            const dst = shot.mark === 'mid' ? MID_MARK : END_MARK;
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
                [[endH, r1], [endP, r2], [endSite, r3]].forEach(([e, r]) => {
                    set(e, 'transform', `translateY(${((1 - r) * 40 + out * -30).toFixed(2)}px)`);
                    set(e, 'opacity', (clamp(r * 1.5) * (1 - out)).toFixed(3));
                });
            }
        }
        lanes(t, o);
        if (shot.mark === 'end' && t < sec(CUE.fold[0])) glints(t, END_MARK);
    }
    if (shot.set === 'mixer') mixer(t);
    if (shot.set === 'creator') creator(t);
    if (shot.set === 'share') share(t);
    if (shot.set === 'split') split(t, shot.a);

    /* screen space: the voice, the sparks, the specks */
    if (shot.set === 'lanes' && !shot.mark && !shot.all) { voiceTrail(lt); hits(lt); }
    if (shot.set === 'share') shareSparkles(t);
    if (shot.set !== 'split') {
        const c = cam;
        specks(t, DUR, shot.set === 'share' || shot.set === 'mixer' ? 0.5 : 1, shot.set === 'lanes' || shot.set === 'creator' ? c.x * 0.8 - 1000 : shot.set === 'mixer' ? MIX.x : SH.x);
    }

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
