"""The first-week journey's first screen (docs/launch-qa-journey.md): the landing as a visitor meets
it, in both skins and both themes, and its Start: where it goes, with what it carries. Shots to
$QA_SHOTS. It runs against the server's own site (the landing is Laravel's), as landing_audio.py:

    (cd server && php -S 127.0.0.1:8422 tool/serve.php)
    SITE=http://127.0.0.1:8422 QA_SHOTS=~/Code/shots-review/launch-qa-journey tool/flows.sh journey_landing

Without SITE and QA_SHOTS it skips (the landing gate has no site running).
"""
import json
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from journey_qa import CHECK  # noqa: E402

SHOTS = Path(os.environ.get("QA_SHOTS", str(Path(__file__).parent / "shots" / "journey"))).expanduser()
START = "[...document.querySelectorAll('a[href*=\"1-comfortable\"]')]"


def journey(f):
    found = []
    for skin in ("serious", "game"):
        for mode in ("light", "dark"):
            f.go("/?landing", "document.fonts.status === 'loaded' && document.querySelector('a[href*=\"1-comfortable\"]')")
            f.run(f"window.stackapellaTheme?.choose({{ skin: {json.dumps(skin)}, mode: {json.dumps(mode)}, gameMode: {json.dumps(mode)} }});")
            f.go("/?landing", "document.fonts.status === 'loaded' && document.querySelector('a[href*=\"1-comfortable\"]')")
            time.sleep(1.0)
            seen = json.loads(f.js(CHECK))
            if seen["over"] > 1 or seen["raw"] or seen["odd"]:
                found.append({"where": f"the landing ({skin}, {mode})", "what": seen})
            SHOTS.mkdir(parents=True, exist_ok=True)
            f.chrome.screenshot(str(SHOTS / f"00-landing-{f.size}-{skin}-{mode}.png"))
            hrefs = f.js(f"JSON.stringify([...new Set({START}.map((a) => a.href))])")
            print(f"     {skin} {mode}: Start goes to {hrefs}")
            if "from=start" not in hrefs:
                found.append({"where": f"the landing's Start ({skin}, {mode})", "what": f"no from=start: {hrefs}"})
    # Start, followed: the app's lesson 1.
    f.run(f"{START}[0].click()")
    try:
        f.wait("location.pathname === '/learn/1-comfortable' && document.querySelector('main h1')", timeout=20, what="lesson 1 from Start")
        print(f"     Start opened {f.js('location.href')}")
        f.chrome.screenshot(str(SHOTS / f"00-start-{f.size}.png"))
    except AssertionError as error:
        found.append({"where": "the landing's Start", "what": str(error)})
    print(f"     {json.dumps(found)}")
    f.chrome.errors.clear()


if __name__ == "__main__":
    if not (os.environ.get("SITE") and os.environ.get("QA_SHOTS")):
        print("skip journey_landing (a QA pass: set SITE and QA_SHOTS)")
        sys.exit(0)
    sys.argv[1:2] = [os.environ["SITE"]]
    run("journey_landing", journey)
