// The Note lane's drawing (components/sing/lane.js), with no Vue and nothing the public pages can't
// load: the app's Sing lane and the landing's embedded lane (js/embed/lane.js) both draw with it, so
// every improvement shows in both. Give it a canvas, set what to draw (`set`, the Lane's props), and
// draw; a theme change wants readTokens(). Drawn from `position` (seconds), which the caller reads
// off the AudioContext clock: nothing here keeps time.

import { token } from './colors.js';
import { camera, frameFor, moodFor, phraseFrame, stepCamera, viewOf, CAMERA } from './camera.js';
import { beam, gemBar, shades, sparkles, stage, vignette } from './glass.js';
import { sungEnd } from './scoring.js';

const NOW_AT = 0.28; // the now line's place across the lane
const KEYS = 34; // the keyboard's width
const EDGE = 22; // room at the top and bottom for the chords and the other parts' names
const BLACK = new Set([1, 3, 6, 8, 10]);
const PULSE_BEFORE = 1.2; // the next note breathes this long before it starts (seconds)
const HIT_SECONDS = 0.5; // a hit's flash, pop and sparkles
const stillMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/** What a lane draws, and its defaults (the Lane's props). */
export const LANE_DEFAULTS = {
    notes: [], // yours: { beat, length, pitch }
    words: [], // your syllables: { beat, text }
    others: [], // [{ name, color, notes, above }]
    transpose: 0,
    beatSeconds: 0.75,
    position: 0, // seconds
    color: '#4ccfb0',
    state: 'listen', // listen, turn or recording: the now line's colour
    noteName: () => '', // MIDI to a name ('' for none)
    keyboard: true,
    chords: null, // [{ seconds, name }] while the chords play
    loop: null, // { startSeconds, endSeconds }
    voice: [], // your voice: [{ seconds, midi, level, alpha }]
    lit: new Set(), // the beats of the notes you hit
    hits: new Map(), // beat: [[fromSeconds, toSeconds]] where you were in tune
    tolerance: 35, // cents either side: the in-tune band (bars are at least this tall)
    hint: null, // 'higher' or 'lower', shown with an arrow at the head
    hintText: '', // the hint in words, as a chip beside the head
    secondsShown: 6, // the lane's width, in seconds (the zoom across)
    trail: null, // your voice's ribbon and dot, if not in your colour (the landing: white, so it reads over gold gems)
    trailGlow: null, // the dot's glow, if not the trail's colour
    ghost: [], // your best take's pitch line, faint, to race (game mode's ghost): [{ seconds, midi|null }]
};

/** A colour a canvas can use: a CSS variable (var(--x)) read from the page, else as it is. */
function canvasColor(value) {
    const m = /^var\((--[\w-]+)\)$/.exec(String(value ?? '').trim());
    return m ? getComputedStyle(document.documentElement).getPropertyValue(m[1]).trim() || '#ffffff' : value;
}

/** A breath mark (a comma, as on a score) centred at (cx, cy): the Sing lane's, and the studio's part view's. */
export function breathComma(g, cx, cy, size, colour) {
    g.fillStyle = colour;
    g.strokeStyle = colour;
    g.globalAlpha = 0.85;
    g.beginPath();
    g.arc(cx, cy, size * 0.28, 0, Math.PI * 2);
    g.fill();
    g.lineWidth = Math.max(1.5, size * 0.18);
    g.lineCap = 'round';
    g.beginPath();
    g.moveTo(cx + size * 0.24, cy + size * 0.05);
    g.quadraticCurveTo(cx + size * 0.2, cy + size * 0.55, cx - size * 0.22, cy + size * 0.8);
    g.stroke();
    g.globalAlpha = 1;
}

export class LanePainter {
    /** `canvas` to draw on; `host`, an element whose data-head says whether your voice's dot is live (for the flows). */
    constructor(canvas, host = null) {
        this.canvas = canvas;
        this.host = host;
        Object.assign(this, LANE_DEFAULTS);
        this.cam = camera();
        this.hitAt = new Map();
        this.inTuneFor = 0;
        this.drawnAt = 0;
        this.readTokens();
    }

