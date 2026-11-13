// A free tool's page (site/tool.blade.php, docs/tools-plan.md): loads the tool's own module
// (js/public/tools/<tool>.js) and mounts it on the page's stage, with the words the page translated. The tool
// works at once; nothing sounds and no mic opens until a tap inside it. A refused mic is said plainly under it.
// It also registers the tools' own service worker (/tools-sw.js, scope /tools: the hub and every tool, never the
// app's), so a tool works offline once visited, and keeps the files a tool loads only on a tap (its module's WARM
// list, such as the pitch tracker) for then. No import map (public_imports_check.py).

/** The tools' service worker, and the files a tool will want offline before it first asks for them. */
async function offline(urls) {
    if (!('serviceWorker' in navigator)) return;
    try {
        const registration = await navigator.serviceWorker.register('/tools-sw.js', { scope: '/tools' });
        if (urls.length === 0) return;
        await navigator.serviceWorker.ready;
        (navigator.serviceWorker.controller ?? registration.active)?.postMessage({ type: 'tools-warm', urls });
    } catch (error) {
        console.warn('The tools could not be kept for offline use', error);
    }
}

import { mountThemeMenus } from '../theme-menu.js';
import { countAnon } from '../count.js';

// The brand row's Theme menu (Game mode, light or dark, the palettes), on every tools page and the hub.
mountThemeMenus();

const stage = document.querySelector('[data-tool]');
const say = document.querySelector('[data-tool-say]');

/**
 * Each tool's funnel (docs/growth-data.md, the backend's tool_open, tool_result and tool_try): one anonymous daily count of
 * each kind per page view, by the page's own slug (/tools/<slug>, the Spanish /es/tools/<slug> too), through count.js's
 * first-party path (no ID, no cookie). Opened is the first tap on a control inside the tool, a result its first result or
 * free-lesson moment, and the way in a tap on the page's try link or the lesson line.
 */
const SLUG = location.pathname.match(/\/tools\/([a-z0-9-]+)\/?$/)?.[1] ?? null;
const counted = new Set();
function countOnce(kind) {
    if (!SLUG || counted.has(kind)) return;
    counted.add(kind);
    countAnon(`${kind}:${SLUG}`);
}
stage?.addEventListener('click', (event) => {
    if (event.target.closest?.('button, [role=button], [role=switch], input, select, summary')) countOnce('tool_open');
});
document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-try-link], [data-tool-lesson-link]')) countOnce('tool_try');
});

function tell(text) {
    if (!say) return;
    say.textContent = text;
    say.hidden = !text;
}

/** The band's line and button for a tool's result: the range test's "the part that fits you". */
// Letter names for a range carried into the app (?range=A2-D4; js/tool-range.js reads them): never the page's own naming.
const LETTERS = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
const letterName = (midi) => `${LETTERS[((midi % 12) + 12) % 12]}${Math.floor(midi / 12) - 1}`;

/**
 * A tool's result in its About block (the range test's: docs/retention-plan.md, idea 7): the line says the range and its
 * voice type, and the link opens the app already knowing the range (?range=A2-D4, letter names and nothing else), so
 * Home's picks fit it and no range check asks.
 */
function showResult({ type, low, high } = {}) {
    const data = document.querySelector('[data-tool-result]');
    if (!data || !type) return;
    const { line, link, to, parts, types } = JSON.parse(data.textContent);
    const part = parts?.[type];
    const lineEl = document.querySelector('[data-try-line]');
    const linkEl = document.querySelector('[data-try-link]');
    if (!lineEl || !linkEl || (line.includes(':part') && !part)) return;
    const ranged = Number.isInteger(low) && Number.isInteger(high) && high > low;
    if (line.includes(':low') && !ranged) return;
    const names = ranged ? { low: letterName(low).replace('#', '♯'), high: letterName(high).replace('#', '♯') } : {};
    lineEl.classList.add('s-about-result'); // a sentence, not a headline: a smaller size
    lineEl.textContent = line.replace(/:(\w+)/g, (m, k) => ({ type: (types[type] ?? type).toLowerCase(), part, ...names }[k] ?? m));
    linkEl.querySelector('[data-try-words]').textContent = link;
    const url = new URL(to, location.href);
    if (ranged) url.searchParams.set('range', `${letterName(low)}-${letterName(high)}`);
    linkEl.href = url.href;
}

/**
 * The tool's free lesson (site/tool.blade.php, data-tool-lesson: its line, link and address): one quiet line in the tool's
 * bar (tool-layout.js, tool-next), so it's in view, once the tool says its moment has come (a sung note held in tune, a
 * round finished, a range found), never before.
 */
function offerLesson() {
    const words = document.querySelector('[data-tool-lesson]');
    const slot = stage?.querySelector('[data-ui=tool-next]');
    if (!words || !slot || slot.childElementCount) return;
    try {
        const { line, link, to } = JSON.parse(words.textContent);
        const a = document.createElement('a');
        a.href = to;
        a.className = 'font-bold text-cream underline underline-offset-2';
        a.dataset.toolLessonLink = '';
        a.textContent = link;
        slot.replaceChildren(`${line} `, a);
    } catch (error) {
        console.error('The lesson line could not be read', error);
    }
}

/** The tool's data from the page (`<script data-tool-data>`), as mount options. */
function data(stage) {
    const el = stage.querySelector('[data-tool-data]');
    if (!el) return {};
    try {
        return JSON.parse(el.textContent) ?? {};
    } catch (error) {
        console.error('The tool data could not be read', error);
        return {};
    }
}

async function start() {
    if (!stage) {
        offline([]);
        return;
    }
    const { src, mount } = stage.dataset;
    const words = JSON.parse(stage.querySelector('[data-tool-words]')?.textContent || '{}');
    try {
        const module = await import(src);
        module[mount](stage, {
            words,
            // The brand row's slot for the tool's key control (the tuner's A4, the metronome's metre), if the page has one.
            keySlot: document.querySelector('[data-tool-key]'),
            // The metronome opened from tap tempo (/tools/metronome?bpm=N) starts at that tempo.
            bpm: Number(new URLSearchParams(location.search).get('bpm')) || null,
            // A tool's result (the range test's voice type) turns the band into its next step, when the page has one.
            onResult: (result) => {
                countOnce('tool_result');
                showResult(result);
                offerLesson();
            },
            // A result shared from the tool (the range test's card): one anonymous count, as the app's result_shared (count.js);
            // a tool that names itself is counted as a share too, sh:tool-<slug>.<how> (docs/improvement-log.md, row 5):
            // the device's share sheet, or the card saved as an image with its line copied.
            onShared: (tool, done) => {
                countAnon('result_shared');
                if (tool) countAnon(`sh:tool-${tool}.${done === 'shared' ? 'sheet' : 'image'}`);
            },
            // The moment a tool's free lesson is wanted (its result), for the line under it.
            onLesson: () => {
                countOnce('tool_result');
                offerLesson();
            },
            // A refused mic: 'denied', 'no-mic' or 'unsupported', in the page's own words.
            onError: (reason) => tell(stage.dataset[{ denied: 'denied', 'no-mic': 'noMic', unsupported: 'unsupported' }[reason]] ?? ''),
            // What the page hands the tool besides its words (the key finder's songs), when it has any.
            ...data(stage),
        });
        stage.dataset.state = 'ready';
        offline(Array.isArray(module.WARM) ? module.WARM : []);
    } catch (error) {
        console.error('The tool could not start', error);
        tell(stage.dataset.unsupported ?? '');
    }
}

start();
