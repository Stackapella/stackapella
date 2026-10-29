"""The landing's hero mixer (js/public/mixer.js), as a visitor meets it: signed out, and again with
the app's service worker installed (someone who has used the app). No console error anywhere (a
module the page can't load, like a bare 'vue', fails here); Play is there and visible; pressing it
starts the harmony on the audio clock (the player says it's playing and its playhead moves).

It runs against the server's own site (the landing is Laravel's), at SITE (default
http://127.0.0.1:8412, `php -S 127.0.0.1:8412 tool/serve.php` from server/):

    python3 -I tests/flows/landing_audio.py
    LANDING_SW=0 python3 -I tests/flows/landing_audio.py   # the first visit only, without the app's worker

The service worker stage installs the app's worker (sw.js runs under the app's CSP, so its precache
reaches the CDN), then plays the landing again with the worker in charge.
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
WITH_SW = os.environ.get("LANDING_SW", "1") != "0"
PLAYER = "document.querySelector('#hero [data-play], [data-player] [data-play]')"


def plays(f, label):
    """The serious skin's four-voice player, then the game skin's cabinet (its recorded take)."""
    f.go("/?landing", f"{PLAYER} && document.fonts.status === 'loaded'")
    f.run("window.stackapellaTheme?.choose({ skin: 'serious' }); document.documentElement.dataset.skin = 'serious';")
    # Play shows once the player is ready (its song loaded).
    f.wait(f"(() => {{ const b = {PLAYER}; const r = b.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(b).visibility !== 'hidden'; }})()", timeout=20, what=f"{label}: the hero's Play button visible")
    f.run(f"{PLAYER}.scrollIntoView({{ block: 'center' }}); {PLAYER}.click();")
    f.wait(f"{PLAYER}.closest('[data-player]').hasAttribute('data-playing')", timeout=20, what=f"{label}: the harmony playing")
    start = float(f.js(f"{PLAYER}.closest('[data-player]').style.getPropertyValue('--p') || '0'"))
    time.sleep(1.2)
    later = float(f.js(f"{PLAYER}.closest('[data-player]').style.getPropertyValue('--p') || '0'"))
    assert later > start, f"{label}: the playhead didn't move ({start} then {later}): no audio clock running"
    f.shot(label)
    f.run(f"{PLAYER}.click();")
    # The game skin, come on with no press (as a page opened in game mode): the hero's slot is the
    # cabinet, and the app's own lane plays the take by itself with no sound, with "Press for sound".
    cab = "document.querySelector('[data-cabinet]')"
    sound = "document.querySelector('[data-cab-sound]')"
    f.run("window.stackapellaTheme?.choose({ skin: 'game' }); document.documentElement.dataset.skin = 'game';")
    f.wait("document.querySelector('[data-cab-lane] canvas')", timeout=20, what=f"{label}: the cabinet's lane")
    f.wait(f"{cab}.hasAttribute('data-playing') && !{sound}.hidden", timeout=20, what=f"{label}: the take playing by itself, quietly")
    f.shot(label + "-game-quiet")
    # A press brings the sound in, carrying on from where it is.
    f.run(f"{sound}.click();")
    f.wait(f"{sound}.hidden && {cab}.hasAttribute('data-playing')", timeout=20, what=f"{label}: the take playing with sound")
    time.sleep(1.2)
    assert f.js("document.querySelector('[data-cab-time]').textContent") != "0:00", f"{label}: the take's time didn't move"
    f.shot(label + "-game")
    # Game mode off by its toggle stops it; on again by the toggle (a press) plays it with sound.
    toggle = "document.querySelector('.gt--hero')"
    f.run(f"{toggle}.click();")
    f.wait(f"document.documentElement.dataset.skin === 'serious' && !{cab}.hasAttribute('data-playing')", timeout=10, what=f"{label}: Game mode off stops the take")
    f.run(f"{toggle}.click();")
    f.wait(f"document.documentElement.dataset.skin === 'game' && {cab}.hasAttribute('data-playing') && {sound}.hidden", timeout=20, what=f"{label}: Game mode on plays the take with sound")
    f.run("document.querySelector('[data-cab-play]').click();")
    f.wait(f"!{cab}.hasAttribute('data-playing')", timeout=10, what=f"{label}: the take paused")

def main():
    failures = []
    for size in ("desktop", "phone"):
        # Signed out, a first visit.
        f = flow.Flow(SITE, size, "landing_audio")
        try:
            plays(f, "signed-out")
            if not WITH_SW:
                continue
            # Someone who has used the app: its service worker in charge of the origin, then back to the landing.
            f.expected_errors.append(f"401 (Unauthorized) {SITE}/api/account")  # the app, signed out
            f.go("/?app", "document.querySelector('#app')")
            f.wait("navigator.serviceWorker.controller", timeout=30, what="the app's service worker")
            plays(f, "after-the-app")
        except AssertionError as error:
            failures.append(f"{size}: {error}")
        finally:
            try:
                f.close()  # raises on any console error the page logged
            except AssertionError as error:
                failures.append(str(error))
    print("FAIL landing_audio\n     " + "\n     ".join(failures) if failures else "ok   landing_audio")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
