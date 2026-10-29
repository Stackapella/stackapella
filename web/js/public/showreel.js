// The landing's film (pages/landing/_showreel.blade.php; server/resources/promo/showreel/README.md).
// Nothing loads until the frame is in view and the page has been scrolled; then the cut matching the
// theme plays muted and loops, and pauses when it leaves the screen or the tab hides. Pause and sound
// are buttons on the picture; the captions show while the sound is on. A theme switch mid-play seeks
// the other cut to the same moment and crossfades once it's ready (the CSS shows the cut named on
// the frame; before the script runs, the theme's). Reduced motion or
// Save-Data: the poster with a play button, and nothing loads until it's pressed. Phones get the
// 540p cut.

const box = document.querySelector('[data-reel]');

if (box) {
    const videos = Object.fromEntries([...box.querySelectorAll('video[data-cut]')].map((v) => [v.dataset.cut, v]));
    const pauseButton = box.querySelector('[data-reel-pause]');
    const soundButton = box.querySelector('[data-reel-sound]');
    const startButton = box.querySelector('[data-reel-start]');
    const calm = matchMedia('(prefers-reduced-motion: reduce)').matches || navigator.connection?.saveData === true;
    const phone = matchMedia('(max-width: 767px)').matches;
    const state = { inView: false, scrolled: false, started: !calm, held: false, sound: false };

    const cutNow = () => (document.documentElement.dataset.theme?.endsWith('-light') ? 'light' : 'dark');
    let cut = cutNow();
    box.dataset.cut = cut; // the cut shown: the theme's, until a switch mid-play hands over
    const active = () => videos[cut];

    /** A cut's file, once: the small one on a phone, else WebM where it plays. */
    const load = (video) => {
        if (video.getAttribute('src')) return;
        video.src = phone ? video.dataset.mobile : video.canPlayType('video/webm; codecs="vp9"') ? video.dataset.webm : video.dataset.mp4;
    };

    const showCaptions = (video, on) => {
        for (const track of video.textTracks) track.mode = on ? 'showing' : 'hidden';
    };

    const play = () => {
        const video = active();
        load(video);
        video.muted = !state.sound;
        showCaptions(video, state.sound);
        video.play().catch((error) => console.warn('The film did not play', error));
    };

    const pause = () => {
        for (const video of Object.values(videos)) video.pause();
    };

    /** Plays while it's on screen, the page has been scrolled and the visitor hasn't paused it. */
    const settle = () => {
        if (!state.started || state.held) return;
        if (state.inView && state.scrolled && document.visibilityState === 'visible') play();
        else pause();
    };

    for (const video of Object.values(videos)) {
        // A WebM that won't load or play: the MP4 instead, from where it was.
        video.addEventListener('error', () => {
            if (video.getAttribute('src') !== video.dataset.webm) return;
            video.src = video.dataset.mp4;
            if (video === active() && !state.held) play();
        });
        video.addEventListener('playing', () => {
            if (video !== active()) return;
            box.dataset.playing = '';
            pauseButton.hidden = false;
            soundButton.hidden = false;
        });
        video.addEventListener('pause', () => {
            if (video === active()) delete box.dataset.playing;
        });
    }

    pauseButton.addEventListener('click', () => {
        state.held = !state.held;
        box.toggleAttribute('data-held', state.held);
        pauseButton.setAttribute('aria-label', state.held ? box.dataset.playLabel : box.dataset.pauseLabel);
        if (state.held) pause();
        else play();
    });

    soundButton.addEventListener('click', () => {
        state.sound = !state.sound;
        soundButton.setAttribute('aria-pressed', String(state.sound));
        soundButton.setAttribute('aria-label', state.sound ? box.dataset.soundOff : box.dataset.soundOn);
        active().muted = !state.sound;
        showCaptions(active(), state.sound);
    });

    if (calm) {
        box.dataset.calm = '';
        startButton.addEventListener('click', () => {
            state.started = true;
            state.scrolled = true;
            box.dataset.started = '';
            play();
            pauseButton.focus();
        });
    } else {
        startButton.hidden = true;
        addEventListener('scroll', () => {
            state.scrolled = true;
            settle();
        }, { once: true, passive: true });
    }

    new IntersectionObserver(([entry]) => {
        state.inView = entry.isIntersecting;
        settle();
    }, { threshold: 0.4 }).observe(box);
    document.addEventListener('visibilitychange', settle);

    // A theme switch: the other cut picks up at the same moment, with the same sound. The one showing
    // keeps playing until the other has its moment ready (its metadata, then the seek a little ahead),
    // then they crossfade and the first pauses. Switched back before that: the other stops instead.
    new MutationObserver(() => {
        const next = cutNow();
        if (next === cut) return;
        const from = active();
        const wasPlaying = !from.paused;
        if (!wasPlaying) {
            cut = next;
            box.dataset.cut = cut;
            return;
        }
        const to = videos[next];
        to.muted = from.muted;
        const handOver = () => {
            if (cutNow() !== next) {
                to.pause();
                return;
            }
            cut = next;
            box.dataset.cut = cut;
            showCaptions(to, state.sound);
            showCaptions(from, false);
            from.pause();
        };
        const seekThenPlay = () => {
            to.addEventListener('seeked', () => {
                to.play().then(handOver, (error) => console.warn('The film did not play', error));
            }, { once: true });
            to.currentTime = Math.min(from.currentTime + 0.15, Math.max(0, (to.duration || Infinity) - 0.05));
        };
        if (to.readyState >= 1) seekThenPlay();
        else {
            to.addEventListener('loadedmetadata', seekThenPlay, { once: true });
            load(to);
            to.load();
        }
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
}
