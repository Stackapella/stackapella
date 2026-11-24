"""Ready by rehearsal's ready check (docs/rehearsal-ready-plan.md, "The ready check"): a tenor plan, "I know most of it"
opens Sing on the whole part with the others, and under the whole take's review the check says "Ready for <day>." (the
plan then ready) or "Nearly: go over phrase(s) ...", each phrase one tap into Coach on it. Never a score out of 100,
never "not ready". Phone size (the run records the whole song); both skins.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects  # noqa: E402
from record_mix import heard_while_recording  # noqa: E402

PLAN = "/rehearse/amazing-grace/tenor"
LEARNING = "(await import('/js/stores/learning.js'))"


def in_days(f, n):
    return f.js(f"(() => {{ const d = new Date(); d.setDate(d.getDate() + {n}); return `${{d.getFullYear()}}-${{String(d.getMonth() + 1).padStart(2, '0')}}-${{String(d.getDate()).padStart(2, '0')}}`; }})()")


def journey(f):
    if f.size == "desktop":
        return  # a phone first: the run records the whole song
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    clear_projects(f)
    f.run(f"const s = {LEARNING}; await s.loadLearning(); await s.setRehearsals([]);")
    f.mic_checked()
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run(f"""const a = {AUDIO}; await a.unlock(); await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")

    # A tenor plan three days out; I know most of it: Sing on the whole part with the others.
    date = in_days(f, 3)
    f.go(f"/?api=mock&scenario=&as=member&song=amazing-grace&part=tenor&rehearsal={date}", f"location.pathname === '{PLAN}' && document.querySelector('[data-rehearse-know]')")
    f.run("document.querySelector('[data-rehearse-know]').click()")
    f.wait("/\\/sing$/.test(location.pathname)", what="Sing")
    f.wait(f"{SESSION}.session.practice?.sing?.way === 'whole' && {SESSION}.session.parts.length > 3", what="the whole part's way")

    # The whole take: the loopback sings the tenor's guide back, the others muted (a clean, scored take).
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run(f"""const s = {SESSION}; for (const p of s.session.parts) if (p.templatePartKey !== 'tenor' && !s.session.hearing.off.includes(p.id)) s.toggleHearing(p.id);
        s.setRecordGuide(true);""")
    f.press("r")
    heard_while_recording(f, "the whole song", seconds=0.5)
    length = f.js(f"{SESSION}.session.contentLength")
    f.wait(f"!{SESSION}.session.recording && document.querySelector('[data-take-review]')", timeout=length + 40, what="the take's review")
    f.wait("document.querySelector('[data-ready-check]')", timeout=20, what="the ready check's report")
    text = f.js("document.querySelector('[data-ready-check]').innerText")
    assert "%" not in text and "not ready" not in text.lower(), text
    f.run("document.querySelector('[data-ready-check]').scrollIntoView({ block: 'center' })")
    f.no_sideways("the review with the ready check")
    f.shot("report")

    if f.js("document.querySelector('[data-ready-check]').hasAttribute('data-ready')"):
        # Ready: the plan says so (readyAt), and its page shows Ready for <day>.
        f.wait(f"(async () => {{ const {{ learning }} = {LEARNING}; return !!learning.progress.rehearsals.find((p) => p.partKey === 'tenor')?.readyAt; }})()", what="the plan marked ready")
        f.go(PLAN, "document.querySelector('[data-rehearse-ready]')")
        # Ready: the days go quiet (no Start), one line and Sing it through, which opens the whole part again.
        assert not f.js("!!document.querySelector('[data-rehearse-start]')"), "a ready plan still offers today's Start"
        assert f.js("!!document.querySelector('[data-rehearse-sing-through]')"), "no Sing it through on a ready plan"
        f.shot("plan-ready")
        f.run("document.querySelector('[data-rehearse-sing-through]').click()")
        f.wait("/\\/sing$/.test(location.pathname)", what="Sing on the whole part")
    else:
        # Nearly: a phrase to go over is one tap into Coach on it, part by part.
        first = f.js("+document.querySelector('[data-ready-phrase]').dataset.readyPhrase")
        f.run("document.querySelector('[data-ready-phrase]').click()")
        f.wait(f"(async () => {{ const {{ session }} = await import('/js/stores/session.js'); return session.practice?.sing?.way === 'parts' && session.practice?.phrase === {json.dumps(first)}; }})()", what="Coach on the phrase to go over")
        f.shot("coach")
    f.run(f"const s = {LEARNING}; await s.setRehearsals([]);")


if __name__ == "__main__":
    run("ready_check", journey)
