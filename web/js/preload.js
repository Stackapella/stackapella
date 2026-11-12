// Vue, lesson 1's modules and its words, fetched early on a visit the service worker doesn't control
// yet (the first). Without these the browser finds the modules one import at a time, a round trip
// for each level; with them it asks for all of them at once (no bundling: the files stay). With
// the service worker in control, the imports come from its cache, and a preload beside them would
// be a second fetch that's never used (Chrome: "cross-world service worker resource mismatch"). A
// classic script after the import map, so the preloads never come before it.
(() => {
    if (navigator.serviceWorker?.controller) return;
    const preload = (href, extra = {}) => {
        const link = document.createElement('link');
        link.rel = 'modulepreload';
        link.href = href;
        Object.assign(link, extra);
        document.head.append(link);
    };
    preload('https://cdn.jsdelivr.net/npm/vue@3.5.44/dist/vue.esm-browser.prod.js', { integrity: 'sha384-Yy+wUwjypE8OL2+tZ7lLLMsIVMFBXibFJCdzCWWWXnuVVdiuS3gViCyubnRyWnSP', crossOrigin: 'anonymous' });
    // Where the visit opens (js/i18n.js firstWordsFor): a lesson, Home, or another screen, which starts with all the words.
    const opens = location.pathname === '/' ? 'home' : /^\/(learn\/[\w-]+|cooldown)\/?$/.test(location.pathname) ? 'lesson' : null;
    // That screen's own words (js/i18n.js fetches them on a first visit): asked for now, not after the modules.
    preload(opens === 'lesson' ? '/locales/lesson1/en.json' : opens === 'home' ? '/locales/home1/en.json' : '/locales/en.json', { rel: 'preload', as: 'fetch', crossOrigin: 'anonymous' });
    // The courses (js/stores/learning.js fetches them as the app starts): lesson 1 can't draw without them, and asked for
    // only once every module had run, they came a round trip and a server's answer late (a slow 4G phone, 2026-11-08).
    preload('/course/course.json', { rel: 'preload', as: 'fetch', crossOrigin: 'anonymous' });
    // Every module js/main.js imports, nearest first: written by tool/preload_list.py from the
    // import graph, and tool/test.sh fails when it's stale. main.js itself is the page's own script.
    // preload_list.py: begin
    const MODULES = [
        '/js/errors.js',
        '/js/templates.js',
        '/js/api.js',
        '/js/db.js',
        '/js/i18n.js',
        '/js/storage.js',
        '/js/note-names.js',
        '/js/router.js',
        '/js/stores/session-live.js',
        '/js/components/ui.js',
        '/js/components/confirm.js',
        '/js/stores/plan.js',
        '/js/components/cloud.js',
        '/js/components/account.js',
        '/js/components/credit.js',
        '/js/components/birth.js',
        '/js/components/version.js',
        '/js/components/mic-help.js',
        '/js/components/mic-check.js',
        '/js/game/fonts.js',
        '/js/version.js',
        '/js/components/install.js',
        '/js/components/controls.js',
        '/js/stores/account.js',
        '/js/stores/cloud.js',
        '/js/stores/appearance.js',
        '/js/colors.js',
        '/js/beta-outbox.js',
        '/js/usage.js',
        '/js/funnel.js',
        '/js/asks.js',
        '/js/lazy.js',
        '/js/components/lazy-results.js',
        '/js/lazy-stores.js',
        '/js/components/screen-loading.js',
        '/js/stores/learning.js',
        '/js/courses.js',
        '/js/screen-name.js',
        '/js/page-lesson.js',
        '/js/theme.js',
        '/js/api-mode.js',
        '/js/dsp.js',
        '/js/components/icon.js',
        '/js/project-title.js',
        '/js/audio.js',
        '/js/mic-help.js',
        '/js/mic-check.js',
        '/js/components/level-meter.js',
        '/js/sounds.js',
        '/js/components/mic.js',
        '/js/game/effects.js',
        '/js/game/arcade.js',
        '/js/wav.js',
        '/js/experiments.js',
        '/js/daily-cap.js',
        '/js/course.js',
        '/js/badges.js',
        '/js/game/modules.js',
        '/js/boss.js',
        '/js/course-parts.js',
        '/js/theory.js',
        '/js/logo-mark.js',
        '/js/public/season.js',
        '/engine/engine.js',
        '/js/timing-check.js',
        '/js/mic-choice.js',
        '/js/pitchtrack.js',
        '/js/mic-level.js',
        '/js/stores/prefs.js',
        '/js/warmup.js',
        '/engine/clock.js',
        '/js/listen.js',
    ];
    // preload_list.py: end
    for (const module of MODULES) preload(module);

    // A lesson's own view (views/learn.js and what it imports beyond main.js's list, written by tool/preload_list.py), on a
    // visit that opens on a lesson: main.js loads it with import() (2026-11-12, the split), so Home never waits for it.
    // preload_list.py: learn begin
    const LEARN = [
        '/js/views/learn.js',
        '/js/components/voice-care.js',
        '/js/skin.js',
        '/js/scoring.js',
        '/js/components/voice-capture.js',
        '/js/components/chord-moment.js',
        '/js/components/then-and-now.js',
        '/js/components/course-wall.js',
        '/js/components/course-part.js',
        '/js/components/voice-card.js',
        '/js/components/learn-visual.js',
        '/js/components/lazy-lesson.js',
        '/js/components/fact-card.js',
        '/js/results.js',
        '/js/celebrate.js',
        '/js/share-moment.js',
        '/js/song-course.js',
        '/js/under-mix.js',
        '/js/real-key.js',
        '/js/unit-song.js',
        '/js/lesson-steps.js',
        '/js/sharpen.js',
        '/js/style-scores.js',
        '/js/flip-rest.js',
        '/js/whistle.js',
        '/js/home-today.js',
        '/js/components/boss.js',
        '/js/next-up.js',
        '/js/voice-day.js',
        '/js/creator-audio.js',
        '/js/listening.js',
        '/js/capture-scale.js',
        '/js/pitch-display.js',
        '/js/synth.js',
        '/js/components/note-keyboard.js',
        '/js/notes.js',
        '/js/recorded-guides.js',
        '/js/guide-voice.js',
    ];
    // preload_list.py: learn end
    if (opens === 'lesson') for (const module of LEARN) preload(module);

    // What a lesson draws once begun (js/components/lazy-lesson.js: the exercise, the results, the end), off lesson 1's
    // first screen: asked for all at once when the lesson is drawn, not found one import level at a time.
    // preload_list.py: lesson begin
    const LESSON = [
        '/js/components/exercise.js',
        '/js/components/results.js',
        '/js/components/keep-song.js',
        '/js/stores/projects.js',
        '/js/stores/templates.js',
        '/js/stores/catalogue.js',
        '/js/camera.js',
        '/js/components/gems.js',
        '/js/glass.js',
        '/js/components/dialogs.js',
        '/js/components/key-control.js',
        '/js/game/hud.js',
        '/js/game/popups.js',
        '/js/waitforme.js',
        '/js/lesson-scores.js',
        '/js/harmony.js',
        '/js/tuning.js',
        '/js/ring.js',
        '/js/components/sing/ring-meter.js',
        '/js/keep-song.js',
        '/js/practice.js',
        '/js/components/report-voice.js',
        '/js/band-style.js',
        '/js/game/combo.js',
        '/js/vibrato.js',
        '/js/pitch-skills.js',
        '/js/just.js',
    ];
    // preload_list.py: lesson end
    // Home's own view on a first visit that opens the app at "/" (written by tool/preload_list.py: views/projects.js and what
    // it imports beyond main.js's and game mode's lists): asked for with Vue, not after it.
    if (opens === 'home') {
        // preload_list.py: home begin
    const HOME = [
        '/js/views/projects.js',
        '/js/hosts.js',
        '/js/home.js',
        '/js/stores/catalogue.js',
        '/js/stores/projects.js',
        '/js/stores/ideas.js',
        '/js/components/home.js',
        '/js/game/title-screen.js',
        '/js/skin.js',
        '/js/components/voice-care.js',
        '/js/components/session-wrap.js',
        '/js/beta-status.js',
        '/js/components/form.js',
        '/js/components/appearance.js',
        '/js/stores/templates.js',
        '/js/song-news.js',
        '/js/group-songs.js',
        '/js/rehearsal.js',
        '/js/components/card-menu.js',
        '/js/practice.js',
        '/js/workout.js',
        '/js/reading-lines.js',
        '/js/results.js',
        '/js/components/course-wall.js',
        '/js/components/course-card.js',
        '/js/song-shape.js',
        '/js/components/home-today-line.js',
        '/js/voice-day.js',
        '/js/reminders.js',
        '/js/game-toggle.js',
        '/js/accent-colours.js',
        '/js/synth.js',
        '/js/next-up.js',
        '/js/unit-song.js',
        '/js/whatsnext.js',
        '/js/home-today.js',
    ];
    // preload_list.py: home end
        for (const module of HOME) preload(module);
    }
    let lessonAsked = false;
    window.stackapellaPreloadLesson = () => {
        if (lessonAsked) return;
        lessonAsked = true;
        for (const module of LESSON) preload(module);
    };
})();

