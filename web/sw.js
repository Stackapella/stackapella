// Stackapella's service worker: lets the app install, and open without a connection.
//
// - Our own files: network first, so a new version shows at once, with the cached copy offline.
// - Pinned CDN files (the version is in the URL, so they never change): cache first.
// - Everything else (fonts aside), including the future /api, passes through untouched.

// One cache a build (the page registers /sw.js?build=<the server's build>): a new version installs a
// whole set of its own beside the running one's, never overwriting files the running one serves, and
// takes over only once all of it is in.
const BUILD = new URL(self.location.href).searchParams.get('build') || 'dev';
const CACHE = `stackapella-v12-${BUILD}`;
const SHELL = [
    '/',
    '/index.html',
    '/css/app.css',
    '/manifest.webmanifest',
    '/icons/icon.svg',
    '/icons/favicon.svg',
    '/js/main.js',
    '/js/router.js',
    '/js/i18n.js',
    '/js/levels.js',
    '/js/lazy.js',
    '/js/screen-name.js',
    '/locales/en.json',
    '/js/audio.js',
    '/js/db.js',
    '/js/device.js',
    '/js/dsp.js',
    '/js/storage.js',
    '/js/theme.js',
    '/js/wav.js',
    '/js/stores/projects.js',
    '/js/stores/session.js',
    '/js/pitchtrack.js',
    '/js/pitchtrack-worker.js',
    '/js/scoring.js',
    '/js/sing.js',
    '/js/course.js',
    '/js/quality.js',
    '/js/mic-help.js',
    '/js/listening.js',
    '/js/celebrate.js',
    '/js/hosts.js',
    '/js/logo-mark.js',
    '/js/pitch-display.js',
    '/js/record-setup.js',
    '/js/api.js',
    '/js/api-mock.js',
    '/js/schema.js',
    '/js/recorded-guides.js',
    '/js/stores/catalogue.js',
    '/js/views/beta.js',
    '/js/beta.js',
    '/js/beta-outbox.js',
    '/js/beta-clip.js',
    '/js/components/beta.js',
    '/js/components/beta-clip.js',
    '/js/components/card-menu.js',
    '/js/components/controls.js',
    '/js/components/count-in.js',
    '/js/game/effects.js',
    '/js/game/motion.js',
    '/js/game/chip.js',
    '/js/game/fonts.js',
    '/js/game/settings.js',
    '/js/game/popups.js',
    '/js/game/hud.js',
    '/js/game/record.js',
    '/js/game/title-card.js',
    '/js/game/title-screen.js',
    '/js/game/tally.js',
    '/js/game/versus.js',
    '/js/game/stage-map.js',
    '/js/game/part-select.js',
    '/js/game/crt.js',
    '/js/game/ghost.js',
    '/js/game/initials.js',
    '/js/game/daily.js',
    '/js/game/badge-news.js',
    '/js/views/projects.js',
    '/js/views/studio.js',
    '/js/views/sing.js',
    '/js/views/learn.js',
    '/js/stores/learning.js',
    '/js/components/course-map.js',
    '/js/components/fact-card.js',
    '/js/components/more-courses.js',
    '/js/components/bullseye.js',
    '/js/components/breath-hold.js',
    '/js/components/wave-lane.js',
    '/js/components/choose.js',
    '/js/components/selfcheck.js',
    '/js/lesson-steps.js',
    '/js/lesson-scores.js',
    '/js/courses.js',
    '/js/sharpen.js',
    '/js/course-clips.js',
    '/js/vibrato.js',
    '/js/pitch-skills.js',
    '/js/ring.js',
    '/js/style-scores.js',
    '/js/harmony.js',
    '/js/keep-song.js',
    '/js/components/keep-song.js',
    '/js/views/plus-course.js',
    '/js/views/ideas.js',
    '/js/stores/ideas.js',
    '/js/views/reset.js',
    '/js/components/share-idea.js',
    '/js/components/idea-voice.js',
    '/js/components/exercise.js',
    '/course/course.json',
    '/course/plus/courses.json',
    '/course/plus/vibrato/course.json',
    '/course/plus/folk/course.json',
    '/course/plus/pitch/course.json',
    '/course/plus/ear/course.json',
    '/course/plus/counterpoint/course.json',
    '/course/plus/improvising-harmony/course.json',
    '/course/plus/breath/course.json',
    '/course/plus/barbershop/course.json',
    '/js/views/auth.js',
    '/js/views/settings.js',
    '/js/views/storage.js',
    '/js/views/voice.js',
    '/js/views/workout.js',
    '/js/views/plus.js',
    '/js/views/reminders.js',
    '/js/reminders.js',
    '/js/stores/plan.js',
    '/js/components/cloud.js',
    '/js/components/trim.js',
    '/js/stores/account.js',
    '/js/components/account.js',
    '/js/turnstile.js',
    '/js/components/install.js',
    '/js/components/voice-note.js',
    '/js/components/home.js',
    '/js/components/confirm.js',
    '/js/listen.js',
    '/js/mic-check.js',
    '/js/results.js',
    '/js/camera.js',
    '/js/glass.js',
    '/js/patch.js',
    '/js/notes.js',
    '/js/livevoice.js',
    '/js/sync.js',
    '/js/trim.js',
    '/js/polish.js',
    '/js/waitforme.js',
    '/js/report.js',
    '/js/badges.js',
    '/js/workout.js',
    '/js/mp3.js',
    '/js/zip.js',
    '/js/whatsnext.js',
    '/js/share.js',
    '/js/transcribe.js',
    '/js/hum.js',
    '/js/lyrics.js',
    '/js/arrange.js',
    '/js/creator.js',
    '/js/stores/templates.js',
    '/js/creator-audio.js',
    '/js/views/create.js',
    '/js/views/template.js',
    '/js/views/join.js',
    '/js/views/early.js',
    '/js/views/groups.js',
    '/js/components/together.js',
    '/js/components/studio.js',
    '/js/components/appearance.js',
    '/js/colors.js',
    '/js/theme-boot.js',
    '/js/preload.js',
    '/js/old-browser.js',
    '/js/stores/appearance.js',
    '/js/components/create/check.js',
    '/js/components/create/chords.js',
    '/js/components/create/chord-chart.js',
    '/js/components/create/chord-walk.js',
    '/js/components/playhead.js',
    '/js/lane-painter.js',
    '/js/skin.js',
    '/js/funnel.js',
    '/js/game-toggle.js',
    '/js/components/create/editor.js',
    '/js/components/create/from-project.js',
    '/js/components/create/parts.js',
    '/js/components/create/roll.js',
    '/js/components/create/save.js',
    '/js/components/create/setup.js',
    '/js/components/create/sing-line.js',
    '/js/stores/sharing.js',
    '/js/components/share.js',
    '/js/components/whatsnext.js',
    '/js/components/pitch-chart.js',
    '/js/stores/cloud.js',
    '/js/stores/prefs.js',
    '/js/components/mic.js',
    '/js/components/mic-help.js',
    '/js/components/chord-moment.js',
    '/js/components/session-wrap.js',
    '/js/components/screen-loading.js',
    '/js/components/then-and-now.js',
    '/js/components/voice-card.js',
    '/js/components/create/hum-tune.js',
    '/js/components/listening.js',
    '/js/components/level-meter.js',
    '/js/components/mic-check.js',
    '/js/components/results.js',
    '/js/components/plus-moment.js',
    '/js/components/learn-visual.js',
    '/js/home.js',
    '/js/views/course.js',
    '/js/components/version.js',
    '/js/components/page.js',
    '/js/version.js',
    '/js/move.js',
    '/js/theory.js',
    '/js/backing.js',
    '/js/accent-colours.js',
    '/js/views/moved.js',
    '/js/views/contact.js',
    '/js/components/sing/chooser.js',
    '/js/components/sing/coach.js',
    '/js/components/sing/hearing.js',
    '/js/components/sing/frame.js',
    '/js/components/sing/sheets.js',
    '/js/components/sing/lane.js',
    '/js/components/sing/parts.js',
    '/js/components/sing/recorded.js',
    '/js/components/sing/resing.js',
    '/js/components/sing/stage.js',
    '/js/components/sing/recording.js',
    '/js/components/birth.js',
    '/js/sounds.js',
    '/sounds/stars-1.mp3',
    '/sounds/stars-2.mp3',
    '/sounds/stars-3.mp3',
    '/sounds/mic-ready.mp3',
    '/sounds/special.mp3',
    '/js/components/sing/staff.js',
    '/js/components/icon.js',
    '/js/components/ui.js',
    '/js/components/dialogs.js',
    '/js/components/notes.js',
    '/js/components/practice.js',
    '/js/components/form.js',
    '/js/components/gems.js',
    '/js/components/invite-card.js',
    '/js/components/key-control.js',
    '/js/errors.js',
    '/js/instruments.js',
    '/js/band.js',
    '/js/instrument-parts.js',
    '/js/usage.js',
    '/js/result-card.js',
    '/js/components/share-result.js',
    '/js/components/boards.js',
    '/js/views/challenge.js',
    '/js/practice.js',
    '/js/synth.js',
    '/js/synth-worker.js',
    '/js/guides.js',
    '/js/templates.js',
    '/templates/index.json',
    '/templates/amazing-grace.json',
    '/templates/are-you-sleeping.json',
    '/templates/happy-birthday.json',
    '/templates/michael-row.json',
    '/engine/clock.js',
    '/engine/engine.js',
    '/engine/recorder-worklet.js',
    'https://cdn.jsdelivr.net/npm/vue@3.5.44/dist/vue.esm-browser.prod.js',
];

