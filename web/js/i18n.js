// Every word people read comes from a translation file (docs/architecture.md, "Languages"): the
// strings are in web/locales/<lang>.json, grouped by screen, and en.json is the source. Adding a
// language is dropping in its file; nothing else changes.
//
//   t('studio.record')                                    a plain string
//   t('studio.recorded', { name: 'Alto' })                "{name} recorded."
//   t('studio.parts', { count: 3 })                       "{count, plural, one {# part} other {# parts}}"
//
// Messages use a small subset of ICU: {name}, and {name, plural, =0 {...} one {...} other {...}}
// with # for the number, chosen by Intl.PluralRules. Not supported (keep messages simple): select,
// apostrophe escaping (write ' as it is), and # meaning an inner plural's number inside a nested
// plural. Dates, numbers and durations go through Intl (formatNumber, formatDate, timeAgo,
// formatList), never built by hand.
//
// `?lang=pseudo` (on localhost) shows every string accented and 40% longer, so a hard-coded
// sentence or a cramped layout stands out in the flow scripts' screenshots.

import { reactive } from 'vue';
import { local } from './storage.js';

const RTL = new Set(['ar', 'he', 'fa', 'ur']);

// `live`: the languages switched on (auth.config's `languages`, kept on the device for the next start): the browser's
// language and Settings' choice are followed only into these. A drafted language stays hidden until Hillel switches it
// on (Admin > Settings > Switches, lang_es); `?lang=es` still opens it, to check a draft. `source`: for each key, the
// English a translation was made from (locales/<lang>.source.json), so a key whose English has changed since shows
// the new English until it's translated again, never a stale sentence.
const LIVE_KEY = 'stackapella.languages';
// The languages we translate, each with its .source.json (tool/stale_words.py --mark writes it after a drafting pass).
const SOURCED = new Set(['es', 'de']);
export const locale = reactive({ lang: 'en', dir: 'ltr', messages: {}, english: {}, source: null, pseudo: false, live: liveFromDevice() });

// A first visit (no service worker yet) starts with lesson 1's own words, a quarter of en.json
// (locales/lesson1/en.json, written by tool/preload_list.py from the modules main.js imports), and
// the rest follows (allWords): when the page is idle, before a view loaded later, or for a key the
// short list doesn't have. Read by a flow: the keys a short list was missing (it should be none).
const FIRST_WORDS = '/locales/lesson1/en.json';
let partial = false;
let allLoading = null;
export const words = { missed: [] };

/** A key for t() that's chosen at run time: tk() marks it, so the key check can find it. */
export const tk = (key) => key;

/** The message for `key` in the person's language (English when it's missing), filled in. */
export function t(key, values = {}) {
    const english = lookup(locale.english, key);
    const message = translated(key, english);
    if (typeof message !== 'string' && typeof english !== 'string' && partial) {
        // Only lesson 1's words so far: the rest are on their way, and the screen re-renders with them.
        if (!words.missed.includes(key)) words.missed.push(key);
        allWords();
        return '';
    }
    if (typeof message !== 'string' && typeof english !== 'string') {
        // Never a raw key on screen: the words may be older than the code (an old copy kept by a
        // browser after a deploy). They're fetched again, past every cache, and the screen
        // re-renders with them; meanwhile, nothing shows.
        if (!triedFor.has(key)) {
            triedFor.add(key); // once per key, so a key missing everywhere can't loop
            console.warn(`Missing translation: ${key}`);
            refreshWords();
        }
        return '';
    }
    try {
        return format(locale.pseudo ? pseudo(message ?? english) : message ?? english, values);
    } catch (error) {
        // A broken translation shows English rather than breaking the screen.
        console.error(`Translation ${locale.lang}:${key} is broken`, error);
        return format(english ?? key, values);
    }
}

/** Content data that holds its text by language (`{ "en": "Find your voice" }`). */
export function localized(text) {
    if (typeof text === 'string' || text === null || text === undefined) {
        return text ?? '';
    }
    return text[locale.lang] ?? text.en ?? Object.values(text)[0] ?? '';
}

