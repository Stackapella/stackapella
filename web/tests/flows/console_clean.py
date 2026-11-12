"""A clean console (9c, from the beta's console): no warnings of ours on the home, the workout and
the studio. Vue is preloaded only on a visit the service worker doesn't control (js/preload.js);
the controlled visit, where Chrome warned of a "cross-world" preload, is checked in DevTools.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def visit(f, path, ready):
    f.go(path, ready)
    time.sleep(1.5)  # the browser says "preloaded but not used" a few seconds after load


def journey(f):
    visit(f, "/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    # Not controlled yet (a first visit): Vue and Home's modules preloaded once each, from the boot script; the lesson's own
    # view only on a visit that opens on a lesson (js/preload.js, main.js's split, 2026-11-12).
    assert f.js("navigator.serviceWorker?.controller ? true : (() => { const all = [...document.querySelectorAll('link[rel=modulepreload]')].map((l) => l.href); return new Set(all).size === all.length && all.filter((h) => h.includes('/vue@')).length === 1 && all.some((h) => h.endsWith('/js/views/projects.js')) && !all.some((h) => h.endsWith('/js/views/learn.js')); })()"), "Vue and Home's modules aren't preloaded once each on a first visit at Home"
    visit(f, "/learn/1-match?api=mock&scenario=&as=signedOut", "document.querySelector('main h1, main h2')")
    assert f.js("navigator.serviceWorker?.controller ? true : (() => { const all = [...document.querySelectorAll('link[rel=modulepreload]')].map((l) => l.href); return new Set(all).size === all.length && all.some((h) => h.endsWith('/js/views/learn.js')); })()"), "a lesson's view isn't preloaded once on a first visit to a lesson"
    visit(f, "/workout?api=mock&scenario=&as=signedOut", "document.querySelector('#app h1')")
    f.run("const s = await import('/js/stores/projects.js'); const p = await s.createProject({ title: 'Console' }); window.made = p.id;")
    visit(f, "/p/" + f.js("window.made") + "?api=mock&scenario=&as=signedOut", "document.querySelector('footer')")
    warnings = [w for w in f.chrome.warnings if "favicon" not in w]
    assert not warnings, f"console warnings: {warnings}"
    f.run("const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all) await s.deleteProject(p.id);")


if __name__ == "__main__":
    run("console_clean", journey)