// The app's own addresses (every route in router.js; tests/sw-routes.test.js keeps them in step);
// every other page is the server's.
const APP_ROUTE = /^\/(?:$|index\.html$|p\/[\w-]+(?:\/sing)?\/?$|styleguide\/?$|beta(?:\/[\w-]*)?\/?$|signin(?:\/link)?\/?$|settings(?:\/reminders)?\/?$|storage\/?$|voice\/?$|workout\/?$|plus\/?$|learn(?:\/c)?(?:\/[\w-]+)?\/?$|verify\/?$|reset\/?$|email\/(?:confirm|revert)\/?$|create(?:\/[\w-]+)?\/?$|t\/[\w-]+\/?$|(?:join|early|invite|choir|challenge)\/[\w-]+\/?$|groups(?:\/[\w-]+)?\/?$|ideas(?:\/[\w-]+)?\/?$|start-fresh\/?$|moved\/?$|contact\/?$|admin(?:\/(?!sign-in|sign-out|second-factor)[\w-]+(?:\/[\w-]+)?)?\/?$)/;

/**
 * Caches every file of the app, or none: a file that fails (a folder the server doesn't serve, say)
 * fails the install, so the version already running stays in charge and the app keeps working. The
 * open pages are told which files failed, and log it.
 */
async function precache() {
    const cache = await caches.open(CACHE);
    const results = await Promise.allSettled(SHELL.map(async (url) => {
        const response = await fetch(new Request(url, { cache: 'reload' }));
        if (!response.ok) throw new Error(`${response.status} ${url}`);
        return [url, response];
    }));
    const failed = results.filter((r) => r.status === 'rejected').map((r) => r.reason?.message ?? String(r.reason));
    if (failed.length) {
        for (const client of await self.clients.matchAll({ includeUncontrolled: true })) client.postMessage({ type: 'install-failed', failed });
        throw new Error(`The new version could not cache: ${failed.join(', ')}`);
    }
    await Promise.all(results.map((r) => cache.put(...r.value)));
}

