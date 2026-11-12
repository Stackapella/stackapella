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

function tell(text) {
    if (!say) return;
    say.textContent = text;
    say.hidden = !text;
}

/** The band's line and button for a tool's result: the range test's "the part that fits you". */
function showResult({ type } = {}) {
    const data = document.querySelector('[data-tool-result]');
    if (!data || !type) return;
    const { line, link, to, parts, types } = JSON.parse(data.textContent);
    const part = parts[type];
    const lineEl = document.querySelector('[data-try-line]');
    const linkEl = document.querySelector('[data-try-link]');
    if (!part || !lineEl || !linkEl) return;
    lineEl.classList.add('s-about-result'); // a sentence, not a headline: a smaller size
    lineEl.textContent = line.replace(/:(\w+)/g, (m, k) => ({ type: (types[type] ?? type).toLowerCase(), part }[k] ?? m));
    linkEl.querySelector('[data-try-words]').textContent = link;
    linkEl.href = to;
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
            onLesson: () => offerLesson(),
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
