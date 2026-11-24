"""Hear yourself as a choir, the AI landing (/ai-harmony-singing, /es/armonias-con-ia; once /your-choir, which 301s here; site/your-choir.blade.php, js/public/your-choir.js, css/your-choir.css; Hillel,
2026-11-20: "something immersive to experience one real voice becoming 2 fake ones"). On a desktop and a phone:

- the page is its own document with its own sheet (and the Game mode switch's), no photos, no console errors;
- with the demo's voices (a consented, credited AI voice and the real guide, VoiceDemo), the stage: one real lane first,
  then the AI lanes, each saying once whether it's real or AI; nothing sounds until a press;
- the page scrolls like a page (Hillel, 2026-11-23: "when you scroll, you just scroll like a normal page"): nothing
  pinned; the lanes once, right under the first screen's words; then each scene (real, split, four when all four parts
  are there, yours) a section with its own picture of its lanes (her lane; the tune and harmony; the four parts), each
  rising into place as it comes into view;
- Play plays one timeline on the audio clock: her lane is heard first (lit, the AI ones waiting), the time moves;
  scrolling never stops or moves it, and with the lanes scrolled away the dock comes up and says who; on the desktop,
  the AI song comes in after hers and her lane waits; Stop puts it back at the start and the dock goes;
- reduced motion: nothing rises or slides, every scene in the page's flow, and a press still lights the lanes in place;
- Spanish at its own address (/es/armonias-con-ia); /your-choir moves here for good, its ref kept; the visit notes on the device that it came
  for AI (sa_for=ai);
- without the demo's voices, the picture (the designed default until a singer's voice is licensed): one voice line
  becoming four AI parts from the song's notes, no player and no sound, the way in on the first screen, then the four
  parts' scene and "you with the choir" each with its picture (the server's own
  test covers each way the voices can be missing; this checks the page that results, wherever this server has none).

The page is behind own_choir: run it against a server whose database has own_choir on (and, for the stage, the demo's
voices: DemoSeeder's AI guides, VOICE_DEMO_AI_VOICE and VOICE_DEMO_REAL_GUIDE in its env). The voices' files are stubbed
here (a tone per part), so the media disk needn't hold them. Not a mock-chain flow:

    SITE=http://127.0.0.1:8412 python3 -I tests/flows/your_choir.py
"""
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")


def moved_to(old):
    """Where the server's 301 from `old` (the page's first address) leads: the page's own address in that language
    (YourChoirStage::SLUGS, named once on the server), with no second copy of it here."""
    class Stay(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args, **kwargs):
            return None
    try:
        urllib.request.build_opener(Stay).open(SITE + old)
    except urllib.error.HTTPError as error:
        if error.code == 301:
            return urllib.parse.urlsplit(error.headers["Location"]).path
        raise
    raise AssertionError(f"{old} should move to the page for good (301)")


PAGE = moved_to("/your-choir")
PAGE_ES = moved_to("/es/your-choir")
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
# Which of the lanes under the first screen show: a lane folded away has no height.
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


def pictures(f):
    """Each scene's picture: its rows' names and chips, in order (site/_yc-mini)."""
    return f.js("Object.fromEntries([...document.querySelectorAll('[data-yc-stage] [data-s]')].map((s) => [s.dataset.s, [...s.querySelectorAll('.yc-mini .yc-song')].map((r) => [r.querySelector('b').firstChild.nextSibling?.textContent?.trim() ?? r.querySelector('b').textContent.trim(), r.querySelector('.yc-chip')?.textContent.trim() ?? '', r.querySelectorAll('.gem-note').length])]))")


def rises(f, view):
    """A scene scrolled into view rises into place and shows (motion allowed), with no sideways scroll."""
    f.run(f"document.querySelector('[data-s={view}]').scrollIntoView({{ block: 'center' }})")
    f.wait(f"document.querySelector('[data-s={view}]').hasAttribute('data-shown')", timeout=5, what=f"the {view} scene in view")
    time.sleep(1.1)
    shown = f.js(f"[...document.querySelector('[data-s={view}]').children].every((c) => getComputedStyle(c).opacity === '1')")
    assert shown, f"the {view} scene shows"
    f.no_sideways(f"the {view} scene")