    /** What to draw (any of LANE_DEFAULTS' keys); the notes' timing is worked out again when they change. */
    set(props) {
        const notesChanged = ['notes', 'beatSeconds', 'transpose', 'words'].some((k) => k in props && props[k] !== this[k]);
        if ('lit' in props && props.lit !== this.lit) {
            // A note just hit: its flash, pop and sparkles start now.
            for (const beat of props.lit) if (!this.lit?.has(beat)) this.hitAt.set(beat, performance.now());
        }
        Object.assign(this, props);
        if (notesChanged || !this.timed) this.retime();
        return this;
    }

    /** Your notes in seconds and sounding pitch (for the camera), the widest frame, the phrases and the words by beat. */
    retime() {
        this.timed = this.notes.map((n) => ({ start: n.beat * this.beatSeconds, end: (n.beat + n.length) * this.beatSeconds, pitch: n.pitch + this.transpose }));
        const pitches = this.timed.map((n) => n.pitch);
        this.maxSpan = pitches.length ? Math.max(CAMERA.minSpan, Math.max(...pitches) - Math.min(...pitches) + 2 * CAMERA.pad) : CAMERA.minSpan;
        const phrases = [];
        for (const n of this.timed) {
            const last = phrases.at(-1);
            if (last && n.start - last[1] < this.beatSeconds - 1e-6) last[1] = n.end;
            else phrases.push([n.start, n.end]);
        }
        this.phrases = phrases;
        this.wordAt = new Map(this.words.map((w) => [Math.round(w.beat * 1000), w.text.replace(/-$/, '')]));
    }

    /** The theme's colours for the canvas (a theme change reads them again). */
    readTokens() {
        this.ink = token('ink', '#15110e');
        this.panel = token('panel', '#1e1915');
        this.line = token('line', '#2e2721');
        this.cream = token('cream', '#f4eee6');
        this.muted = token('muted', '#a39a90');
        this.record = token('record', '#f0654a');
        this.accent = token('accent', '#ff79da');
        this.light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
    }

    /** Stops any frame still to come (the lane is going). */
    destroy() {
        cancelAnimationFrame(this.again);
    }

