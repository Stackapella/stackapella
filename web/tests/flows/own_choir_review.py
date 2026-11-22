"""The whole choir journey on a phone, shot by shot, for a UX review (the coordinator, 2026-11-23): the intro with its two
choices, the first note, the take's count-in, the take review while the choir is made, the ready page (Listen to), and
Choir settings. Asserts only that each screen is there; the shots are the point (copied to shots-review).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects  # noqa: E402

FLAGS = "sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir'); sessionStorage.setItem('stackapella.mockChoirMaking', '1');"


def top(f):
    f.run("window.scrollTo(0, 0)")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.mic_checked()
    f.run(FLAGS)
    clear_projects(f)
    f.run("sessionStorage.setItem('stackapella.choirFromSite', 'amazing-grace')")
    f.go("/t/amazing-grace/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-agree]') || document.querySelector('[data-choir-start]')")
    if f.js("!!document.querySelector('[data-choir-agree]')"):
        top(f)
        f.shot("01-terms")
        f.run("document.querySelector('[data-choir-agree]').click()")
    f.wait("document.querySelector('[data-clip-song]')", what="the intro")
    top(f)
    f.shot("02-intro")
    f.run("document.querySelector('[data-clip-song=\"amazing-grace\"]').click()")
    f.run("document.querySelector('[data-headphones-pick] [role=radio]:nth-child(1)').click()")
    f.run("document.querySelector('[data-headphones-pick]').scrollIntoView({ block: 'center' })")
    f.shot("03-intro-choices")
    f.run("document.querySelector('[data-choir-start]').scrollIntoView({ block: 'center' })")
    f.shot("04-intro-start")
    f.run("document.querySelector('[data-choir-start]').click()")
    f.wait("document.querySelector('[data-first-note]')", what="the first note")
    top(f)
    f.shot("05-first-note")
    f.run("document.querySelector('[data-first-note-usual]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing")
    f.wait(f"{SESSION}.session.guidesVersion > 0", timeout=30, what="the guide")
    f.shot("06-sing")
    f.run(f"const a = {AUDIO}; await a.unlock(); a.engine.setLoopback(true); sessionStorage.setItem('stackapella.headphones', '1');")
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    f.wait(f"{SESSION}.session.practice?.focus", what="the tune in focus")
    f.press("r")
    f.wait("document.querySelector('[data-count-big] span')", timeout=20, what="the count-in")
    f.shot("07-count-in")
    f.wait(f"{AUDIO}.engine.playing?.recording && {AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 4", timeout=30, what="singing")
    f.shot("08-recording")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 16", timeout=40, what="16 s sung")
    f.press("Escape")
    f.wait("document.querySelector('[data-choir-sent]') || document.querySelector('[data-choir-check]')", timeout=30, what="sent, or the level warning")
    if f.js("!!document.querySelector('[data-choir-check]')"):
        f.run("document.querySelector('[data-choir-check]').scrollIntoView({ block: 'center' })")
        f.shot("09-take-warning")
        f.run("document.querySelector('[data-choir-force]').click()")
    f.wait("document.querySelector('[data-choir-sent]')", timeout=30, what="the choir being made")
    f.run("(await import('/js/own-choir.js')).loadOwnChoir({ force: true })")
    f.wait("document.querySelectorAll('[data-choir-sent] [data-choir-part]').length > 0", what="each part's state")
    f.run("document.querySelector('[data-choir-sent]').scrollIntoView({ block: 'center' })")
    f.shot("10-take-review-making")
    f.run("sessionStorage.removeItem('stackapella.mockChoirMaking'); await (await import('/js/own-choir.js')).loadOwnChoir({ force: true })")
    f.wait("document.querySelector('[data-choir-listen]')", timeout=20, what="Listen to your choir")
    f.run("document.querySelector('[data-choir-listen]').scrollIntoView({ block: 'center' })")
    f.shot("11-take-review-ready")
    f.run("document.querySelector('[data-choir-listen]').click()")
    f.wait("document.querySelector('[data-listen-to]') || document.querySelector('[data-choir-play]')", what="the ready page")
    top(f)
    f.shot("12-ready")
    f.run("sessionStorage.setItem('stackapella.mockChoirLead', '1'); await (await import('/js/own-choir.js')).loadOwnChoir({ force: true })")
    f.wait("document.querySelectorAll('[data-listen-to] [role=radio]').length === 3", what="You + choir")
    top(f)
    f.shot("13-ready-listen-to")
    f.run("document.querySelector('[data-choir-settings]').scrollIntoView({ block: 'start' })")
    f.shot("14-settings")
    f.run("document.querySelector('[data-remake]').scrollIntoView({ block: 'end' })")
    f.shot("15-settings-footer")
    f.run("sessionStorage.removeItem('stackapella.mockChoirLead'); sessionStorage.removeItem('stackapella.choirFromSite');")
    clear_projects(f)


run("own_choir_review", journey)
