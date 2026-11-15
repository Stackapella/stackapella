// The combined landing, candidate "grok" (/next-grok; server/resources/views/pages/landing-next-grok.blade.php,
// docs/landing-next-grok-plan.md): which scene is up, and the bars. The page is nine full-screen scenes scrolling over
// one fixed stage; this tells the stage which scene crosses the middle of the window (<main data-scene>, which
// css/input.css's landing-next-grok block turns into the lanes' moves), marks it in the bottom bar's rail, slims the
// top bar while the page goes down, and lets the bottom bar's Play and part buttons press the stage's own.
//
// It makes no sound and keeps no time: playing, hearing a part alone and the playhead are js/public/mixer.js's, on the
// audio clock, and nothing here seeks, pauses or stops it. Scrolling only changes the picture. Hooks are data
// attributes only (never a class), so the page's styles stay the page's.

const root = document.querySelector('[data-lng]');

if (root) {
    // The stage holds still behind the scenes from here on (without this script it's the first screen's picture).
    root.setAttribute('data-lng-live', '');

    const scenes = [...root.querySelectorAll('[data-lng-scene]')];
    const rail = [...root.querySelectorAll('[data-lng-rail]')];
    const show = (n) => {
        if (root.dataset.scene === String(n)) return;
        root.dataset.scene = String(n);
        for (const link of rail) {
            if (link.dataset.lngRail === String(n)) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }
    };
    show(0);
    if ('IntersectionObserver' in window) {
        // The scene crossing the window's middle line is the one that's up.
        const middle = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) show(Number(entry.target.dataset.lngScene));
            }
        }, { rootMargin: '-50% 0px -50% 0px', threshold: 0 });
        for (const scene of scenes) middle.observe(scene);
    }

    // The top bar: slimmer while the page goes down, whole again as it comes back up (and whenever focus is in it: CSS).
    const bar = root.querySelector('[data-lng-bar]');
    let last = scrollY;
    addEventListener('scroll', () => {
        const now = scrollY;
        if (Math.abs(now - last) < 8) return;
        bar?.toggleAttribute('data-slim', now > last && now > 80);
        last = now;
    }, { passive: true });

    // The bottom bar's Play is the stage's own, pressed from wherever you are; it says what that one says.
    const play = root.querySelector('[data-play]');
    const mini = root.querySelector('[data-lng-play]');
    if (play && mini) {
        mini.addEventListener('click', () => play.click());
        const name = () => mini.setAttribute('aria-label', play.getAttribute('aria-label') ?? '');
        new MutationObserver(name).observe(play, { attributes: true, attributeFilter: ['aria-label'] });
        name();
    }

    // Each part's bubble in the bottom bar presses its lane (hear it alone; again, all of them).
    const lanes = new Map([...root.querySelectorAll('[data-voice]')].map((lane) => [lane.dataset.voice, lane]));
    const dots = [...root.querySelectorAll('[data-lng-part]')];
    for (const dot of dots) {
        dot.addEventListener('click', () => lanes.get(dot.dataset.lngPart)?.querySelector('[data-hear-alone]')?.click());
    }

    // "Learn the alto part" follows the part heard alone, or the one picked ("I sing this"), as the song page's does.
    const learn = root.querySelector('[data-lng-learn]');
    const first = lanes.keys().next().value;
    let picked = null;
    let alone = null;
    const follow = () => {
        for (const dot of dots) dot.setAttribute('aria-pressed', String(dot.dataset.lngPart === alone));
        const lane = lanes.get(picked ?? alone ?? first);
        if (!learn || !lane) return;
        learn.href = lane.dataset.learn;
        // Its words, keeping the arrow that follows them.
        const words = [...learn.childNodes].find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '');
        if (words) words.textContent = `${lane.dataset.learnLabel} `;
    };
    root.addEventListener('mixer-solo', (event) => {
        alone = event.detail?.key ?? null;
        follow();
    });
    root.addEventListener('mixer-pick', (event) => {
        picked = event.detail?.key ?? null;
        follow();
    });
}