/** A key's words in the person's language, or undefined when it has none or they were made from older English. */
function translated(key, english) {
    if (locale.messages === locale.english) return english;
    const message = lookup(locale.messages, key);
    if (typeof message !== 'string' || typeof english !== 'string' || !locale.source) return message;
    return lookup(locale.source, key) === english ? message : undefined;
}

/**
 * Whether content kept by language (`{ "en": ..., "es": ... }`) has the person's language: false shows its English
 * with a "not yet in your language" note. Always true in English.
 */
export function inLanguage(text) {
    if (locale.lang === 'en' || typeof text !== 'object' || text === null) return true;
    return typeof text[locale.lang] === 'string' && text[locale.lang] !== '';
}

// Staff screens stay English in every language (the admin, the style guides): never counted as untranslated.
const ENGLISH_ONLY = new Set(['admin', 'styleguide', 'styleguideGame']);

/** How much of the app is in the person's language, 0 to 1 (1 in English): Settings says when some is still English. */
export function coverage() {
    if (locale.messages === locale.english || locale.pseudo) return 1;
    let all = 0;
    let done = 0;
    const walk = (node, path) => {
        for (const [part, value] of Object.entries(node)) {
            const key = path ? `${path}.${part}` : part;
            if (typeof value === 'object' && value !== null) walk(value, key);
            else if (typeof value === 'string') {
                all++;
                if (translated(key, value) !== undefined) done++;
            }
        }
    };
    for (const [part, node] of Object.entries(locale.english)) if (!ENGLISH_ONLY.has(part) && typeof node === 'object') walk(node, part);
    return all ? done / all : 1;
}

function lookup(messages, key) {
    return key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), messages);
}

/**
 * Loads the person's language: the one they chose, or the browser's first that we have, falling
 * back to English. Sets <html lang> and dir.
 */
export async function loadLanguage(wanted = preferredLanguages(), { lessonFirst = false } = {}) {
    // lessonFirst: a first visit's start (main.js), with lesson 1's own words; the rest, later.
    partial = lessonFirst;
    locale.english = partial ? await getJson(FIRST_WORDS).catch(() => ((partial = false), fetchMessages('en'))) : await fetchMessages('en');
    let chosen = 'en';
    let messages = locale.english;
    for (const lang of wanted) {
        if (lang === 'pseudo' || lang === 'en' || lang.startsWith('en-')) {
            break; // English (any region) needs no other file
        }
        // Only base languages ship (es.json, never es-ES.json), and preferredLanguages() lists each region's base after
        // it: asking for the region first was a 404 awaited before every start in a Spanish browser.
        if (lang.includes('-')) continue;
        const found = await fetchMessages(lang).catch(() => null);
        if (found) {
            chosen = lang;
            messages = found;
            break;
        }
    }
    // What each key was translated from (none for English, or when the file isn't there: then nothing counts as stale).
    locale.source = SOURCED.has(chosen.split('-')[0]) ? await getJson(`/locales/${encodeURIComponent(chosen)}.source.json`).catch(() => null) : null;
    locale.pseudo = wanted[0] === 'pseudo';
    locale.lang = chosen;
    locale.dir = RTL.has(chosen.split('-')[0]) ? 'rtl' : 'ltr';
    locale.messages = messages;
    document.documentElement.lang = chosen;
    document.documentElement.dir = locale.dir;
}

/** All of en.json, after a first visit started with lesson 1's words (resolves at once otherwise, and on failure). */
export function allWords() {
    if (!partial) return Promise.resolve();
    allLoading ??= fetchMessages('en')
        .then((english) => {
            if (locale.messages === locale.english) locale.messages = english;
            locale.english = english;
            partial = false;
        })
        .catch((error) => {
            allLoading = null; // tried again next time something needs it
            console.error('The rest of the words did not load', error);
        });
    return allLoading;
}

/** Settings: remember the person's choice on this device (Settings also sends it to the account, for emails). */
export async function chooseLanguage(lang) {
    local.set('stackapella.lang', lang);
    await loadLanguage([lang]);
}