def scenes(f):
    """A page that scrolls: the lanes once under the first screen, then each scene a section with its own picture."""
    views = f.js("[...document.querySelectorAll('[data-yc-stage] [data-s]')].map((s) => s.dataset.s)")
    four = "four" in views
    assert views[:3] == ["hero", "real", "split"] and views[-1] == "yours", f"the stage's scenes: {views}"
    assert four == (f.js("document.querySelectorAll('.yc-ln.yc-ai').length") >= 4), "the whole choir's scene only with four AI parts"
    assert f.js("[...document.querySelectorAll('.yc-stage, .yc-stage *')].every((el) => !/sticky|fixed/.test(getComputedStyle(el).position))"), "nothing on the stage is pinned"
    tops = f.js("['[data-s=hero]', '.yc-world', '[data-s=real]'].map((q) => Math.round(document.querySelector(q).getBoundingClientRect().top + scrollY))")
    assert tops == sorted(tops), f"the lanes come right under the first screen, before the scenes: {tops}"
    assert len(f.js(OPEN)) == f.js("document.querySelectorAll('.yc-ln').length"), "every lane shows, once"
    pics = pictures(f)
    real_chip = f.js("document.querySelector('.yc-ln.yc-real .yc-chip').textContent.trim()")
    ai_chip = f.js("document.querySelector('.yc-ln.yc-ai .yc-chip').textContent.trim()")
    assert [r[1] for r in pics["real"]] == [real_chip] and pics["real"][0][2] > 0, f"real: her lane, chipped real: {pics['real']}"
    assert [r[1] for r in pics["split"]] == [ai_chip, ai_chip] and all(r[2] > 0 for r in pics["split"]), f"split: the tune and harmony, AI: {pics['split']}"
    if four:
        assert len(pics["four"]) == 4 and {r[1] for r in pics["four"]} == {ai_chip}, f"four: the four AI parts: {pics['four']}"
    assert pics["hero"] == [] and pics["yours"] == [], "the first screen and now yours have no picture of their own"
    for view in views[1:]:
        rises(f, view)
    f.run("window.scrollTo(0, 0)")


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
    assert f.js("getComputedStyle(document.querySelector('.yc-dock')).visibility") == "hidden", "with the lanes in view, no dock"
    before = state["run"]
    f.run("document.querySelector('[data-s=yours]').scrollIntoView()")
    time.sleep(1.5)
    after = f.js(STATE)
    assert after["playing"] and after["run"] > before, f"scrolling never stops or moves the song: {before} -> {after}"
    assert f.js("getComputedStyle(document.querySelector('.yc-dock')).visibility") == "visible", "with the lanes scrolled away, the dock comes up"
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
        f.go(PAGE, READY)
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
    first screen; then the four parts' scene and you with the choir, each with its picture, then "now yours"."""
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
    # Under the first screen's words, as the lanes come into view: the voice line becomes the four parts once, on its own,
    # then rests (nothing animates for ever).
    f.run("document.querySelector('.yc-world').scrollIntoView({ block: 'center' })")
    time.sleep(3.5)
    opened = f.js(OPEN)
    assert opened == ["you:soprano", "ai:soprano", "ai:alto", "ai:tenor", "ai:bass"], f"the first screen: one voice line, four parts: {opened}"
    assert f.js("document.getAnimations().filter((a) => a.playState === 'running' && /yc-lane/.test(a.animationName ?? '')).length") == 0, "the opening has finished"
    # Then the four parts' scene and you with the choir, each with its picture: the four AI parts; your recording leading
    # the AI alto, tenor and bass (the AI soprano gives way to the tune you sing).
    pics = pictures(f)
    ai_chip = lanes[1][2]
    assert [r[1] for r in pics["four"]] == [ai_chip] * 4 and all(r[2] > 0 for r in pics["four"]), f"four: the four AI parts: {pics['four']}"
    lead = pics["lead"]
    assert len(lead) == 4 and lead[0][1] not in ("", ai_chip) and [r[1] for r in lead[1:]] == [ai_chip] * 3, f"lead: your recording, then three AI parts: {lead}"
    for view in ("four", "lead", "yours"):
        rises(f, view)
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
            f.go(PAGE, "document.readyState !== 'loading'")
            assert f.js("!!document.querySelector('[data-yc]')"), "the page is there (own_choir on in this server's database?)"
            f.wait(READY, timeout=20, what="the page's script")
            # The visit leaves the device its note that it came for AI (the app pre-selects the sign-up's answer by it).
            assert "sa_for=ai" in f.js("document.cookie").split("; "), f"came for AI: {f.js('document.cookie')}"
            stage = f.js("!!document.querySelector('[data-yc-data]')")
            if stage:
                first_frame(f)
                scenes(f)
                play(f, whole=size == "desktop")
            else:
                without_voices(f)
            # Its first address moved here for good, in each language, a shared link's ref kept.
            f.go("/your-choir?ref=choir-share", READY)
            assert f.js("location.pathname + location.search") == PAGE + "?ref=choir-share", f"the old address moved: {f.js('location.href')}"
            f.go(PAGE_ES, READY)
            assert f.js("document.documentElement.lang") == "es", f"Spanish at {PAGE_ES}"
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
