"""A first visit's lesson 1 starts with its own words (locales/lesson1/en.json, the coordinator's
first-load step): none of its keys missing from that short list, the rest of en.json arriving once
the page is idle, and a view loaded later (the home) in full words.
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


if __name__ == "__main__":
    run("first_words", journey)
