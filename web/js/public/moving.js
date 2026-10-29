// "Stackapella has moved" (server/resources/views/public/moving.blade.php; docs/app-subdomain-plan.md,
// "Old installs"): on the old address, for an app page or an old install. It looks for what this
// device kept here; with nothing, it goes to the new address at once. With songs, Bring my songs
// opens the new address's /moved page and hands everything over (js/move.js), then this address's
// service worker and caches go. A home-screen app on iPhone can't share a window with Safari, so
// there it saves a moving file to open at the new address instead. Words from the app's locale
// files (this page has no Vue), the page's markup only places them.

import { batches, countSongs, localItems, MESSAGE, openOld, packFile, readRecords } from '../move.js';

const root = document.querySelector('[data-moving]');
const appUrl = root?.dataset.appUrl;
const appOrigin = appUrl ? new URL(appUrl).origin : null;
const HANDSHAKE_MS = 20000;
const ACK_MS = 60000;
const iphoneHomeScreen = navigator.standalone === true;

let words = {};
/** A message with `{name}` and `{count, plural, one {...} other {...}}` filled in (as js/i18n.js does, without Vue). */
function t(key, values = {}) {
    const message = key.split('.').reduce((node, part) => node?.[part], words) ?? key;
    const plural = message.replace(/\{(\w+), plural, ((?:[^{}]|\{[^{}]*\})*)\}/g, (_, name, cases) => {
        const count = Number(values[name] ?? 0);
        const options = Object.fromEntries([...cases.matchAll(/(=\d+|\w+) \{([^{}]*)\}/g)].map((m) => [m[1], m[2]]));
        const chosen = options[`=${count}`] ?? options[new Intl.PluralRules(document.documentElement.lang).select(count)] ?? options.other ?? '';
        return chosen.replace(/#/g, String(count));
    });
    return plural.replace(/\{(\w+)\}/g, (_, name) => values[name] ?? '');
}

async function loadWords() {
    const wanted = [...(navigator.languages ?? [navigator.language])].map((l) => l.split('-')[0]).filter((l) => /^[a-z]{2}$/.test(l));
    for (const lang of [...wanted, 'en']) {
        const response = await fetch(`/locales/${lang}.json`).catch(() => null);
        if (response?.ok) {
            words = await response.json();
            document.documentElement.lang = lang;
            return;
        }
    }
}

/** This address's service worker and caches go: the app is at the new address now. */
async function forgetHere() {
    try {
        for (const registration of (await navigator.serviceWorker?.getRegistrations()) ?? []) await registration.unregister();
        for (const key of (await window.caches?.keys()) ?? []) await caches.delete(key);
        localStorage.setItem('stackapella.movedAt', String(Date.now()));
    } catch (error) {
        console.warn('The old address was not tidied', error);
    }
}

function go() {
    location.replace(appUrl);
}

const el = (tag, attrs = {}, ...children) => {
    const node = Object.assign(document.createElement(tag), attrs);
    node.append(...children.filter((c) => c !== null && c !== undefined));
    return node;
};

/** The page's one panel, redrawn for each moment. */
function show({ title, body = [], actions = [], status = false }) {
    const parts = [
        el('h1', { className: 'text-3xl font-extrabold tracking-tight text-cream' }, title),
        ...body.map((text) => el('p', { className: 'text-muted' }, text)),
        status ? el('p', { className: 'text-sm text-muted', role: 'status' }, status) : null,
        actions.length ? el('div', { className: 'flex flex-wrap gap-3 pt-2' }, ...actions) : null,
    ];
    root.replaceChildren(...parts.filter(Boolean));
    root.querySelector('button, a')?.focus();
}

const button = (label, run, primary = true) => {
    const b = el('button', { type: 'button', className: primary ? 'btn-primary' : 'btn-secondary' }, label);
    b.addEventListener('click', run);
    return b;
};
const link = (label, href, primary = false) => el('a', { href, className: primary ? 'btn-primary' : 'btn-secondary' }, label);

/** Opens the new address and hands the records over, batch by batch, each acknowledged. */
function handOver(records, local) {
    return new Promise((resolve, reject) => {
        const target = window.open(new URL('/moved', appOrigin).href, 'stackapella-move');
        if (!target) return reject(new Error('blocked'));
        const parts = batches(records);
        let sent = 0;
        let timer = setTimeout(() => finish(new Error('no answer')), HANDSHAKE_MS);
        const finish = (error, result) => {
            clearTimeout(timer);
            window.removeEventListener('message', onMessage);
            if (error) reject(error);
            else resolve(result);
        };
        const next = () => {
            clearTimeout(timer);
            timer = setTimeout(() => finish(new Error('no answer')), ACK_MS);
            if (sent < parts.length) {
                target.postMessage({ type: MESSAGE.batch, records: parts[sent], done: false }, appOrigin);
                sent++;
                show({ title: t('moving.bringing'), status: t('moving.progress', { done: sent, total: parts.length }) });
            } else {
                target.postMessage({ type: MESSAGE.batch, records: [], local, done: true }, appOrigin);
            }
        };
        const onMessage = (event) => {
            if (event.origin !== appOrigin || event.source !== target) return;
            if (event.data?.type === MESSAGE.ready || event.data?.type === MESSAGE.ack) next();
            if (event.data?.type === MESSAGE.done) finish(null, event.data);
        };
        window.addEventListener('message', onMessage);
    });
}

function saveFile(records, local) {
    const a = el('a', { href: URL.createObjectURL(packFile(records, local)), download: 'stackapella-songs.stackapella' });
    document.body.append(a);
    a.click();
    a.remove();
}

async function start() {
    if (!root || !appUrl) return;
    await loadWords();
    show({ title: t('moving.title'), status: t('moving.looking') });
    const db = await openOld();
    const records = db ? await readRecords(db) : [];
    db?.close();
    const songs = countSongs(records);
    const local = localItems();
    const host = new URL(appUrl).host;
    if (!songs) {
        show({ title: t('moving.title'), body: [t('moving.nowAt', { host })], status: t('moving.taking') });
        await forgetHere();
        return go();
    }
    const fileWay = () => {
        saveFile(records, local);
        show({ title: t('moving.fileSaved'), body: [t('moving.fileThen', { host })], actions: [link(t('moving.open', { host }), appUrl, true)] });
    };
    const offer = (problem = null) =>
        show({
            title: t('moving.title'),
            body: [t('moving.nowAt', { host }), t('moving.found', { count: songs }), problem].filter(Boolean),
            actions: iphoneHomeScreen
                ? [button(t('moving.saveFile'), fileWay), link(t('moving.without'), appUrl)]
                : [button(t('moving.bring'), bring), button(t('moving.saveFile'), fileWay, false), link(t('moving.without'), appUrl)],
        });
    const bring = async () => {
        show({ title: t('moving.bringing'), status: t('moving.opening') });
        try {
            await handOver(records, local);
            await forgetHere();
            show({ title: t('moving.moved'), body: [t('moving.movedBody', { count: songs, host })], actions: [link(t('moving.open', { host }), appUrl, true)] });
        } catch (error) {
            console.warn('The songs were not handed over', error);
            offer(error.message === 'blocked' ? t('moving.blocked') : t('moving.noAnswer'));
        }
    };
    offer();
}

start();
