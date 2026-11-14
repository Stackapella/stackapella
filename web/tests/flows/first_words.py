"""A first visit's lesson 1 starts with its own words (locales/lesson1/en.json, the coordinator's
first-load step): none of its keys missing from that short list, the rest of en.json arriving once
the page is idle, and a view loaded later (the home) in full words. In Spanish the same, from
locales/lesson1/es.json and locales/home1/es.json (the first-session walk, 2026-11-14), never all of es.json first.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

WORDS = "(await import('/js/i18n.js'))"
FETCHED = "performance.getEntriesByType('resource').map((r) => new URL(r.name).pathname)"


def journey(f):
    f.go("/learn/1-comfortable?api=mock&scenario=", "document.querySelector('main h1') && document.querySelector('[data-go-on]')")
    if f.js("!!navigator.serviceWorker?.controller"):
        return  # a controlled visit reads all of en.json from the cache: nothing to check here
    assert f.js(f"{FETCHED}.includes('/locales/lesson1/en.json')"), "lesson 1's words fetched"
    f.wait(f"{FETCHED}.includes('/locales/en.json')", timeout=20, what="the rest of the words, once idle")
    assert f.js(f"{WORDS}.words.missed") == [], f.js(f"{WORDS}.words.missed")
    f.shot("lesson")
    # A view loaded later, in full words.
    f.run("(await import('/js/router.js')).navigate('/')")
    f.wait("document.querySelector('#lead-title') || document.querySelector('main h1')", what="the home")
    assert f.js(f"{WORDS}.words.missed") == [], f.js(f"{WORDS}.words.missed")

    # In Spanish: lesson 1 from its own Spanish words, all of es.json only after; then Home's, the same way.
    for path, bundle, ready in (("/learn/1-comfortable", "lesson1", "document.querySelector('main h1') && document.querySelector('[data-go-on]')"), ("/", "home1", "document.querySelector('#app main h1, #lead-title')")):
        # A first visit again: no service worker in control, nothing cached, nothing chosen.
        f.run("for (const r of await navigator.serviceWorker.getRegistrations()) await r.unregister(); for (const k of await caches.keys()) await caches.delete(k); localStorage.clear()")  # sessionStorage keeps the mock (clearing it sent the page's requests to the real API)
        f.chrome.send("Network.clearBrowserCache", {})
        f.go(f"{path}?api=mock&scenario=&lang=es", ready)
        f.wait("document.documentElement.lang === 'es'", what="Spanish")
        early = f.js(f"{FETCHED}")
        assert f"/locales/{bundle}/es.json" in early and f"/locales/{bundle}/es.source.json" in early, f"{bundle}'s Spanish words: {[u for u in early if 'locales' in u]}"
        f.wait(f"{FETCHED}.includes('/locales/es.json')", timeout=20, what="the rest of the Spanish, once idle")
        assert f.js(f"{WORDS}.words.missed") == [], f.js(f"{WORDS}.words.missed")
        assert f.js(f"{WORDS}.t('common.back')") == "Atrás", "not in Spanish"
        f.shot(f"es-{bundle}")


if __name__ == "__main__":
    run("first_words", journey)