    /** The camera's frame now: framing the notes ahead and your voice, eased; a phrase at a time with reduced motion. */
    frame(dt, nowNote) {
        const still = stillMotion();
        const last = this.voice.at(-1);
        const live = last && this.position - last.seconds < 0.15 ? last.midi : null;
        if (nowNote && live !== null && Math.abs(live - (nowNote.pitch + this.transpose)) * 100 <= this.tolerance) this.inTuneFor += dt;
        else this.inTuneFor = 0;
        const options = { voice: live, maxSpan: this.maxSpan };
        let target = still ? phraseFrame(this.timed, this.position, this.phrases, options) : frameFor(this.timed, this.position, options);
        if (target && !still) target = { ...target, span: Math.min(this.maxSpan * CAMERA.phraseOut, Math.max(CAMERA.minSpan * CAMERA.lockIn, target.span * moodFor(this.timed, this.position, { inTuneFor: this.inTuneFor }))) };
        this.cam = stepCamera(this.cam, target ?? (this.cam.centre === null ? { centre: 60, span: CAMERA.minSpan } : null), dt, { still });
        return viewOf(this.cam);
    }
    draw() {
        const canvas = this.canvas;
        if (!canvas) return;
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        if (!width || !height) return;
        if (canvas.width !== Math.round(width * ratio) || canvas.height !== Math.round(height * ratio)) {
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
        }
        const clock = performance.now();
        const dt = this.drawnAt ? Math.min(1, (clock - this.drawnAt) / 1000) : 1;
        this.drawnAt = clock;
        const still = stillMotion();
        const g = canvas.getContext('2d');
        g.direction = 'ltr'; // time runs left to right on any page; a right-to-left page would right-align every label
        g.setTransform(ratio, 0, 0, ratio, 0, 0);
        g.clearRect(0, 0, width, height);
        const seconds = (beat) => beat * this.beatSeconds;
        const nowNote = this.notes.find((n) => this.position >= seconds(n.beat) && this.position < seconds(n.beat + n.length));
        const { low, high } = this.frame(dt, nowNote);
        const left = this.keyboard ? KEYS : 0;
        const inner = height - 2 * EDGE;
        const rowH = inner / (high - low);
        // A pitch's centre line (MIDI may be fractional): the camera's frame across the lane's height.
        const mid = (pitch) => EDGE + (high - pitch) * rowH;
        const pps = (width - left) / this.secondsShown;
        const nowX = left + (width - left) * NOW_AT;
        const x = (s) => nowX + (s - this.position) * pps;
        const rows = [];
        for (let p = Math.ceil(low - 0.5); p <= Math.floor(high + 0.5); p++) rows.push(p);

        // The stage: faint semitone lines (a C stronger), a floor glow and drifting specks.
        stage(g, { left, top: EDGE, width, bottom: height - EDGE, lines: rows.map((p) => ({ y: mid(p) + rowH / 2, strong: ((p % 12) + 12) % 12 === 0 })), accent: this.accent, light: this.light, drift: this.position * pps, still });
        // A looped phrase, tinted.
        if (this.loop) {
            g.fillStyle = `${this.color}1a`;
            g.fillRect(Math.max(left, x(this.loop.startSeconds)), EDGE, x(this.loop.endSeconds) - Math.max(left, x(this.loop.startSeconds)), inner);
        }
        g.save();
        g.beginPath();
        g.rect(left, EDGE, width - left, inner);
        g.clip();
        // Game mode's ghost: under the gems, so it never covers a note to come.
        this.drawGhost(g, { x, mid, left, width });
        // The other parts: faint outlines at their real pitches.
        const tolH = rowH * (2 * this.tolerance) / 100;
        const barH = Math.max(tolH, Math.min(34, Math.max(14, rowH * 1.25)));
        for (const other of this.others) {
            for (const n of other.notes) {
                const pitch = n.pitch + this.transpose;
                if (pitch < low - 1 || pitch > high + 1) continue; // out of the lane: its name at the edge says where it is
                gemBar(g, x(seconds(n.beat)), mid(pitch) - barH * 0.35, seconds(n.length) * pps - 3, barH * 0.7, other.color, { ghost: true, light: this.light });
            }
        }
        // Your notes as glass gems (Hillel: chunky, glossy, alive): at least as tall as the
        // tolerance; filled where you sang in tune, the whole gem lit with a tick once hit; the
        // next breathing as the playhead nears, the note now rimmed and glowing; a hit flashes,
        // pops and sparkles; a missed note dims, never red. Labels bold inside when they fit.
        const ink = shades(this.color, { light: this.light }).ink;
        const fontSize = Math.max(10, Math.min(14, barH * 0.5));
        g.textBaseline = 'middle';
        const next = this.notes.find((n) => seconds(n.beat) > this.position);
        this.notes.forEach((n, index) => {
            const from = x(seconds(n.beat));
            const w = seconds(n.length) * pps - 3;
            if (from > width || from + w < left) return;
            const pitch = n.pitch + this.transpose;
            const top = mid(pitch) - barH / 2;
            const past = seconds(n.beat + n.length) <= this.position;
            const lit = this.lit.has(n.beat);
            const isNow = n === nowNote;
            const hitAge = this.hitAt.has(n.beat) ? (clock - this.hitAt.get(n.beat)) / 1000 : Infinity;
            const fresh = !still && hitAge < HIT_SECONDS;
            const ahead = next === n ? seconds(n.beat) - this.position : Infinity;
            const pulse = !still && ahead < PULSE_BEFORE ? (0.35 + 0.35 * Math.sin(clock / 180)) * (1 - ahead / PULSE_BEFORE) : 0;
            if (lit) {
                gemBar(g, from, top, w, barH, this.color, { glow: fresh ? 1 - hitAge / HIT_SECONDS : 0.25, pop: fresh ? 1 + 0.05 * Math.sin(Math.PI * Math.min(1, hitAge / 0.25)) : 1, rim: isNow ? this.cream : null, light: this.light });
            } else if (past) {
                gemBar(g, from, top, w, barH, this.color, { dim: true, light: this.light });
            } else {
                // Not sung yet: the whole gem, bright (it breathes when next); where you were in
                // tune, a light sheen over it, filling as you sing.
                gemBar(g, from, top, w, barH, this.color, { glow: isNow ? 0.8 : pulse, rim: isNow ? this.cream : null, light: this.light });
                for (const [a, b] of this.hits.get(n.beat) ?? []) {
                    const sx = Math.max(from, x(a));
                    const sw = Math.min(from + w, x(b)) - sx;
                    if (sw <= 0) continue;
                    g.save();
                    g.beginPath();
                    g.rect(sx, top, sw, barH);
                    g.clip();
                    g.fillStyle = this.light ? 'rgba(255,255,255,0.45)' : 'rgba(255,255,255,0.32)';
                    this.bar(g, from, top, w, barH);
                    g.restore();
                }
            }
            if (lit && w > 18) this.tick(g, from + w - 10, mid(pitch), Math.min(8, barH * 0.5), ink);
            if (fresh) sparkles(g, from + w / 2, mid(pitch), hitAge, this.color, { seed: index });
            // The word and the name: bold inside the gem when they fit, else just above it.
            const word = this.wordAt.get(Math.round(n.beat * 1000)) ?? '';
            const name = this.noteName(pitch);
            const label = [word, name].filter(Boolean).join(' ');
            const room = w - (lit ? 26 : 10);
            g.font = `800 ${fontSize}px system-ui, sans-serif`;
            const inside = barH >= 18 && (lit || isNow || !past);
            const fits = (text) => text && g.measureText(text).width < room;
            const text = fits(label) ? label : fits(word) ? word : fits(name) ? name : '';
            if (inside && text) {
                // On the gem: dark or white by its colour (AA); a missed one is grey, so cream at 70%.
                g.fillStyle = past && !lit ? this.cream : ink;
                g.globalAlpha = past && !lit ? 0.7 : 1;
                g.fillText(text, Math.round(from + 7), Math.round(mid(pitch)) + 0.5);
                g.globalAlpha = 1;
            } else if (label) {
                const roomAbove = (this.notes[index + 1] ? x(seconds(this.notes[index + 1].beat)) : width) - from - 6;
                g.font = `700 ${Math.max(10, Math.min(13, fontSize))}px system-ui, sans-serif`;
                g.fillStyle = past ? this.muted : this.cream;
                const above = g.measureText(label).width < roomAbove ? label : g.measureText(name).width < roomAbove ? name : '';
                if (above) g.fillText(above, Math.round(from + 2), Math.round(top - 8));
            }
        });
        // A breath mark (a comma, as on a score) where you breathe before a new line: the last half
        // beat of the note before it, which scoring forgives (js/scoring.js sungEnd).
        this.notes.forEach((n, index) => {
            const end = sungEnd(this.notes, index);
            if (end >= n.beat + n.length - 1e-6) return;
            const bx = x(seconds(end));
            if (bx < left || bx > width) return;
            this.breathMark(g, bx, mid(n.pitch + this.transpose) - barH / 2 - 10, Math.max(8, Math.min(11, barH * 0.4)));
        });
        this.drawVoice(g, { x, mid, nowX, nowPitch: nowNote, seconds });
        g.restore();
        vignette(g, { left, top: EDGE, width, bottom: height - EDGE, light: this.light });
        // The now line: a glowing beam.
        beam(g, nowX, EDGE, height - EDGE, this.state === 'recording' ? this.record : this.state === 'turn' ? this.color : this.cream, { light: this.light });
        // The chords along the top, and the other parts' names at the edges.
        g.textBaseline = 'alphabetic';
        g.font = '600 12px system-ui, sans-serif';
        g.fillStyle = this.muted;
        for (const chord of this.chords ?? []) {
            const cx = x(chord.seconds);
            if (cx >= left && cx < width - 20) g.fillText(chord.name, cx + 3, EDGE / 2 + 4);
        }
        for (const other of this.others) {
            g.fillText(`${other.above ? '▲' : '▼'} ${other.name}`, left + 6, other.above ? (this.chords?.length ? EDGE + 12 : EDGE / 2 + 4) : height - EDGE / 2 + 4);
        }
        // The keyboard down the side, the note now lit.
        if (this.keyboard) {
            g.save();
            g.beginPath();
            g.rect(0, EDGE, KEYS, inner);
            g.clip();
            g.textBaseline = 'middle';
            for (const p of rows) {
                const black = BLACK.has(((p % 12) + 12) % 12);
                const lit = nowNote && nowNote.pitch + this.transpose === p;
                const top = mid(p) - rowH / 2;
                if (lit) gemBar(g, 2, top + 1, KEYS - 6, rowH - 2, this.color, { light: this.light });
                else {
                    g.fillStyle = black ? this.ink : this.line;
                    g.fillRect(2, top + 1, black ? KEYS * 0.6 : KEYS - 6, rowH - 2);
                }
                if ((((p % 12) + 12) % 12 === 0 || lit) && rowH >= 11) {
                    g.fillStyle = lit ? ink : this.muted;
                    g.font = `700 ${Math.min(11, rowH * 0.6)}px system-ui, sans-serif`;
                    g.fillText(this.noteName(p) || '', 5, Math.round(mid(p)) + 0.5);
                }
            }
            g.restore();
        }
        // Something still moving (a hit's sparkles, the camera settling, the next note breathing) while paused: draw again.
        const moving = !still && ([...this.hitAt.values()].some((at) => clock - at < HIT_SECONDS * 1000) || Math.abs(this.cam.vc) + Math.abs(this.cam.vs) > 0.05);
        cancelAnimationFrame(this.again);
        if (moving) this.again = requestAnimationFrame(() => this.draw());
    }
    /** Your best take's line (game mode's ghost), faint and dashed, under the gems and your voice; a gap where it was silent. */
    drawGhost(g, { x, mid, left, width }) {
        if (!this.ghost?.length) return;
        g.save();
        g.globalAlpha = this.light ? 0.45 : 0.4;
        g.strokeStyle = this.cream;
        g.lineWidth = 2;
        g.setLineDash([5, 5]);
        g.beginPath();
        let open = false;
        for (const p of this.ghost) {
            const px = x(p.seconds);
            if (p.midi === null || px < left - 10 || px > width + 10) {
                open = false;
                continue;
            }
            if (open) g.lineTo(px, mid(p.midi));
            else g.moveTo(px, mid(p.midi));
            open = true;
        }
        g.stroke();
        g.restore();
    }

