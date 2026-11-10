"""A lesson's later parts load after its first screen (components/lazy-lesson.js; the coordinator's lesson 1 waterfall,
2026-11-09): with the exercise's code held back on the line, lesson 1 still shows, and its fetch ahead has asked for
it; a lesson opened on an exercise while the code is still on its way shows a quiet spinner, never a blank space or an
error, and the exercise as soon as the code is in. Then, with nothing held, a lesson's exercise draws at once (it was
fetched ahead), and the song stores (js/lazy-stores.js) are fetched ahead too, so a song step's Open in Sing never
waits for one."""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

HELD = "/js/components/exercise.js"
LOADING = "document.querySelector('[data-lesson-loading]')"
DRAWN = "document.querySelector('main [data-play-disc], main [data-lane-note]')"  # the exercise's Play, or its lane
NAVIGATE = "(await import('/js/router.js')).navigate"
STORES = ("/js/stores/projects.js", "/js/stores/templates.js", "/js/stores/catalogue.js")


def journey(f):
    chrome = f.chrome
    held, holding = [], [True]
    serve = chrome.answer_cdn

    def answer(params):
        if holding[0] and params["request"]["url"].split("?")[0].endswith(HELD):
            held.append(params["requestId"])  # left paused: the exercise's code is "still on its way"
            return
        serve(params)

    chrome.answer_cdn = answer
    chrome.send("Network.enable")
    chrome.send("Network.setCacheDisabled", {"cacheDisabled": True})
    patterns = [{"urlPattern": "*" + HELD + "*", "requestStage": "Request"}]
    if chrome.cdn:
        patterns.append({"urlPattern": "https://cdn.jsdelivr.net/*", "requestStage": "Request"})
    chrome.send("Fetch.enable", {"patterns": patterns})
    try:
        # Signed in: from lesson 2 a guest is asked for a free account.
        f.go("/?api=mock&as=member", "document.querySelector('#app header')")
        f.go("/learn/1-comfortable?api=mock", "document.querySelector('main h1')")
        f.wait("document.querySelector('[data-mic-first-go], [data-go-on], [data-listening]')", what="lesson 1's first screen, the exercise's code held")
        deadline = time.time() + 20
        while not held and time.time() < deadline:
            f.js("1")  # lets the paused request's event in
            time.sleep(0.1)
        assert held, "lesson 1's fetch ahead never asked for the exercise"

        # A lesson that opens on an exercise, its code still held: the spinner, then the exercise.
        f.run(f"{NAVIGATE}('/learn/1-match')")
        f.wait(LOADING, what="the quiet spinner while the exercise loads")
        assert f.js(f"{LOADING}.getClientRects().length"), "the spinner isn't shown"
        assert f.js(f"{LOADING}.getAttribute('aria-busy')") == "true"
        assert f.js(f"{LOADING}.querySelector('[role=status]').textContent.trim()"), "the spinner says nothing to a screen reader"
        f.wait("!document.querySelector('[data-game-title]')", timeout=10, what="game mode's title card gone")
        assert f.js(f"!!{LOADING}"), "the spinner went before the code came in"
        f.shot("exercise-loading")
        holding[0] = False
        for request in held:
            chrome.send("Fetch.continueRequest", {"requestId": request})
        f.wait(f"!{LOADING} && {DRAWN}", what="the exercise once its code is in")
        f.shot("exercise-drawn")
    finally:
        holding[0] = False
        chrome.answer_cdn = serve
        if not chrome.cdn:
            chrome.send("Fetch.disable")
        else:
            chrome.send("Fetch.enable", {"patterns": [{"urlPattern": "https://cdn.jsdelivr.net/*", "requestStage": "Request"}]})

    # Nothing held: lesson 1 fetches ahead, so the next lesson's exercise never shows the spinner.
    f.go("/learn/1-comfortable?api=mock", "document.querySelector('main h1')")
    f.wait("performance.getEntriesByType('resource').some((r) => r.name.endsWith('" + HELD + "') && r.responseEnd > 0)", what="the exercise fetched ahead")
    for store in STORES:
        f.wait(f"performance.getEntriesByType('resource').some((r) => r.name.endsWith('{store}') && r.responseEnd > 0)", what=f"{store} fetched ahead")
    f.run("window.__spun = false; new MutationObserver(() => { if (document.querySelector('[data-lesson-loading]')) window.__spun = true; }).observe(document.body, { childList: true, subtree: true });")
    f.run(f"{NAVIGATE}('/learn/1-match')")
    f.wait(DRAWN, what="the next lesson's exercise")
    assert not f.js("window.__spun"), "the exercise waited for its code after being fetched ahead"


if __name__ == "__main__":
    run("lesson_lazy", journey, sizes=("phone",))