/** The person's language as they'd choose it: their choice on this device, else the browser's first that's live. */
export function preferredLanguages() {
    const asked = new URLSearchParams(location.search).get('lang');
    if (asked === 'pseudo') {
        // A developer's tool: never on the live site.
        return ['localhost', '127.0.0.1'].includes(location.hostname) ? ['pseudo'] : [];
    }
    // A language asked for in the address opens even before it's switched on (checking a draft); the rest only once live.
    const list = [local.get('stackapella.lang'), ...(navigator.languages ?? [navigator.language])].filter(Boolean);
    const live = [...new Set(list.flatMap((lang) => [lang, lang.split('-')[0]]))].filter((lang) => isLive(lang));
    return [...new Set([...(asked ? [asked, asked.split('-')[0]] : []), ...live])];
}

/** Whether a language (or its region's, "es-MX") is switched on. English always is. */
export function isLive(lang) {
    const base = String(lang).split('-')[0];
    return base === 'en' || locale.live.includes(base);
}

function liveFromDevice() {
    try {
        const kept = JSON.parse(local.get(LIVE_KEY) ?? 'null');
        return Array.isArray(kept) ? kept.filter((lang) => typeof lang === 'string') : ['en'];
    } catch {
        return ['en'];
    }
}

/**
 * auth.config's `languages` (missing: English only), kept for the next start. When it changes what the person would
 * read (a language switched on or off), the words change now.
 */
export async function setLiveLanguages(languages) {
    const live = Array.isArray(languages) && languages.length ? [...new Set(['en', ...languages.filter((lang) => typeof lang === 'string')])] : ['en'];
    if (live.join() === locale.live.join()) return;
    locale.live = live;
    local.set(LIVE_KEY, JSON.stringify(live));
    const wanted = preferredLanguages();
    const now = wanted.find((lang) => lang === 'pseudo' || lang === 'en' || lang.startsWith('en-') || isLive(lang)) ?? 'en';
    if (now.split('-')[0] !== locale.lang.split('-')[0] && !locale.pseudo) await loadLanguage(wanted);
}

const triedFor = new Set();
let refreshing = null;

/** Fetches the words again from the server, past every cache (one fetch at a time). */
export function refreshWords() {
    if (refreshing) return refreshing;
    refreshing = (async () => {
        try {
            const english = await fetchMessages('en', { fresh: true });
            const messages = locale.lang === 'en' ? english : await fetchMessages(locale.lang, { fresh: true }).catch(() => locale.messages);
            if (SOURCED.has(locale.lang.split('-')[0])) locale.source = await getJson(`/locales/${encodeURIComponent(locale.lang)}.source.json`, { cache: 'reload' }).catch(() => locale.source);
            locale.english = english;
            locale.messages = messages;
            partial = false;
        } catch (error) {
            console.error('The words could not be fetched again', error);
        } finally {
            refreshing = null;
        }
    })();
    return refreshing;
}

async function fetchMessages(lang, { fresh = false } = {}) {
    return getJson(`/locales/${encodeURIComponent(lang)}.json`, fresh ? { cache: 'reload' } : {});
}

async function getJson(path, options = {}) {
    const response = await fetch(path, options);
    if (!response.ok) {
        throw new Error(`No messages at ${path}`);
    }
    return response.json();
}

// ---- Messages -----------------------------------------------------------------------------------

/** Fills in `{name}` and `{name, plural, ...}`. */
export function format(message, values) {
    let out = '';
    let i = 0;
    while (i < message.length) {
        const open = message.indexOf('{', i);
        if (open < 0) {
            out += message.slice(i);
            break;
        }
        out += message.slice(i, open);
        const close = matching(message, open);
        out += argument(message.slice(open + 1, close), values);
        i = close + 1;
    }
    return out;
}

function matching(text, open) {
    let depth = 0;
    for (let i = open; i < text.length; i++) {
        if (text[i] === '{') depth++;
        else if (text[i] === '}' && --depth === 0) return i;
    }
    throw new Error(`Unclosed { in "${text}"`);
}

