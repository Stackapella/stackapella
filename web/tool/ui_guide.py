"""Picture sheets for review: every screen numbered, game mode and serious side by side (12a game,
12b serious), so Hillel can say "12b: move the button up". One PNG per sheet, rendered from an HTML
grid by headless Chrome (tool/cdp.py, no new dependencies), with an index and the single shots.

    python3 -I tool/ui_guide.py funnel        # the onboarding funnel: ~/Code/shots-review/funnel/
    python3 -I tool/ui_guide.py guide         # the master UI guide: ~/Code/shots-review/ui-guide/

It runs the journeys first (tests/flows/journey_qa.py and journey_landing.py, phone size, both
skins, Warm light), against the mock API and the server's own site, each on its own port.
SKIP_RUN=1 lays out the last run's shots again (after a change to the lists below); EXTRA_ONLY=1 runs
only the flows behind "More screens" and adds them to the last run; OUT=<dir> moves the output. The images aren't committed: they're for review, and are made again after a change.
"""
import html
import json
import os
import shutil
import subprocess
import sys
import time
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
SERVER = WEB.parent / "server"
sys.path.insert(0, str(WEB / "tool"))
from cdp import Chrome  # noqa: E402

REVIEW = Path(os.environ.get("OUT", "~/Code/shots-review")).expanduser()
PORT = int(os.environ.get("PORT", "8396"))
SITE_PORT = PORT + 26
SKINS = ("game", "serious")
MAX_HEIGHT = 12000  # a sheet past this is split by area

# The onboarding funnel, in the order a new visitor meets it: (screen, what the person is asked to
# do, the shot). A shot is "<file>" from journey_qa (the number and size dropped: "{skin}" is filled
# in) or "landing:<file>" from journey_landing or this tool's own landing scroll.
FUNNEL = [
    ("The landing, its hero", "Start singing, or hear four voices first", "landing:00-landing-phone-{skin}-light"),
    ("The landing, the first scroll", "Hear the song's parts; Start singing", "landing:01-landing-scroll-phone-{skin}"),
    ("Lesson 1, the first step", "Read the three steps; Continue", "arrival"),
    ("Lesson 1, the mic", "Turn on my mic (the browser then asks)", "mic-ask"),
    ("Lesson 1, the note finder", "Sing any easy note and hold it", "got-you"),
    ("Lesson 1, your note", "Hear your note in a chord; Share my note; Next lesson", "note-result"),
    ("Lesson 1, the chord moment", "Listen: your note in a chord", "chord-playing"),
    ("Lesson 1, the voice card", "See your voice; go on", "chord-line"),
    ("Lesson 2, learn", "Read what the lesson is for; Continue", "l2-learn"),
    ("Lesson 2, headphones", "Put headphones on (or go on without)", "headphones"),
    ("Lesson 2, the try", "Sing along; Continue", "l2-try-done"),
    ("Lesson 2, a matched take", "See how close you were; go on", "1-match-take"),
    ("Lesson 2, Hear it together", "Hear your take with the guide", "hear-together"),
    ("Lesson 2, the end", "Next lesson, or play again", "l2-end"),
    ("Lesson 3, learn", "Read; Continue", "l3-learn"),
    ("Lesson 3, a take", "Sing high and low; see the result", "1-high-low-take"),
    ("Lesson 3, the end", "Next lesson, or play again", "l3-end"),
    ("The session wrap", "Pick days a week; install the app, or Not now", "wrap"),
    ("The session wrap, a goal chosen", "Install, or Not now; Challenge a friend", "wrap-goal"),
    ("Home after session 1", "Continue the course; install; start a song", "home"),
    ("Share as a guest", "Save it to share: sign up (the reason is the share)", "share-guest"),
    ("Sign-up, with its reason", "Enter an email to keep your note and songs", "signup"),
    ("Check your email", "Open the link to verify", "check-email"),
    ("Signed in, back where you were", "Go on with what you were doing", "signed-in"),
    ("Day 2's Home, welcome back", "Continue lesson 4; install (the reminders offer waits while install shows)", "day2-home"),
    ("Day 2's next lesson", "Start lesson 4", "day2-lesson"),
    ("Day 7's first-week card", "Read your first week; Got it", "day7-week"),
]


