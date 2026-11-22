"""Hear yourself as a choir, /your-choir (site/your-choir.blade.php, js/public/your-choir.js, css/your-choir.css; Hillel,
2026-11-20: "something immersive to experience one real voice becoming 2 fake ones"). On a desktop and a phone:

- the page is its own document with its own sheet (and the Game mode switch's), no photos, no console errors;
- with the demo's voices (a consented, credited AI voice and the real guide, VoiceDemo), the stage: one real lane first,
  then the AI lanes, each saying once whether it's real or AI; nothing sounds until a press;
- the scenes follow the scroll (hero, real, split, four when all four parts are there, yours), and each opens the lanes
  it needs: her lane alone, then her lane folded with the tune and harmony open, then the four AI parts, then none;
- Play plays one timeline on the audio clock: her lane is heard first (lit, the AI ones waiting), the dock says who, the
  time moves; scrolling never stops or moves it; on the desktop, the AI song comes in after hers and her lane waits;
  Stop puts it back at the start;
- reduced motion: nothing pinned, every scene in the page's flow, and a press still lights the lanes in place;
- Spanish at /es/your-choir;
- without the demo's voices, the picture (the designed default until a singer's voice is licensed): one voice line
  becoming four AI parts from the song's notes, no player and no sound, the way in on the first screen (the server's own
  test covers each way the voices can be missing; this checks the page that results, wherever this server has none).

The page is behind own_choir: run it against a server whose database has own_choir on (and, for the stage, the demo's
voices: DemoSeeder's AI guides, VOICE_DEMO_AI_VOICE and VOICE_DEMO_REAL_GUIDE in its env). The voices' files are stubbed
here (a tone per part), so the media disk needn't hold them. Not a mock-chain flow:

    SITE=http://127.0.0.1:8412 python3 -I tests/flows/your_choir.py
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
ROOT = "document.querySelector('[data-yc]')"
READY = f"{ROOT}?.dataset.ready !== undefined"
# The voices' files, stubbed: any guide file the page fetches is a tone made here (the part named in its file name if it
# can be told, else 440 Hz), at the length its guide says; window.__voiceFail refuses them.
VOICES = """(() => {
    const tones = { soprano: 523, alto: 392, tenor: 330, bass: 196 };
    const wav = (hz, seconds) => { const RATE = 8000, FRAMES = Math.round(seconds * RATE); const b = new ArrayBuffer(44 + FRAMES * 2), v = new DataView(b);
        const text = (at, t) => [...t].forEach((c, i) => v.setUint8(at + i, c.charCodeAt(0)));
        text(0, 'RIFF'); v.setUint32(4, 36 + FRAMES * 2, true); text(8, 'WAVEfmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
        v.setUint32(24, RATE, true); v.setUint32(28, RATE * 2, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true); text(36, 'data'); v.setUint32(40, FRAMES * 2, true);
        for (let i = 0; i < FRAMES; i++) v.setInt16(44 + i * 2, Math.round(Math.sin(2 * Math.PI * hz * i / RATE) * 6000), true); return b; };
    const fetched = window.fetch;
    window.fetch = async function (url, ...rest) {
        const name = String(url?.url ?? url);
        const play = (() => { try { return JSON.parse(document.querySelector('[data-yc-data]')?.textContent ?? 'null'); } catch { return null; } })();
        const voice = play?.segments.flatMap((s) => s.voices).find((v) => v.url === name);
        if (!voice) return fetched.call(this, url, ...rest);
        if (window.__voiceFail) return new Response('', { status: 503 });
        const seconds = (Number(voice.frames) || 48000 * 30) / (Number(voice.sampleRate) || 48000);
        const bytes = wav(tones[voice.part] ?? 440, seconds);
        return new Response(bytes, { status: 200, headers: { 'Content-Type': 'audio/wav', 'Content-Length': String(bytes.byteLength) } });
    };
})();"""
STATE = f"""(() => {{ const r = {ROOT}; return {{
    playing: r.hasAttribute('data-playing'), loading: r.hasAttribute('data-loading'), view: r.dataset.view,
    live: [...r.querySelectorAll('.yc-ln[data-live]')].map((l) => l.dataset.seg + ':' + l.dataset.part),
    wait: [...r.querySelectorAll('.yc-ln[data-wait]')].map((l) => l.dataset.seg + ':' + l.dataset.part),
    lit: r.querySelectorAll('.yc-g[data-ring]').length, time: r.querySelector('[data-yc-time]')?.textContent ?? '',
    who: r.querySelector('[data-yc-who]')?.textContent ?? '', run: Number.parseFloat(r.querySelector('.yc-rail')?.style.getPropertyValue('--yc-p')) || 0 }}; }})()"""
# Which lanes a view opens (pinned): a lane folded away has no height.
OPEN = "[...document.querySelectorAll('.yc-ln')].filter((l) => l.getBoundingClientRect().height > 8 && getComputedStyle(l).opacity !== '0').map((l) => l.dataset.seg + ':' + l.dataset.part)"


def first_frame(f):
    """The server's page: its own sheet, no photos, the stage's lanes in order, each saying once what it is."""
    sheets = f.js("[...document.querySelectorAll('link[rel=stylesheet]')].map((l) => new URL(l.href).pathname)")
    assert sheets == ["/css/your-choir.css", "/css/game-toggle.css"], f"its own sheet and the switch's only: {sheets}"
    assert f.js("document.querySelectorAll('img').length") == 0, "no photos on this page"
    assert f.js("document.querySelectorAll('.yc-song').length") >= 1, "the library's songs are lanes of their tunes"
    lanes = f.js("[...document.querySelectorAll('.yc-ln')].map((l) => [l.dataset.seg, l.dataset.part, l.querySelectorAll('.yc-chip').length, l.querySelectorAll('.yc-g').length])")
    assert lanes[0][0] == "real" and all(lane[0] == "ai" for lane in lanes[1:]) and len(lanes) >= 3, f"one real lane, then the AI ones: {lanes}"
    assert all(lane[2] == 1 for lane in lanes), f"each lane says once whether it's real or AI: {lanes}"
    assert all(lane[3] > 0 for lane in lanes), f"each lane has its notes: {lanes}"
    chips = f.js("[...document.querySelectorAll('.yc-chip')].map((c) => c.textContent.trim())")
    assert len(set(chips[1:])) == 1 and chips[0] != chips[1], f"the real chip and the AI chip differ, and every AI lane's is the same: {chips}"
    time.sleep(1.2)
    assert not f.js(f"{ROOT}.hasAttribute('data-playing') || {ROOT}.hasAttribute('data-loading')"), "nothing sounds until a press"


def scenes(f):
    """The scenes follow the scroll, and each opens the lanes it needs."""
    views = f.js("[...document.querySelectorAll('[data-yc-stage] [data-s]')].map((s) => s.dataset.s)")
    four = "four" in views
    assert views[:3] == ["hero", "real", "split"] and views[-1] == "yours", f"the stage's scenes: {views}"
    assert four == (f.js("document.querySelectorAll('.yc-ln.yc-ai').length") >= 4), "the whole choir's scene only with four AI parts"
    assert f.js("getComputedStyle(document.querySelector('.yc-stage')).position") == "sticky", "the stage stays while its scenes pass"
    for i, view in enumerate(views):
        f.run(f"window.scrollTo(0, {i} * innerHeight)")
        f.wait(f"{ROOT}.dataset.view === '{view}'", timeout=5, what=f"the {view} scene")
        time.sleep(1.1)
        shown = f.js(f"getComputedStyle(document.querySelector('[data-s={view}]')).opacity")
        assert shown == "1", f"the {view} scene shows: {shown}"
        opened = f.js(OPEN)
        if view == "real" or (view == "hero" and f.size == "desktop"):
            assert opened == ["real:soprano"], f"{view}: her lane alone: {opened}"
        elif view == "split":
            assert opened == ["real:soprano", "ai:soprano", "ai:alto"], f"split: her lane folded, the tune and harmony open: {opened}"
        elif view == "four":
            assert opened == ["ai:soprano", "ai:alto", "ai:tenor", "ai:bass"], f"four: the four AI parts: {opened}"
        elif view == "yours":
            assert f.js("getComputedStyle(document.querySelector('.yc-world')).opacity") == "0", "now yours: the lanes give way"
        f.no_sideways(f"the {view} scene")
    f.run("window.scrollTo(0, 0)")
    f.wait(f"{ROOT}.dataset.view === 'hero'", timeout=5, what="back at the top")


def play(f, whole):
    """One press plays the timeline: her lane first, the dock says who, scrolling never touches it; Stop goes back."""
    data = f.js("JSON.parse(document.querySelector('[data-yc-data]').textContent)")
    f.run("document.querySelector('[data-yc-go]').click()")
    f.wait(f"{ROOT}.hasAttribute('data-playing')", timeout=40, what="the voices playing")
    time.sleep(1.5)
    state = f.js(STATE)
    assert state["live"] == ["real:soprano"], f"her lane is heard first, alone: {state}"
    assert all(w.startswith("ai:") for w in state["wait"]) and state["wait"], f"the AI lanes wait while she sings: {state}"
    assert state["who"] and state["time"].startswith("0:0") and state["time"] != "0:00 / " + state["time"].split(" / ")[1], f"the dock says who and the time moves: {state}"
    assert f.js("document.querySelector('.yc-dock button.yc-disc').getAttribute('aria-label')") == f.js("document.querySelector('.yc-dock button.yc-disc').dataset.labelStop"), "the dock's control says Stop"
    before = state["run"]
    f.run("window.scrollTo(0, 2 * innerHeight)")
    time.sleep(1.5)
    after = f.js(STATE)
    assert after["playing"] and after["run"] > before, f"scrolling never stops or moves the song: {before} -> {after}"
    if whole:
        # The AI song comes in after hers: her lane waits, the tune and harmony are heard.
        start = data["segments"][1]["start"]
        f.wait(f"Number.parseFloat({ROOT}.querySelector('.yc-rail').style.getPropertyValue('--yc-p')) * {data['seconds']} > {start + 2}", timeout=start + 30, what="the AI song")
        ai = f.js(STATE)
        assert "real:soprano" in ai["wait"] and {"ai:soprano", "ai:alto"} <= set(ai["live"]), f"the AI song: her lane waits, the tune and harmony sing: {ai}"
        assert ai["who"] != state["who"], f"the dock says the AI sings now: {ai['who']}"
    # The dock's own disc (the button itself, not the hero's): Stop, then Play again, then Stop.
    disc = "document.querySelector('.yc-dock button.yc-disc')"
    f.run(f"{disc}.click()")
    f.wait(f"!{ROOT}.hasAttribute('data-playing')", timeout=5, what="Stop")
    stopped = f.js(STATE)
    assert stopped["time"].startswith("0:00") and stopped["live"] == [] and stopped["wait"] == [], f"Stop puts it back at the start: {stopped}"
    f.run(f"{disc}.click()")
    f.wait(f"{ROOT}.hasAttribute('data-playing')", timeout=20, what="the dock's Play")
    f.run(f"{disc}.click()")
    f.wait(f"!{ROOT}.hasAttribute('data-playing')", timeout=5, what="the dock's Stop")
    f.run("window.scrollTo(0, 0)")


def still(size):
    """Reduced motion: nothing pinned, every scene in the page's flow, and a press still lights the lanes in place."""
    f = flow.Flow(SITE, size, "your_choir_still", skin="serious")
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    try:
        f.go("/your-choir", READY)
        if not f.js(f"{ROOT}.hasAttribute('data-stage')"):
            return
        if f.js(f"{ROOT}.hasAttribute('data-picture')"):
            # The picture, still: every scene in the page's flow, the lanes one picture, nothing animating.
            assert f.js("getComputedStyle(document.querySelector('.yc-stage')).position") != "sticky", "nothing is pinned"
            assert len(f.js(OPEN)) == 5, f"the voice line and the four parts, in one picture: {f.js(OPEN)}"
            assert f.js("document.getAnimations().filter((a) => a.playState === 'running').length") == 0, "nothing animates"
            return
        assert f.js("getComputedStyle(document.querySelector('.yc-stage')).position") != "sticky", "nothing is pinned"
        tops = f.js("[...document.querySelectorAll('[data-yc-stage] [data-s]')].map((s) => getComputedStyle(s).opacity === '1' ? Math.round(s.getBoundingClientRect().top + scrollY) : -1)")
        assert -1 not in tops and len(set(tops)) == len(tops), f"every scene shows, one after another: {tops}"
        assert len(f.js(OPEN)) == f.js("document.querySelectorAll('.yc-ln').length"), "the lanes are one picture, all of them"
        f.no_sideways("the still page")
        f.run("document.querySelector('[data-yc-go]').click()")
        f.wait(f"{ROOT}.hasAttribute('data-playing')", timeout=40, what="playing, still")
        time.sleep(1.5)
        state = f.js(STATE)
        assert state["live"] == ["real:soprano"] and state["lit"] >= 1, f"her lane lights in place: {state}"
        f.run("document.querySelector('.yc-dock button.yc-disc').click()")
    finally:
        f.close()


def without_voices(f):
    """No demo voices on this server (Hillel, 2026-11-21: "lets support starting track B without A ... so there aren't
    samples"): the picture, no player. One voice line ("Your voice", no AI chip) becomes four parts, each chipped AI,
    with the song's own notes; no sound, no singer's name, no recording; the way in ("Try it with your voice") is on the
    first screen; scrolling opens the four parts, then "now yours"."""
    assert f.js(f"{ROOT}.hasAttribute('data-picture')"), "the page is the picture"
    assert f.js("document.querySelectorAll('[data-yc-play], [data-yc-data], [data-yc-go], .yc-dock, audio').length") == 0, "no player, no sound"
    lanes = f.js("[...document.querySelectorAll('.yc-ln')].map((l) => [l.dataset.seg, l.dataset.part, l.querySelector('.yc-chip')?.textContent.trim() ?? '', l.querySelectorAll('.yc-g').length])")
    assert [lane[0] for lane in lanes] == ["you", "ai", "ai", "ai", "ai"], f"one voice line, then four AI parts: {lanes}"
    assert [lane[1] for lane in lanes[1:]] == ["soprano", "alto", "tenor", "bass"], f"soprano to bass: {lanes}"
    assert all(lane[3] > 0 for lane in lanes), f"every lane has its notes: {lanes}"
    assert len({lane[2] for lane in lanes[1:]}) == 1 and lanes[0][2] != lanes[1][2], f"each AI lane says AI, the voice line doesn't: {lanes}"
    try_box = f.js("(() => { const a = document.querySelector('[data-s=hero] [data-your-choir-try]'); const r = a.getBoundingClientRect(); return [r.top, r.bottom, innerHeight]; })()")
    assert try_box[1] <= try_box[2] and try_box[0] >= 0, f"the way in is on the first screen: {try_box}"
    assert f.js("document.querySelectorAll('img').length") == 0, "no photos on this page"
    views = f.js("[...document.querySelectorAll('[data-yc-stage] [data-s]')].map((s) => s.dataset.s)")
    assert views == ["hero", "four", "lead", "yours"], f"the picture's scenes: {views}"
    # The first screen: the voice line becomes the four parts once, on its own, then rests (nothing animates for ever).
    time.sleep(3.5)
    opened = f.js(OPEN)
    assert opened == ["you:soprano", "ai:soprano", "ai:alto", "ai:tenor", "ai:bass"], f"the first screen: one voice line, four parts: {opened}"
    assert f.js("document.getAnimations().filter((a) => a.playState === 'running' && /yc-lane/.test(a.animationName ?? '')).length") == 0, "the opening has finished"
    f.run("window.scrollTo(0, innerHeight)")
    f.wait(f"{ROOT}.dataset.view === 'four'", timeout=5, what="the four parts scene")
    time.sleep(1.2)
    assert f.js("getComputedStyle(document.querySelector('[data-s=four]')).opacity") == "1", "the four parts' words show"
    assert f.js(OPEN) == ["you:soprano", "ai:soprano", "ai:alto", "ai:tenor", "ai:bass"], f"four: the voice line folded, the four parts open: {f.js(OPEN)}"
    # You + choir: the voice line back as the lead, named as the singer's own recording, the AI alto, tenor and bass
    # around it, the AI soprano given way.
    f.run("window.scrollTo(0, 2 * innerHeight)")
    f.wait(f"{ROOT}.dataset.view === 'lead'", timeout=5, what="you with the choir")
    time.sleep(1.2)
    assert f.js(OPEN) == ["you:soprano", "ai:alto", "ai:tenor", "ai:bass"], f"lead: your recording and the AI parts around it: {f.js(OPEN)}"
    shown = f.js("[...document.querySelectorAll('.yc-you :is(.yc-you-voice, .yc-you-lead)')].filter((b) => getComputedStyle(b).display !== 'none').map((b) => b.className)")
    assert shown == ["yc-you-lead"], f"lead: the voice line is named as your recording: {shown}"
    f.run("window.scrollTo(0, 3 * innerHeight)")
    f.wait(f"{ROOT}.dataset.view === 'yours'", timeout=5, what="now yours")
    f.no_sideways("the picture")
    # Better with friends (own_choir_together on in this server's database): four parts, two in a voice like yours and
    # two in a voice like your friend's, each saying whose with the small AI chip; no button of its own.
    if f.js("!!document.querySelector('.yc-friends')"):
        chips = f.js("[...document.querySelectorAll('.yc-friends .yc-chip')].map((c) => c.textContent.trim())")
        assert len(chips) == 4 and len(set(chips)) == 1, f"friends: every part the same small AI chip: {chips}"
        whose = f.js("[...document.querySelectorAll('.yc-friends .yc-song small')].map((c) => c.textContent.trim())")
        assert len(whose) == 4 and len(set(whose)) == 2, f"friends: each part says whose voice: {whose}"
        assert f.js("document.querySelectorAll('.yc-friends [data-your-choir-try], .yc-friends a, .yc-friends button').length") == 0, "friends: no second try block"
        f.run("document.querySelector('.yc-friends').scrollIntoView()")
        time.sleep(0.6)
        f.shot("friends")
        f.no_sideways("with friends")
    f.run("window.scrollTo(0, 0)")


def main():
    failures = []
    stage = None
    for size in ("desktop", "phone"):
        f = flow.Flow(SITE, size, "your_choir", skin="serious")
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
        try:
            f.go("/your-choir", "document.readyState !== 'loading'")
            assert f.js("!!document.querySelector('[data-yc]')"), "the page is there (own_choir on in this server's database?)"
            f.wait(READY, timeout=20, what="the page's script")
            stage = f.js("!!document.querySelector('[data-yc-data]')")
            if stage:
                first_frame(f)
                scenes(f)
                play(f, whole=size == "desktop")
            else:
                without_voices(f)
            f.go("/es/your-choir", READY)
            assert f.js("document.documentElement.lang") == "es", "Spanish at /es/your-choir"
            f.no_sideways("the Spanish page")
        except AssertionError as error:
            failures.append(f"{size}: {error}")
        finally:
            try:
                f.close()
            except AssertionError as error:
                failures.append(str(error))
        try:
            still(size)
        except AssertionError as error:
            failures.append(f"{size}, still: {error}")
    if stage is False and not failures:
        print("note your_choir: this server has no demo voices, so the picture was checked (no player, no sound), not the audio stage")
    print("FAIL your_choir\n     " + "\n     ".join(failures) if failures else "ok   your_choir")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
