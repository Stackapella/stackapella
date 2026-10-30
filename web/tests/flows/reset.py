"""Start fresh on this device (/start-fresh, for testing on a phone): signed in, with a song, keys in both
storages, the app's file caches and its service worker, the page counts what goes; Start fresh signs
out here and leaves a true first visit on the website: no service worker in charge, no old caches,
none of the old keys and no songs. Keep everything leaves it all.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SEED = """const db = await import('/js/db.js');
    await db.put('projects', { id: 'fresh-song', title: 'Kept here', bpm: 90, key: 'C', timeSignature: '4/4', countInBars: 1, metronome: true, createdAt: Date.now(), updatedAt: Date.now() });
    localStorage.setItem('stackapella.freshProbe', '1'); sessionStorage.setItem('stackapella.freshProbe', '1');
    localStorage.setItem('stackapella.appearance', JSON.stringify({ skin: 'serious', mode: 'light', at: Date.now() }));
    localStorage.setItem('stackapella.funnel', JSON.stringify({ firsts: { f_mic: true, f_lesson1: true }, variant: null, arrived: '2026-01-01' }));
    await (await caches.open('stackapella-old-build')).put('/fresh-probe', new Response('x'));
    await navigator.serviceWorker.register('/sw.js'); await navigator.serviceWorker.ready;"""


def journey(f):
    # After the fresh start the mock is gone too (it's a tab setting): the website's own calls answer
    # 404 on this machine's file server.
    f.expected_errors += ["404 (Not Found) http://127.0.0.1"]
    f.go("/settings?api=mock&as=member&scenario=", "document.querySelector('#app h1')")
    f.run(SEED)
    f.go("/start-fresh", "document.querySelector('[data-reset-go]')")
    f.wait("document.querySelector('[data-reset] dd')?.textContent.trim() !== ''", what="what goes, counted")
    f.no_sideways("Start fresh")
    f.shot("reset")
    # Keep everything: nothing goes.
    f.run("document.querySelector('[data-reset] .btn-secondary').click()")
    f.wait("location.pathname === '/'", what="home, kept")
    assert f.js("localStorage.getItem('stackapella.freshProbe')") == "1", "kept"
    f.go("/start-fresh", "document.querySelector('[data-reset-go]')")
    f.run("document.querySelector('[data-reset-go]').click()")
    f.wait("location.search.includes('landing') || !location.pathname.startsWith('/start-fresh')", timeout=30, what="the website, as a new visitor")
    f.wait("document.readyState === 'complete'", what="loaded")
    assert f.js("!navigator.serviceWorker.controller"), "no service worker in charge: a first visit"
    assert not f.js("(await caches.keys()).includes('stackapella-old-build')"), "the old caches gone"
    assert f.js("localStorage.getItem('stackapella.freshProbe') === null && sessionStorage.getItem('stackapella.freshProbe') === null"), "the old keys gone"
    # (A serious run's harness sets its own skin on every page, so the seeded light mode is what's checked.)
    assert not f.js("(localStorage.getItem('stackapella.appearance') ?? '').includes('light')"), "the look gone"
    assert f.js("(await (await import('/js/db.js')).all('projects')).length") == 0, "no songs"
    funnel = f.js("JSON.parse(localStorage.getItem('stackapella.funnel') || '{}')")
    assert funnel.get("arrived") != "2026-01-01" and not (funnel.get("firsts") or {}).get("f_lesson1"), f"a first visit, as the funnel sees it: {funnel}"


run("reset", journey)