function argument(body, values) {
    const [name, kind] = body.split(',', 2).map((s) => s.trim());
    const value = kind === 'plural' ? values[name] ?? 0 : values[name];
    if (kind !== 'plural') {
        return value === undefined || value === null ? '' : typeof value === 'number' ? formatNumber(value) : String(value);
    }
    const cases = {};
    let rest = body.slice(body.indexOf(',', body.indexOf(',') + 1) + 1);
    for (;;) {
        const open = rest.indexOf('{');
        if (open < 0) break;
        const close = matching(rest, open);
        cases[rest.slice(0, open).trim()] = rest.slice(open + 1, close);
        rest = rest.slice(close + 1);
    }
    const chosen = cases[`=${value}`] ?? cases[cached(Intl.PluralRules).select(value)] ?? cases.other ?? '';
    return format(outsideBraces(chosen, (text) => text.replaceAll('#', formatNumber(value))), values);
}

/** Applies `change` to the text of `message` that's outside any {argument}. */
function outsideBraces(message, change) {
    let out = '';
    let depth = 0;
    let run = '';
    for (const char of message) {
        if (depth === 0 && char !== '{') {
            run += char;
            continue;
        }
        if (depth === 0) {
            out += change(run);
            run = '';
        }
        depth += char === '{' ? 1 : char === '}' ? -1 : 0;
        out += char;
    }
    return out + change(run);
}

const formatters = new Map();

/** One Intl formatter per language and options: making them is slow, and the studio renders often. */
function cached(Type, options = {}) {
    const key = `${Type.name}:${locale.lang}:${JSON.stringify(options)}`;
    if (!formatters.has(key)) {
        formatters.set(key, new Type(locale.lang, options));
    }
    return formatters.get(key);
}

/** Accented and 40% longer, inside [ ], leaving {arguments} and # alone. */
export function pseudo(message) {
    const accents = { a: 'à', e: 'é', i: 'ï', o: 'ö', u: 'ü', c: 'ç', n: 'ñ', y: 'ý', A: 'Å', E: 'É', I: 'Î', O: 'Ö', U: 'Ü', C: 'Ç', N: 'Ñ', S: 'Š', s: 'š', z: 'ž' };
    let depth = 0; // even: text; odd: an argument's name and kind
    let out = '';
    let letters = 0;
    for (const char of message) {
        if (char === '{') depth++;
        else if (char === '}') depth--;
        else if (depth % 2 === 0 && /\p{L}/u.test(char)) {
            letters++;
            out += accents[char] ?? char;
            continue;
        }
        out += char;
    }
    // The 40% as words of up to five dots (a real language's longer text has spaces to wrap at; one long run never would).
    const pad = '·'.repeat(Math.ceil(letters * 0.4));
    return `[${out} ${pad.match(/·{1,5}/g)?.join(' ') ?? ''}]`;
}

// ---- Numbers, dates and durations ---------------------------------------------------------------

export function formatNumber(value, options = {}) {
    return cached(Intl.NumberFormat, options).format(value);
}

export function formatDate(time, options = { dateStyle: 'medium' }) {
    return cached(Intl.DateTimeFormat, options).format(new Date(time));
}

/** "just now", "3 minutes ago", "yesterday", then the date. */
export function timeAgo(time, now = Date.now()) {
    const minutes = Math.round((now - time) / 60000);
    if (minutes < 1) return t('common.justNow');
    const relative = cached(Intl.RelativeTimeFormat, { numeric: 'auto' });
    if (minutes < 60) return relative.format(-minutes, 'minute');
    const hours = Math.round(minutes / 60);
    if (hours < 24) return relative.format(-hours, 'hour');
    const days = Math.round(hours / 24);
    if (days < 7) return relative.format(-days, 'day');
    return formatDate(time);
}

/** A wait the server asked for (`retryAfter`, seconds): "20 minutes", "45 seconds", "2 hours". */
export function formatWait(seconds) {
    const [value, unit] = seconds >= 5400 ? [Math.round(seconds / 3600), 'hour'] : seconds >= 90 ? [Math.round(seconds / 60), 'minute'] : [Math.max(1, Math.round(seconds)), 'second'];
    return cached(Intl.NumberFormat, { style: 'unit', unit, unitDisplay: 'long' }).format(value);
}

/** "Alto and Bass", "Alto, Tenor, and Bass", as the person's language lists things. */
export function formatList(items) {
    return cached(Intl.ListFormat, { type: 'conjunction' }).format(items);
}