self.addEventListener('install', (event) => {
    // From the network, not the browser's cache: a new version's files, never a mix with the old.
    // The carried files come over now, while the old version and its cache are still in charge.
    event.waitUntil(
        precache()
            .then(() => caches.keys())
            .then((keys) => carryOver(keys.filter((key) => key !== CACHE)))
            .then(() => self.skipWaiting()),
    );
});

/**
 * Pinned files kept as they're used rather than precached (MP3 export's encoder, fetched only when
 * someone exports an MP3). Their address names the exact version, so they never change: a new
 * version copies them from the old cache as it installs, instead of losing them until the next time
 * online.
 */
const CARRIED = ['https://cdn.jsdelivr.net/npm/lamejs@1.2.1/lame.min.js'];

/** The CARRIED files the old caches hold, copied into this version's. */
async function carryOver(old) {
    const cache = await caches.open(CACHE);
    for (const key of old) {
        const before = await caches.open(key);
        for (const url of CARRIED) {
            const hit = (await cache.match(url)) ? null : await before.match(url);
            if (hit) await cache.put(url, hit);
        }
    }
}

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.pathname.startsWith('/api/')) {
        return;
    }
    // A real instrument's version never changes (contract/instruments.md): kept for good once fetched.
    const instrumentFile = /\/instruments\/[a-z][a-z0-9-]+\/[^/]+\/./.test(url.pathname) && !url.pathname.endsWith('/index.json');
    const pinned = url.hostname === 'cdn.jsdelivr.net' || url.hostname === 'fonts.gstatic.com' || instrumentFile;
    if (pinned) {
        event.respondWith(caches.match(request).then((hit) => hit ?? fetchAndKeep(event, request)));
    } else if (url.origin === self.location.origin && url.pathname.startsWith('/js/admin/')) {
        // The admin's code is never kept: it's fetched fresh each time (docs/beta-singers-plan.md),
        // past the browser's own cache too, so a deploy shows at once.
        event.respondWith(fetch(fresh(request)));
    } else if (request.mode === 'navigate' && !APP_ROUTE.test(url.pathname)) {
        // A server page (sign-in, the second factor, public pages): never stored as the app shell.
    } else if (url.origin === self.location.origin || url.hostname === 'fonts.googleapis.com') {
        // An app address (like /p/123) is the app shell.
        const key = request.mode === 'navigate' ? '/index.html' : request;
        event.respondWith(fetchAndKeep(event, request, key).catch(() => caches.match(key)));
    }
});