// Game mode's own modules (js/game/modules.js imports them; serious mode never does), fetched as the page starts so
// they're in before the first game screen draws, with what they import (motion and the daily pick came a round trip
// late, 2026-11-08), and game mode's pixel type, which lesson 1's first screen is set in. Here, after the import map,
// never in theme-boot.js before it: Firefox ignores an import map that comes after a module load has begun, and the
// app never started (2026-11-02). With the service worker in control they come from its cache, as above.
(() => {
    if (navigator.serviceWorker?.controller) return;
    if (document.documentElement.dataset.skin !== 'game' || !document.querySelector('meta[name="stackapella-app"]')) return;
    // Written by tool/preload_list.py from js/game/modules.js and the import graph; tool/test.sh fails when it's stale.
    // preload_list.py: game begin
    const GAME = [
        '/js/game/chip.js',
        '/js/game/daily.js',
        '/js/game/ghost.js',
        '/js/game/title-card.js',
        '/js/game/tally.js',
        '/js/game/badge-news.js',
        '/js/game/stage-map.js',
        '/js/game/settings.js',
        '/js/components/boards.js',
        '/js/components/account-wall.js',
        '/js/daily-pick.js',
        '/js/game/initials.js',
        '/js/game/motion.js',
        '/js/game/record.js',
    ];
    // preload_list.py: game end
    for (const href of GAME) document.head.append(Object.assign(document.createElement('link'), { rel: 'modulepreload', href }));
    document.head.append(Object.assign(document.createElement('link'), { rel: 'preload', as: 'font', type: 'font/woff2', crossOrigin: 'anonymous', href: '/fonts/stackapella-pixel.woff2' }));
})();