def run_journeys(runs):
    """journey_qa (phone, both skins, light) into runs/qa, journey_landing into runs/landing."""
    if runs.exists():
        shutil.rmtree(runs)
    env = {**os.environ, "FLOW_SIZES": "phone", "FLOW_SKINS": ",".join(SKINS), "QA_LOOK": "light", "PORT": str(PORT)}
    subprocess.run(["tool/flows.sh", "journey_qa"], cwd=WEB, check=True, env={**env, "QA_SHOTS": str(runs / "qa"), "QA_OUT": str(runs / "qa-found")})
    site = subprocess.Popen(["php", "-S", f"127.0.0.1:{SITE_PORT}", "tool/serve.php"], cwd=SERVER, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        time.sleep(1.5)
        base = f"http://127.0.0.1:{SITE_PORT}"
        subprocess.run(["tool/flows.sh", "journey_landing"], cwd=WEB, check=True, env={**env, "SITE": base, "QA_SHOTS": str(runs / "landing")})
        landing_scroll(base, runs / "landing")
    finally:
        site.terminate()


def landing_scroll(base, out):
    """The landing's first scroll, in each skin, in light."""
    for skin in SKINS:
        chrome = Chrome(None, 390, 844, True)
        try:
            ready = "document.fonts.status === 'loaded' && document.querySelector('a[href*=\"1-comfortable\"]')"
            chrome.goto(base + "/?landing", ready)
            chrome.evaluate(f"window.stackapellaTheme?.choose({{ skin: {json.dumps(skin)}, mode: 'light', gameMode: 'light' }})")
            chrome.goto(base + "/?landing", ready)
            chrome.evaluate("window.scrollTo(0, Math.round(innerHeight * 0.85))")
            time.sleep(1.0)
            chrome.screenshot(str(out / f"01-landing-scroll-phone-{skin}.png"))
        finally:
            chrome.close()


def find_shot(runs, key, skin):
    """The run's file for a list entry, or None."""
    if key.startswith("landing:"):
        path = runs / "landing" / (key.split(":", 1)[1].format(skin=skin) + ".png")
        return path if path.exists() else None
    hits = sorted((runs / "qa").glob(f"[0-9][0-9]-{key}-phone-{skin}-light.png"))
    return hits[0] if hits else None


def sheet(cards, title, subtitle, png):
    """Renders `cards` ({number, name, state, game, serious}) as one PNG grid (or more, split by area
    past MAX_HEIGHT): each card's number large, its name and state, its two skins side by side."""
    tiles = []
    for c in cards:
        shots = "".join(
            f"""<figure><div class="tag {skin}">{c['number']}{'a' if skin == 'game' else 'b'} · {skin.upper()}</div>
            {f'<img src="{Path(c[skin]).as_uri()}">' if c.get(skin) else '<div class="missing">not reached</div>'}</figure>"""
            for skin in SKINS)
        tiles.append(f"""<section><header><b>{c['number']}</b><div><h2>{html.escape(c['name'])}</h2>
            <p>{html.escape(c['state'])}</p></div></header><div class="pair">{shots}</div></section>""")
    page = f"""<!doctype html><meta charset="utf-8"><style>
        body {{ margin: 0; padding: 40px; background: #f4efe8; font: 18px/1.35 -apple-system, Helvetica, sans-serif; color: #241c18; width: 2320px; }}
        h1 {{ font-size: 44px; margin: 0 0 6px; }} .sub {{ margin: 0 0 30px; color: #6b5d55; font-size: 20px; }}
        .grid {{ display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }}
        section {{ background: #fff; border-radius: 22px; padding: 18px; box-shadow: 0 1px 3px #0002; }}
        header {{ display: flex; gap: 14px; align-items: center; margin-bottom: 12px; }}
        header b {{ font-size: 46px; min-width: 96px; text-align: center; background: #241c18; color: #fff; border-radius: 16px; padding: 4px 10px; }}
        h2 {{ font-size: 22px; margin: 0; }} header p {{ margin: 2px 0 0; color: #6b5d55; font-size: 17px; }}
        .pair {{ display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }}
        figure {{ margin: 0; }} img {{ width: 100%; border-radius: 12px; border: 1px solid #0002; display: block; }}
        .tag {{ font-weight: 800; font-size: 18px; margin-bottom: 6px; }} .tag.game {{ color: #b0306f; }} .tag.serious {{ color: #2f5f8f; }}
        .missing {{ height: 300px; display: grid; place-items: center; border: 2px dashed #0003; border-radius: 12px; color: #6b5d55; }}
    </style><h1>{html.escape(title)}</h1><p class="sub">{html.escape(subtitle)}</p><div class="grid">{''.join(tiles)}</div>"""
    source = png.with_suffix(".html")
    source.write_text(page)
    chrome = Chrome(None, 2400, 1000, False)
    try:
        chrome.goto(source.as_uri(), "document.readyState === 'complete' && [...document.images].every((i) => i.complete)")
        height = chrome.evaluate("document.documentElement.scrollHeight")
        chrome.send("Emulation.setDeviceMetricsOverride", {"width": 2400, "height": int(height), "deviceScaleFactor": 1, "mobile": False})
        time.sleep(0.5)
        chrome.screenshot(str(png))
    finally:
        chrome.close()
    source.unlink()
    return height


def funnel():
    out = REVIEW / "funnel"
    runs = out / "_runs"
    if os.environ.get("SKIP_RUN") != "1":
        run_journeys(runs)
    singles = out / "shots"
    if singles.exists():
        shutil.rmtree(singles)
    singles.mkdir(parents=True)
    cards = []
    for i, (name, ask, key) in enumerate(FUNNEL, 1):
        card = {"number": f"F{i}", "name": name, "state": ask}
        for skin in SKINS:
            found = find_shot(runs, key, skin)
            if found:
                card[skin] = singles / f"F{i:02d}{'a' if skin == 'game' else 'b'}-{skin}.png"
                shutil.copy(found, card[skin])
        cards.append(card)
    height = sheet(cards, "Stackapella: the onboarding funnel", "A new visitor's path at phone size, in Warm light. Each step: a = game mode, b = serious. Say \"F7b: ...\" to point at one.", out / "funnel-sheet.png")
    rows = ["| Step | Screen | What the person is asked to do | Game | Serious |", "|---|---|---|---|---|"]
    rows += [f"| {c['number']} | {c['name']} | {c['state']} | {'F%sa' % c['number'][1:] if c.get('game') else 'not reached'} | {'F%sb' % c['number'][1:] if c.get('serious') else 'not reached'} |" for c in cards]
    found = []
    for f in sorted((runs / "qa-found").glob("*.json")) if (runs / "qa-found").exists() else []:
        found += [f"- {f.stem}: {x['where']}: {x['what']}" for x in json.loads(f.read_text())["found"]]
    (out / "index.md").write_text("# The onboarding funnel\n\nMade by `python3 -I web/tool/ui_guide.py funnel` from journey_qa and journey_landing, phone size, Warm light.\n\n" + "\n".join(rows) + "\n\n## What the journey noticed\n\n" + ("\n".join(found) or "Nothing.") + "\n")
    print(f"funnel: {len(cards)} steps, {sum(1 for c in cards for s in SKINS if c.get(s))} shots, sheet {height} px tall: {out / 'funnel-sheet.png'}")

# The master UI guide, by area: (screen, state, the shot). A shot is "j:<label>" from journey_qa,
# "f:<flow>:<label>" from a flow's own shots (tests/flows/<flow>.py, phone), or "o:<file>" from a
# flow's OUT folder ("{skin}" is "" in game, "-serious" in serious). Game-only screens have no b.
GUIDE = {
    "Learn": [
        ("First visit", "Home, nothing done yet", "f:home:first-visit"),
        ("Lesson 1 from Start", "the landing's Start, the first step", "j:arrival"),
        ("Lesson 1, the mic", "Turn on my mic", "j:mic-ask"),
        ("The note finder", "listening", "f:find_note:listening"),
        ("The note finder", "hearing you, the mic works", "j:got-you"),
        ("Your note", "the note, the chord, Share my note", "j:note-result"),
        ("A lesson", "before singing", "j:l2-learn"),
        ("A lesson", "singing along", "f:course:sing-it"),
        ("A lesson", "the take's result", "j:1-match-take"),
        ("A lesson", "Hear it together", "j:hear-together"),
        ("A lesson's end", "up next, and Did you know?", "j:l2-end"),
        ("The session wrap", "days a week and install", "j:wrap"),
        ("The course map", "after a few lessons", "f:course:map"),
        ("A Plus course page", "Sharpen up", "o:sharpen-card-phone{skin}"),
        ("Sharpen up", "its first lesson", "o:sharpen-lesson-phone{skin}"),
    ],
    "Sing and studio": [
        ("The library", "the song cards", "f:library:cards"),
        ("A song page", "the sheet", "j:song-sheet"),
        ("A song page", "playing", "j:song-playing"),
        ("Sing", "the part chooser", "j:chooser"),
        ("Sing", "the lane, the coach", "j:coach"),
        ("Sing", "get ready", "f:sing:get-ready"),
        ("Sing", "the count-in (game)", "f:game_hud:count-in"),
        ("Sing", "recording", "j:recording"),
        ("Sing", "Just recorded", "j:just-recorded"),
        ("Sing", "the staff view", "f:sing:staff"),
        ("The studio", "a song, nothing recorded", "f:studio_template:played"),
        ("The studio", "a row open", "f:studio_template:row-open"),
        ("The studio", "two tracks", "f:studio_record:two-tracks"),
        ("The studio", "a row with its takes", "f:studio_record:takes"),
        ("The studio", "ready to record, the armed ring (game)", "f:game_hud:ready"),
        ("The studio", "a refused save (out of room)", "f:locked:studio"),
        ("Export", "the dialog", "f:export_polish:export"),
        ("Share", "signed in", "j:share-signed-in"),
        ("Share", "the card, as a post", "f:result_share:post"),
        ("Make your own", "the song builder's check", "f:create:check"),
        ("Ideas", "the shelf", "f:ideas:shelf"),
    ],
    "Home and account": [
        ("Home", "after session 1", "j:home"),
        ("Home", "with songs", "j:home-songs"),
        ("Home", "day 2, welcome back", "j:day2-home"),
        ("Home", "day 7, the first week", "j:day7-week"),
        ("Sign-up", "with its reason", "j:signup"),
        ("Check your email", "after sign-up", "j:check-email"),
        ("Sign in", "the sheet", "f:accounts:sign-in"),
        ("Your voice", "a member", "f:voice:member"),
        ("Settings", "the top", "j:settings"),
        ("Settings", "Game mode", "j:settings-game"),
        ("Settings", "Game mode's own switches (game)", "f:game_settings:game-light"),
        ("Storage", "with course takes", "f:device_wipe:storage-course-takes"),
        ("This device", "erase, as a guest", "f:device_wipe:confirm-guest"),
        ("Plus", "the plans", "f:plus:plus-page"),
        ("Plus", "on Plus, billing", "f:plus:on-plus"),
        ("Choir groups", "your groups", "f:groups:groups"),
        ("Choir groups", "the director", "f:groups:director"),
        ("The boards", "a song's board (game)", "f:boards:board"),
        ("The daily challenge", "its sheet (game)", "f:game_daily:sheet"),
        ("Initials", "the picker (game)", "f:game_initials:picker"),
        ("A ghost", "racing your best (game)", "f:game_ghost:ghost"),
        ("No mic", "the help, iPhone", "f:mic_help:iphone-warm-light"),
    ],
    # Added after the first sheets: numbered on from 58 so the earlier numbers stay as they were.
    "More screens": [
        ("Then and now", "a finale, lesson 1's take beside today's", "o:then-and-now-pitch-phone{skin}"),
        ("Offline", "Plus says it needs a connection", "o:offline-plus-phone{skin}"),
        ("The studio", "the notice card with Undo", "f:studio_record:notice-undo"),
    ],
}
GUIDE_FLOWS = sorted({key.split(":")[1] for area in GUIDE.values() for _, _, key in area if key.startswith("f:")} | {"course_sharpen"})
# Flows that shoot for the guide but run on their own terms: Then and now on one course, offline per skin.
EXTRA = os.environ.get("EXTRA_ONLY") == "1"


def guide_shot(runs, journey, key, skin):
    """The run's file for a guide entry in one skin, or None."""
    if key.startswith("j:"):
        return find_shot(journey, key[2:], skin)
    if key.startswith("o:"):
        path = runs / "out" / (key[2:].format(skin="" if skin == "game" else "-serious") + ".png")
        return path if path.exists() else None
    _, flow, label = key.split(":")
    name = flow if skin == "game" else f"{flow}-serious"
    hits = sorted((runs / "flows").glob(f"{name}-phone-[0-9][0-9]-{label}.png"))
    return hits[0] if hits else None


def run_flows(runs, flows=None):
    """The guide's flows (phone, both skins), their shots kept in runs/flows and runs/out; `flows`
    runs only those, adding to what's there."""
    if flows is None:
        for sub in ("flows", "out"):
            if (runs / sub).exists():
                shutil.rmtree(runs / sub)
    env = {**os.environ, "FLOW_SIZES": "phone", "FLOW_SKINS": ",".join(SKINS), "PORT": str(PORT), "OUT": str(runs / "out")}
    # A flow that fails still leaves its shots; the sheet says "not reached" for what's missing.
    subprocess.run(["tool/flows.sh", *(flows or GUIDE_FLOWS)], cwd=WEB, env=env)
    (runs / "flows").mkdir(parents=True, exist_ok=True)
    shutil.copytree(WEB / "tests" / "flows" / "shots", runs / "flows", dirs_exist_ok=True)
    subprocess.run(["tool/flows.sh", "then_and_now"], cwd=WEB, env={**env, "THEN_AND_NOW_COURSES": "pitch"})
    for skin in SKINS:
        subprocess.run([sys.executable, "-I", "tests/flows/offline.py"], cwd=WEB, env={**env, "OFFLINE_SIZE": "phone", "OFFLINE_SKIN": skin})


def guide():
    out = REVIEW / "ui-guide"
    runs = out / "_runs"
    journey = REVIEW / "funnel" / "_runs"
    if EXTRA:  # only the flows behind "More screens", added to the last run
        run_flows(runs, ["studio_record"])
    elif os.environ.get("SKIP_RUN") != "1":
        run_journeys(journey)
        run_flows(runs)
    singles = out / "shots"
    if singles.exists():
        shutil.rmtree(singles)
    singles.mkdir(parents=True)
    n = 0
    sheets = {}
    rows = ["| No. | Area | Screen | State | Game | Serious |", "|---|---|---|---|---|---|"]
    for area, entries in GUIDE.items():
        cards = []
        for name, state, key in entries:
            n += 1
            card = {"number": str(n), "name": name, "state": state}
            for skin in SKINS:
                found = guide_shot(runs, journey, key, skin)
                if found:
                    card[skin] = singles / f"{n:02d}{'a' if skin == 'game' else 'b'}-{skin}.png"
                    shutil.copy(found, card[skin])
            cards.append(card)
            rows.append(f"| {n} | {area} | {name} | {state} | {f'{n}a' if card.get('game') else '-'} | {f'{n}b' if card.get('serious') else '-'} |")
        sheets[area] = cards
    # One sheet if it fits, else one an area.
    every = [c for cards in sheets.values() for c in cards]
    made = []
    note = "Phone size, Warm light. Each screen: a = game mode, b = serious. Say \"12b: ...\" to point at one."
    height = sheet(every, "Stackapella: the UI guide", note, out / "ui-guide.png")
    if height > MAX_HEIGHT:
        (out / "ui-guide.png").unlink()
        for i, (area, cards) in enumerate(sheets.items(), 1):
            slug = area.lower().replace(" ", "-")
            sheet(cards, f"Stackapella: the UI guide, {area}", note, out / f"ui-guide-{i}-{slug}.png")
            made.append(f"ui-guide-{i}-{slug}.png")
    else:
        made.append("ui-guide.png")
    missing = [f"{c['number']}{'a' if s == 'game' else 'b'}" for c in every for s in SKINS if not c.get(s)]
    (out / "index.md").write_text("# The UI guide\n\nMade by `python3 -I web/tool/ui_guide.py guide`: the screens from the flows and the first-week journey, phone size, Warm light. "
                                 "Sheets: " + ", ".join(made) + ". A screen marked (game) exists only in game mode.\n\n" + "\n".join(rows)
                                 + "\n\nNot reached in this run: " + (", ".join(missing) or "none") + ".\n")
    print(f"guide: {n} screens, sheets {made}, not reached: {missing}")


if __name__ == "__main__":
    what = sys.argv[1] if len(sys.argv) > 1 else "funnel"
    {"funnel": funnel, "guide": guide}[what]()
