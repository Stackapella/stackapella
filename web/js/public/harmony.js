// The shared harmony's page (/h/<id>, server/resources/views/public/harmony.blade.php): the round
// play button and the waveform over the page's own <audio> (which plays without this script): one
// row per part in its own colour (data-parts, each part's peaks), or the mix in the parts' colours
// when there's one part; and the Report form. Report posts reports.create to the app's own address
// when it's on another host (the form's data-api: no cookies, no CSRF, which that one route allows
// from the public site; docs/app-subdomain-plan.md), else through api.js on this one. Every word
// comes from the page (data attributes).

import { api, call } from '../api.js';
import { renderTurnstile } from '../turnstile.js';
import { paint, token } from '../colors.js';

{
    const player = document.querySelector('[data-player]');
    if (player) setUpPlayer(player);
    const report = document.querySelector('[data-report]');
    if (report) setUpReport(report);

    function setUpPlayer(root) {
        const audio = root.querySelector('[data-audio]');
        const button = root.querySelector('[data-play]');
        const canvas = root.querySelector('[data-wave]');
        const time = root.querySelector('[data-time]');
        const peaks = JSON.parse(root.dataset.peaks || '[]');
        const colors = JSON.parse(root.dataset.colors || '[]');
        const parts = JSON.parse(root.dataset.parts || '[]').filter((part) => part.peaks?.length);
        const duration = Number(root.dataset.duration) || 0;
        // The script is here: the round button and the waveform take over from the native controls.
        audio.controls = false;
        audio.classList.add('hidden');
        for (const el of [button, canvas, time, root.querySelector('[data-part-labels]')]) el?.classList.remove('hidden');
        button.classList.add('grid');

        const muted = token('line', '#352c26');
        const clock = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

        function draw() {
            const ratio = window.devicePixelRatio || 1;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            if (!width || !height) return;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            const g = canvas.getContext('2d');
            g.setTransform(ratio, 0, 0, ratio, 0, 0);
            const total = audio.duration || duration || 1;
            const done = (audio.currentTime || 0) / total;
            const step = 4;
            if (parts.length > 1) {
                // A row per part, in its colour: what's been played full, the rest faint, and a playhead.
                const row = height / parts.length;
                parts.forEach((part, r) => {
                    const middle = row * r + row / 2;
                    g.fillStyle = paint(part.color);
                    for (let x = 0; x < width; x += step) {
                        const share = x / width;
                        const peak = part.peaks[Math.floor(share * part.peaks.length)] ?? 0;
                        const bar = Math.max(1, Math.sqrt(peak) * row * 0.4);
                        g.globalAlpha = done > 0 && share <= done ? 1 : 0.35;
                        g.fillRect(x, middle - bar, step - 1.5, bar * 2);
                    }
                });
                g.globalAlpha = 1;
                if (done > 0) {
                    g.fillStyle = token('cream', '#f4eee6');
                    g.fillRect(Math.min(width - 2, done * width), 0, 2, height);
                }
            }
            for (let x = 0; parts.length <= 1 && x < width; x += step) {
                const share = x / width;
                const peak = peaks[Math.floor(share * peaks.length)] ?? 0;
                const bar = Math.max(1.5, Math.sqrt(peak) * height * 0.48);
                // Played bars in the parts' colours, across the width; the rest dim.
                g.fillStyle = done > 0 && share <= done ? paint(colors[Math.floor(share * colors.length)] ?? token('cream', '#f4eee6')) : muted;
                g.fillRect(x, height / 2 - bar, step - 1.5, bar * 2);
            }
            time.textContent = `${clock(audio.currentTime || 0)} / ${clock(total)}`;
            canvas.setAttribute('aria-valuemax', String(Math.round(total)));
            canvas.setAttribute('aria-valuenow', String(Math.round(audio.currentTime || 0)));
            canvas.setAttribute('aria-valuetext', time.textContent);
        }

        function playing(now) {
            button.querySelector('[data-icon-play]').classList.toggle('hidden', now);
            button.querySelector('[data-icon-pause]').classList.toggle('hidden', !now);
            button.setAttribute('aria-label', now ? button.dataset.labelPause : button.dataset.labelPlay);
        }

        let frame = 0;
        const tick = () => {
            draw();
            if (!audio.paused) frame = requestAnimationFrame(tick);
        };
        button.addEventListener('click', () => (audio.paused ? audio.play() : audio.pause()));
        audio.addEventListener('play', () => {
            playing(true);
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(tick);
        });
        audio.addEventListener('pause', () => {
            playing(false);
            draw();
        });
        audio.addEventListener('ended', () => playing(false));
        audio.addEventListener('loadedmetadata', draw);
        // A tap on the waveform moves there.
        canvas.addEventListener('click', (event) => {
            const box = canvas.getBoundingClientRect();
            const share = Math.max(0, Math.min(1, (event.clientX - box.left) / box.width));
            const total = audio.duration || duration;
            if (total) audio.currentTime = share * total;
            draw();
        });
        // The waveform is the position slider: arrows move 5 seconds, Home and End to the ends.
        canvas.addEventListener('keydown', (event) => {
            const total = audio.duration || duration;
            if (!total) return;
            const steps = { ArrowRight: 5, ArrowUp: 5, ArrowLeft: -5, ArrowDown: -5 };
            if (event.key in steps) audio.currentTime = Math.max(0, Math.min(total, audio.currentTime + steps[event.key]));
            else if (event.key === 'Home') audio.currentTime = 0;
            else if (event.key === 'End') audio.currentTime = total;
            else return;
            event.preventDefault();
            draw();
        });
        new ResizeObserver(draw).observe(canvas);
    }

    /** The app's API: on another host (no cookies, no CSRF), or on this one. */
    function reportApi(origin) {
        if (!origin) return { config: () => api.auth.config(), report: (body) => api.reports.create(body) };
        return { config: () => call('auth.config', { origin }), report: (body) => call('reports.create', { body, origin }) };
    }

    function setUpReport(dialog) {
        const open = document.querySelector('[data-report-open]');
        const form = dialog.querySelector('[data-report-form]');
        const remote = reportApi(form.dataset.api || null);
        const status = dialog.querySelector('[data-report-status]');
        const send = dialog.querySelector('[data-report-send]');
        open.classList.remove('hidden');
        const box = dialog.querySelector('[data-turnstile]');
        let token = null;
        let resetCheck = null; // set in shield mode: Send waits for Cloudflare's token
        const ready = () => (send.disabled = !!resetCheck && !token);
        open.addEventListener('click', async () => {
            status.classList.add('hidden');
            send.disabled = false;
            dialog.showModal();
            // Shield mode (a flood): Cloudflare's check first, only when the server asks for it.
            const config = await remote.config().then((r) => r?.data, () => null);
            if (config?.turnstileSiteKey && !resetCheck) {
                box.classList.remove('hidden');
                resetCheck = await renderTurnstile(box, config.turnstileSiteKey, (t) => {
                    token = t;
                    ready();
                }).catch(() => null);
                ready();
            }
        });
        dialog.querySelector('[data-report-close]').addEventListener('click', () => dialog.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            send.disabled = true;
            const data = new FormData(form);
            let ok = false;
            try {
                await remote.report({ harmonyId: form.dataset.harmony, reason: data.get('reason'), note: String(data.get('note') || '').slice(0, 500), website: String(data.get('website') || ''), ...(token ? { turnstileToken: token } : {}) });
                ok = true;
            } catch {
                ok = false;
            }
            resetCheck?.(); // a token works once: a new one for another try
            status.textContent = ok ? status.dataset.sent : status.dataset.failed;
            status.classList.remove('hidden');
            if (!ok) ready();
            else setTimeout(() => dialog.close(), 1800);
        });
    }
}