/**
 * The request, asking the server whether it changed (the browser's cache would otherwise answer for
 * a few minutes after a deploy, and old modules could load beside new ones). Unchanged, the server
 * answers 304 and nothing is downloaded again.
 */
function fresh(request) {
    if (request.mode === 'navigate') return new Request(request.url, { cache: 'no-cache', credentials: 'same-origin', redirect: 'follow' });
    return new Request(request, { cache: 'no-cache' });
}

/** Answers from the network, and keeps a copy (the worker stays alive until it's kept). */
async function fetchAndKeep(event, request, key = request) {
    const sameOrigin = new URL(request.url).origin === self.location.origin;
    const response = await fetch(sameOrigin ? fresh(request) : request);
    if (response.ok) {
        const copy = response.clone();
        const keeping =
            key === '/index.html'
                ? // Only the app's own shell is kept as the shell: a server page answering an app
                  // address (the coming-soon page at /) must never become what the app opens offline.
                  copy.text().then((html) => html.includes('<div id="app"></div>') && caches.open(CACHE).then((cache) => cache.put(key, new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))))
                : caches.open(CACHE).then((cache) => cache.put(key, copy));
        event.waitUntil(keeping.catch((error) => console.warn('Not kept for offline use', key, error)));
    }
    return response;
}

// ---- Practice reminders (js/reminders.js; contract/api.md reminders.devices.*) --------------------

/** A value the app kept in IndexedDB's `meta` store, or null (the database is never made here). */
function kept(key) {
    return new Promise((resolve) => {
        const request = indexedDB.open('stackapella');
        request.onupgradeneeded = () => request.transaction.abort(); // no database yet: nothing kept
        request.onerror = () => resolve(null);
        request.onsuccess = () => {
            const database = request.result;
            if (!database.objectStoreNames.contains('meta')) {
                database.close();
                resolve(null);
                return;
            }
            const get = database.transaction('meta').objectStore('meta').get(key);
            get.onsuccess = () => {
                resolve(get.result ?? null);
                database.close();
            };
            get.onerror = () => {
                resolve(null);
                database.close();
            };
        };
    });
}

// Every push shows a notification (Safari ends a subscription that gets one without), worded in
// the learner's language from what the app kept; a tap opens the course (or the push's own url).
self.addEventListener('push', (event) => {
    event.waitUntil(
        (async () => {
            let data = {};
            try {
                data = event.data?.json() ?? {};
            } catch {
                data = {};
            }
            const words = (await kept('reminderWords')) ?? { title: 'Time to sing', body: 'A few minutes of practice keeps your weekly goal going.' };
            await self.registration.showNotification(words.title, { body: words.body, icon: '/icons/icon-192.png', badge: '/icons/icon-192.png', tag: 'practice', data: { url: data.url ?? '/learn' } });
        })(),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url ?? '/learn', self.location.origin);
    if (url.origin !== self.location.origin) return; // only ever our own pages
    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            const open = windows.find((client) => new URL(client.url).origin === self.location.origin);
            if (open) {
                // The app moves there itself (never mid-take): reloading the window could cut a recording.
                await open.focus();
                open.postMessage({ type: 'navigate', path: url.pathname + url.search });
            } else {
                await self.clients.openWindow(url.href);
            }
        })(),
    );
});

// The push service renewed the subscription: the account hears of it by the device's token alone.
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        (async () => {
            const device = await kept('reminderDevice');
            const key = event.oldSubscription?.options?.applicationServerKey;
            if (!device || !key) return;
            const subscription = event.newSubscription ?? (await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }));
            await fetch(`/api/reminders/devices/${encodeURIComponent(device.id)}`, {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Device-Token': device.token },
                body: JSON.stringify(subscription.toJSON()),
                credentials: 'omit',
            });
        })(),
    );
});
