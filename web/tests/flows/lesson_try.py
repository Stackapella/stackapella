"""Try one round on a lesson's public page (docs/lesson-page-try-it-plan.md; the lesson_try switch). With the switch off,
the page has no round and loads no script. With it on, the lesson's first listen-and-choose round: Try it here loads
it, Play it sounds the guide voice, a wrong choice says "Not quite." and why, Try again clears it, a right one says
"Right." and why, and Keep going in the app opens the lesson there tagged ?ref=lesson-try. Every theme at AA on the
card, nothing sideways, both skins, phone and desktop. Against the site server, which this run tells the switch to
(and puts back as it found it):

    python3 -I tests/flows/lesson_try.py http://127.0.0.1:8422
"""
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402
from app_audit import APP_CONTRAST  # noqa: E402
from game_frames import THEMES  # noqa: E402
from game_tools_site import courses_flag  # noqa: E402

SITE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8412"
SERVER = Path(__file__).resolve().parents[3] / "server"
PAGE = "/courses/perfect-your-pitch/scoop-or-clean"
CARD = "document.querySelector('[data-lesson-try]')"


def switch(value=None):
    """lesson_try in the site server's database: its value now, or set (app:flag, with a reason)."""
    if value is None:
        out = subprocess.run(["php", "artisan", "tinker", "--execute", "echo app(App\\Support\\Flags::class)->value('lesson_try');"], cwd=SERVER, capture_output=True, text=True, check=True)
        return out.stdout.strip().splitlines()[-1]
    subprocess.run(["php", "artisan", "app:flag", "lesson_try", value, "--reason=lesson_try flow: the round with the switch off and on"], cwd=SERVER, capture_output=True, text=True, check=True)
    return value


def dark(f):
    f.go(PAGE, "document.querySelector('main h1')")
    assert f.js(f"!{CARD}"), "the round shows with its switch off"
    assert f.js("![...document.scripts].some((s) => s.src.includes('lesson-try.js'))"), "its script loads with the switch off"


def lit(f):
    f.go(PAGE, "document.querySelector('[data-lesson-try-start]')")
    assert f.js("!document.querySelector('[data-lesson-try-play]')"), "the round loads before a tap"
    f.run("document.querySelector('[data-lesson-try-start]').click()")
    f.wait("document.querySelector('[data-lesson-try-play]')", what="the round")
    # Drawn at once with its buttons waiting (no layout shift when the voice arrives), then ready.
    f.wait("!document.querySelector('[data-lesson-try-play]').disabled", timeout=20, what="the round's voice")
    f.run("document.querySelector('[data-lesson-try-play]').click()")
    # Wrong first: Not quite and why, then Try again clears it.
    f.run("document.querySelector('[data-lesson-try-choice=\"1\"]').click()")
    said = f.js(f"{CARD}.querySelector('[role=status]').textContent")
    assert f.js(f"{CARD}.dataset.answered") == "wrong" and said.startswith("Not quite."), said
    # The answer shown on the choices, not only said: the right one ticked, the wrong pick crossed as yours, each in words
    # for a screen reader too (never colour alone).
    marks = f.js("[...document.querySelectorAll('[data-lesson-try-choice]')].map((b) => [b.dataset.lessonTryMark ?? null, !!b.querySelector('svg'), b.querySelector('.sr-only')?.textContent ?? ''])")
    assert marks[0][0] == "right" and marks[0][1] and "Right." in marks[0][2] and "Your answer" not in marks[0][2], f"the right one: {marks}"
    assert marks[1][0] == "yours" and marks[1][1] and "Your answer" in marks[1][2], f"the wrong pick: {marks}"
    f.wait("getComputedStyle(document.querySelector('[data-lesson-try-choice=\"1\"]')).opacity === '1'", what="the marked choices clear (past the buttons' fade)")
    f.run(f"{CARD}.scrollIntoView({{ block: 'center' }})")
    f.shot("wrong")
    low = []
    for colour, mode in THEMES:
        f.run(f"window.stackapellaTheme.choose({{ colour: '{colour}', mode: '{mode}' }})")
        time.sleep(0.35)
        low += [f"{colour} {mode}: {item}" for item in f.chrome.evaluate(APP_CONTRAST)[:2]]
    f.run("localStorage.removeItem('stackapella.appearance'); document.cookie = 'sa_theme=; path=/; max-age=0'; window.stackapellaTheme.apply()")
    assert not low, f"under AA, answered wrong: {low[:4]}"
    f.run("[...document.querySelectorAll('[data-lesson-try] button')].find((b) => b.textContent === 'Try again').click()")
    assert f.js(f"!{CARD}.dataset.answered && !document.querySelector('[data-lesson-try-choice]').disabled && !document.querySelector('[data-lesson-try-mark]')"), "Try again clears the answer and its marks"
    f.run("document.querySelector('[data-lesson-try-choice=\"0\"]').click()")
    said = f.js(f"{CARD}.querySelector('[role=status]').textContent")
    assert f.js(f"{CARD}.dataset.answered") == "right" and said.startswith("Right.") and "scoop" in said, said
    marks = f.js("[...document.querySelectorAll('[data-lesson-try-choice]')].map((b) => [b.dataset.lessonTryMark ?? null, b.querySelector('.sr-only')?.textContent ?? ''])")
    assert marks[0][0] == "right" and "Your answer" in marks[0][1] and marks[1][0] is None, f"right, and yours: {marks}"
    link = f.js("document.querySelector('[data-lesson-try-app]').href")
    assert link.endswith("/learn/pp-1-scoop?ref=lesson-try"), link
    f.no_sideways("the round answered")
    f.run(f"{CARD}.scrollIntoView({{ block: 'center' }})")
    f.shot("round")
    low = []
    for colour, mode in THEMES:
        f.run(f"window.stackapellaTheme.choose({{ colour: '{colour}', mode: '{mode}' }})")
        time.sleep(0.35)
        low += [f"{colour} {mode}: {item}" for item in f.chrome.evaluate(APP_CONTRAST)[:2]]
        f.no_sideways(f"the round in {colour} {mode}")
    f.run("localStorage.removeItem('stackapella.appearance'); document.cookie = 'sa_theme=; path=/; max-age=0'; window.stackapellaTheme.apply()")
    assert not low, f"under AA: {low[:4]}"


def main():
    if courses_flag() != "on":
        courses_flag("on")
    before = switch()
    failures = []
    try:
        for value, check in (("off", dark), ("on", lit)):
            switch(value)
            for skin in ("game", "serious"):
                for size in ("phone", "desktop"):
                    f = flow.Flow(SITE, size, f"lesson_try-{value}", skin=skin)
                    try:
                        check(f)
                        print(f"ok   lesson_try (switch {value}, {skin}, {size})")
                    except Exception as error:  # noqa: BLE001 (every case is tried)
                        failures.append(f"{value} {skin} {size}: {error}")
                        print(f"FAIL lesson_try (switch {value}, {skin}, {size}): {error}")
                    finally:
                        try:
                            f.close()
                        except AssertionError as error:  # page errors
                            failures.append(f"{value} {skin} {size}: {error}")
                            print(f"FAIL lesson_try (switch {value}, {skin}, {size}): {error}")
    finally:
        switch(before)
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