    /**
     * Your voice (Hillel: steady, easy to see, louder is bigger): a ribbon in your colour whose
     * path is your pitch and whose thickness is how loud you sing (to a cap that never hides the
     * notes), fading with age; the head at the now line, a solid dot in your colour with a cream
     * ring and a soft glow, sized by loudness, fading through a dropout rather than vanishing.
     * Out of tune: an arrow and a dotted lead to the bar. After a breath the head is a hollow
     * ring where you stopped, and a dashed dot marks where the next note starts.
     */
    drawVoice(g, { x, mid, nowX, nowPitch, seconds }) {
        const points = this.voice;
        if (!points.length) return;
        const half = (p) => 1.5 + 4.5 * (p.level ?? 0.5); // the ribbon's half-thickness, px
        const trail = canvasColor(this.trail) ?? this.color;
        const glow = canvasColor(this.trailGlow) ?? trail;
        g.fillStyle = trail;
        for (let i = 1; i < points.length; i++) {
            const [a, b] = [points[i - 1], points[i]];
            if (b.seconds - a.seconds > 0.12) continue; // a breath: the ribbon breaks
            const age = Math.max(0, this.position - b.seconds);
            g.globalAlpha = 0.6 * Math.min(a.alpha ?? 1, b.alpha ?? 1) * Math.max(0, 1 - age / 4);
            if (g.globalAlpha <= 0) continue;
            const [ax, bx, ay, by] = [x(a.seconds), x(b.seconds), mid(a.midi), mid(b.midi)];
            g.beginPath();
            g.moveTo(ax, ay - half(a));
            g.lineTo(bx, by - half(b));
            g.lineTo(bx, by + half(b));
            g.lineTo(ax, ay + half(a));
            g.closePath();
            g.fill();
        }
        g.globalAlpha = 1;
        const last = points.at(-1);
        // Points keep coming while you sing, hold or fade: the head stays until the fade is over.
        const live = this.position - last.seconds < 0.1;
        if (this.host?.dataset && this.host.dataset.head !== (live ? 'live' : 'off')) this.host.dataset.head = live ? 'live' : 'off'; // for the flows
        if (live) {
            const hx = Math.min(nowX, x(last.seconds));
            const hy = mid(last.midi);
            const target = nowPitch ? nowPitch.pitch + this.transpose : null;
            if (target !== null && this.hint) {
                // Out: a dotted lead to the bar's middle, and an arrow the way to go.
                g.strokeStyle = this.color;
                g.lineWidth = 1.5;
                g.setLineDash([3, 4]);
                g.beginPath();
                g.moveTo(hx, hy);
                g.lineTo(hx, mid(target));
                g.stroke();
                g.setLineDash([]);
                const up = this.hint === 'higher';
                const ay = hy + (up ? -16 : 16);
                g.fillStyle = this.color;
                g.beginPath();
                g.moveTo(hx - 6, ay + (up ? 4 : -4));
                g.lineTo(hx + 6, ay + (up ? 4 : -4));
                g.lineTo(hx, ay + (up ? -5 : 5));
                g.closePath();
                g.fill();
                if (this.hintText) this.chip(g, hx + 16, hy + (up ? 14 : -14), this.hintText);
            }
            const r = 7 * (0.8 + 0.7 * (last.level ?? 0.5)); // 0.8x to 1.5x, by loudness
            g.globalAlpha = last.alpha ?? 1;
            g.save();
            g.shadowColor = glow;
            g.shadowBlur = 16;
            g.fillStyle = trail;
            g.beginPath();
            g.arc(hx, hy, r, 0, Math.PI * 2);
            g.fill();
            g.restore();
            g.strokeStyle = this.cream;
            g.lineWidth = 2.5;
            g.beginPath();
            g.arc(hx, hy, r, 0, Math.PI * 2);
            g.stroke();
            // A halo in the page's colour: the dot stands off its own bar, in either theme.
            g.strokeStyle = this.ink;
            g.lineWidth = 2;
            g.beginPath();
            g.arc(hx, hy, r + 2.25, 0, Math.PI * 2);
            g.stroke();
            g.globalAlpha = 1;
        } else if (this.position - last.seconds < 2) {
            // A breath: a hollow ring where you stopped, and the next note's start marked.
            g.globalAlpha = Math.max(0.3, last.alpha);
            g.strokeStyle = this.cream;
            g.lineWidth = 2;
            g.beginPath();
            g.arc(x(last.seconds), mid(last.midi), 6, 0, Math.PI * 2);
            g.stroke();
            g.globalAlpha = 1;
            const next = this.notes.find((n) => seconds(n.beat) > this.position);
            if (next) {
                g.strokeStyle = this.color;
                g.setLineDash([2, 3]);
                g.beginPath();
                g.arc(x(seconds(next.beat)), mid(next.pitch + this.transpose), 5, 0, Math.PI * 2);
                g.stroke();
                g.setLineDash([]);
            }
        }
    }
    /** A tick on a lit bar: it keeps it, so a hit isn't colour alone. */
    /** The hint in words: a pill in your colour from `left`, centred on `cy`, kept on the canvas. */
    chip(g, left, cy, text) {
        g.font = '700 13px system-ui, sans-serif';
        const w = g.measureText(text).width + 22;
        const h = 26;
        const { clientWidth: width, clientHeight: height } = this.canvas;
        const x0 = Math.min(left, width - w - 4);
        const y0 = Math.max(EDGE, Math.min(height - EDGE - h, cy - h / 2));
        g.fillStyle = this.color;
        g.beginPath();
        g.roundRect(x0, y0, w, h, h / 2);
        g.fill();
        g.fillStyle = shades(this.color, { light: this.light }).ink;
        g.textBaseline = 'middle';
        g.fillText(text, x0 + 11, y0 + h / 2 + 0.5);
    }
    tick(g, cx, cy, size, ink = this.ink) {
        g.strokeStyle = ink;
        g.lineWidth = 2;
        g.lineCap = 'round';
        g.beginPath();
        g.moveTo(cx - size * 0.45, cy);
        g.lineTo(cx - size * 0.12, cy + size * 0.33);
        g.lineTo(cx + size * 0.5, cy - size * 0.35);
        g.stroke();
    }
    breathMark(g, cx, cy, size) {
        breathComma(g, cx, cy, size, this.cream);
    }
    bar(g, x, y, w, h, stroke = false) {
        const r = Math.min(6, h / 2, Math.max(0, w) / 2);
        g.beginPath();
        g.roundRect ? g.roundRect(x, y, Math.max(0, w), h, r) : g.rect(x, y, Math.max(0, w), h);
        if (stroke) g.stroke();
        else g.fill();
    }

}
