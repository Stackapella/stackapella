"""AI optional's card (docs/ai-optional-plan.md, revised 2026-11-23): a guest on their second day records the first part
of a song the choir can make, and under the take's review sees "Hear the other parts in your voice?" with two equal
buttons. Not for me turns AI off on the device (the card gone, the AI guides with it). With the choice cleared, the next
take shows it again, and Try it turns AI on and opens Hear yourself as a choir for the song. Never on the first day.
Phone size, where most singers are (the whole run records twice); both skins.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song  # noqa: E402
from record_mix import heard_while_recording  # noqa: E402

FLAGS = "sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockAiOptional', '1'); sessionStorage.setItem('stackapella.mockAiVoices', '1');"
FRESH = "localStorage.removeItem('stackapella.ai'); localStorage.removeItem('stackapella.aiCard'); localStorage.removeItem('stackapella.asks');"
YESTERDAY = """const f = JSON.parse(localStorage.getItem('stackapella.funnel') || '{}'); const d = new Date(Date.now() - 86400000);
    f.arrived = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    localStorage.setItem('stackapella.funnel', JSON.stringify(f));"""
TODAY = YESTERDAY.replace("Date.now() - 86400000", "Date.now()")
GUEST = "?api=mock&scenario=&as=signedOut"


def record_tenor(f, song):
    """One whole tenor take, scored (the loopback sings the synthesized guide back cleanly; Be the voice is off in the mock), to its review."""
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'tenor')); s.savePractice();""")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run(f"""const s = {SESSION}; for (const p of s.session.parts) if (p.templatePartKey !== 'tenor' && !s.session.hearing.off.includes(p.id)) s.toggleHearing(p.id);
        s.setRecordGuide(true);""")
    f.press("r")
    heard_while_recording(f, "the whole song", seconds=0.5)
    length = f.js(f"{SESSION}.session.contentLength")
    f.wait(f"!{SESSION}.session.recording && document.querySelector('[data-take-review]')", timeout=length + 40, what="the take's review")


def journey(f):
    if f.size == "desktop":
        return  # a phone first: the run records the whole song twice
    f.go(f"/{GUEST}", "document.querySelector('h1')")
    f.run(FLAGS + FRESH)
    f.mic_checked()
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run(f"""const a = {AUDIO}; await a.unlock(); await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")

    # The first day: no card under the take.
    f.run(TODAY)
    clear_projects(f)
    song = new_song(f)
    record_tenor(f, song)
    f.wait("document.querySelector('[data-take-review] footer, [data-take-review] .footer-bar')", what="the review's footer")
    assert not f.js("!!document.querySelector('[data-ai-card]')"), "the card showed on the device's first day"

    # The second day, the song's first part: the card, its buttons equal.
    f.run(YESTERDAY + FRESH)
    clear_projects(f)
    song = new_song(f)
    record_tenor(f, song)
    f.wait("document.querySelector('[data-ai-card]')", timeout=20, what="the card")
    classes = f.js("[...document.querySelectorAll('[data-ai-card-not], [data-ai-card-try]')].map((b) => b.className)")
    assert len(classes) == 2 and classes[0] == classes[1], classes
    assert "Amazing Grace" in f.js("document.querySelector('[data-ai-card]').innerText"), "the card doesn't name the song"
    f.run("document.querySelector('[data-ai-card]').scrollIntoView({ block: 'center' })")
    f.no_sideways("the review with the card")
    f.shot("card")

    # Not for me: AI off on this device, the card gone.
    f.run("document.querySelector('[data-ai-card-not]').click()")
    f.wait("!document.querySelector('[data-ai-card]')", what="the card gone")
    assert f.js("localStorage.getItem('stackapella.ai')") == "off"
    assert f.js("(async () => (await import('/js/ai-choice.js')).aiOn())()") is False

    # The choice cleared, a new song's first part: Try it turns AI on and opens the song's choir page.
    f.run(FRESH)
    clear_projects(f)
    song = new_song(f)
    record_tenor(f, song)
    f.wait("document.querySelector('[data-ai-card]')", timeout=20, what="the card again")
    f.run("document.querySelector('[data-ai-card-try]').click()")
    f.wait("/^\\/t\\/[\\w-]+\\/choir$/.test(location.pathname)", what="Hear yourself as a choir's page")
    assert f.js("localStorage.getItem('stackapella.ai')") == "on"
    f.shot("try-it")

    f.run(FRESH + " ['mockAiOptional', 'mockAiVoices', 'mockOwnChoir'].forEach((k) => sessionStorage.removeItem('stackapella.' + k));")


if __name__ == "__main__":
    run("ai_card", journey)
