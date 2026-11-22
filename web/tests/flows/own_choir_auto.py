"""From /your-choir (Hillel: "if you're recording a song because you started on /your-choir then we should auto start the
choir processing and show it working while you review the results"): the take is sent by itself once saved (no Make my
choir), the take review shows each part's state while it's made, then Listen to your choir opens the song's choir page.
Elsewhere the take waits for Make my choir, as before.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects  # noqa: E402

FLAGS = "sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir'); sessionStorage.setItem('stackapella.mockChoirMaking', '1');"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.mic_checked()
    f.run(FLAGS)
    clear_projects(f)
    # As the site's "Try it with Amazing Grace" (?choir=1) leaves it for this tab.
    f.run("sessionStorage.setItem('stackapella.choirFromSite', 'amazing-grace')")
    f.go("/t/amazing-grace/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-agree]') || document.querySelector('[data-choir-start]')")
    if f.js("!!document.querySelector('[data-choir-agree]')"):
        f.run("document.querySelector('[data-choir-agree]').click()")
    f.wait("document.querySelector('[data-choir-start]')", what="Start singing")
    # A free singer's choir is the song they sing: Amazing Grace's tune, for Amazing Grace.
    f.wait("document.querySelector('[data-clip-song=\"amazing-grace\"]')", what="the tunes")
    f.run("document.querySelector('[data-clip-song=\"amazing-grace\"]').click()")
    f.run("document.querySelector('[data-headphones-pick] [role=radio]:nth-child(1)').click()")
    f.run("document.querySelector('[data-choir-start]').click()")
    f.wait("document.querySelector('[data-first-note-usual]')", what="the first-note step")
    f.run("document.querySelector('[data-first-note-usual]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing on the take's project")
    f.wait("(async () => (await import('/js/stores/session.js')).session.project?.choir?.auto === true)()", what="the take marked as from the site")
    f.run(f"const a = {AUDIO}; await a.unlock(); a.engine.setLoopback(true); sessionStorage.setItem('stackapella.headphones', '1');")
    # As the count-in flow does: the mic open and its timing known, so Record records (no timing check).
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    f.wait(f"{SESSION}.session.guidesVersion > 0", timeout=30, what="the guide")
    f.wait(f"{SESSION}.session.practice?.focus", what="the tune in focus")
    f.press("r")  # Sing's own Record: the take goes into the tune's slot
    f.wait(f"{AUDIO}.engine.playing?.recording && {AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 16", timeout=40, what="16 s sung")
    f.press("Escape")
    # A loopback take may be quiet: the warning first (a take the worker would refuse isn't sent without a word).
    f.wait("document.querySelector('[data-choir-sent]') || document.querySelector('[data-choir-check]')", timeout=30, what="sent, or the level warning")
    if f.js("!!document.querySelector('[data-choir-check]')"):
        f.shot("take-warning")
        f.run("document.querySelector('[data-choir-force]').click()")
    # Sent by itself: no Make my choir; the parts' states on the take review.
    f.wait("document.querySelector('[data-choir-sent]')", timeout=30, what="the choir being made, on the take review")
    assert not f.js("document.querySelector('[data-choir-make]')"), "Make my choir offered for a take sent by itself"
    assert f.js("!!globalThis.mockChoirClip?.sha256"), "the take wasn't sent"
    f.run("(await import('/js/own-choir.js')).loadOwnChoir({ force: true })")
    f.wait("document.querySelectorAll('[data-choir-sent] [data-choir-part]').length > 0", what="each part's state")
    f.no_sideways("the take review with the choir being made")
    f.run("document.querySelector('[data-choir-sent]').scrollIntoView({ block: 'center' })")
    f.shot("auto-making")
    # Made: Listen to your choir, to its page.
    f.run("sessionStorage.removeItem('stackapella.mockChoirMaking'); await (await import('/js/own-choir.js')).loadOwnChoir({ force: true })")
    f.wait("document.querySelector('[data-choir-listen]')", timeout=20, what="Listen to your choir")
    f.run("document.querySelector('[data-choir-listen]').scrollIntoView({ block: 'center' })")
    f.shot("auto-ready")
    f.run("document.querySelector('[data-choir-listen]').click()")
    f.wait("location.pathname === '/t/amazing-grace/choir'", what="the song's choir page")
    clear_projects(f)


run("own_choir_auto", journey)
