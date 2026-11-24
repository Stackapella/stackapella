"""Ready by rehearsal (docs/rehearsal-ready-plan.md; Hillel's approved build plan, brief A): a part page's "Pick the
day" (?rehearsal=YYYY-MM-DD&part=) makes the part's plan, its phrases spread over the days left and the last day the
whole part with the others; Start opens Sing's Coach on today's first phrase; a day is done by Coach's own mark; Add to
my calendar makes one file with the days still to do; Home shows today's phrases with Start; Remove takes it away,
asked first. A part without a plan asks the day. Both skins.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

CATCH = """window.downloads = [];
HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloads.push(this.href); };"""
PLAN = "/rehearse/amazing-grace/alto"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def in_days(f, n):
    return f.js(f"(() => {{ const d = new Date(); d.setDate(d.getDate() + {n}); return `${{d.getFullYear()}}-${{String(d.getMonth() + 1).padStart(2, '0')}}-${{String(d.getDate()).padStart(2, '0')}}`; }})()")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    clear_projects(f)
    f.run("const s = await import('/js/stores/learning.js'); await s.loadLearning(); await s.setRehearsals([]);")

    # The site's Pick the day: the plan made, three days of phrases and the whole part on the rehearsal day.
    date = in_days(f, 3)
    f.go(f"/?api=mock&scenario=&as=member&song=amazing-grace&part=alto&rehearsal={date}", f"location.pathname === '{PLAN}' && document.querySelector('[data-rehearse-days]')")
    days = f.js("[...document.querySelectorAll('[data-rehearse-day]')].map((li) => li.dataset.rehearseDay)")
    assert len(days) == 4 and days[-1] == date, days
    assert "Amazing Grace" in f.js("document.querySelector('[data-rehearse-title]').textContent"), "the song isn't named"
    assert f.js("document.querySelectorAll('[data-rehearse-start]').length") == 1, "one Start, on today's row"
    assert "behind" not in f.js("document.querySelector('main').innerText").lower()
    f.no_sideways("the plan")
    f.shot("plan")

    # Add to my calendar: one file, an entry for each day still to do.
    f.run(CATCH)
    f.run("document.querySelector('[data-rehearse-calendar]').click()")
    f.wait("window.downloads.length === 1", what="the calendar file")
    ics = f.js("(async () => (await fetch(window.downloads[0])).text())()")
    assert ics.count("BEGIN:VEVENT") == 4 and "/rehearse/amazing-grace/alto?ref=rr-calendar" in ics, ics[:300]

    # Start: Sing's Coach on today's first phrase, part by part.
    first = f.js("(async () => { const { learning } = await import('/js/stores/learning.js'); return learning.progress.rehearsals[0].days[0].phrases[0]; })()")
    f.run("document.querySelector('[data-rehearse-start]').click()")
    f.wait("/\\/sing$/.test(location.pathname)", what="Sing")
    f.wait(f"(async () => {{ const {{ session }} = await import('/js/stores/session.js'); return session.practice?.sing?.way === 'parts' && session.practice?.phrase === {first}; }})()", what="Coach on today's first phrase")
    f.shot("coach")

    # Coach's mark for today's phrases (on your own, each once): the day done on the plan.
    f.run("""const { learning } = await import('/js/stores/learning.js'); const day = learning.progress.rehearsals[0].days[0];
        const { session, savePractice } = await import('/js/stores/session.js'); const part = session.parts.find((p) => p.templatePartKey === 'alto');
        const phrases = {}; for (let i = day.phrases[0]; i <= day.phrases[1]; i++) phrases[i] = 1;
        session.practice.progress = { ...(session.practice.progress ?? {}), alto: { step: 'own', phrases } }; savePractice();
        await new Promise((done) => setTimeout(done, 1200));""")  # savePractice keeps it after half a second: kept before the page goes
    f.go(PLAN, "document.querySelector('[data-rehearse-days]')")
    f.wait("document.querySelector('[data-rehearse-day][data-done]')", what="today's day marked done")
    f.shot("day-done")

    # Home: the plan's line with Start.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-rehearsal-plans] [data-rehearsal-plan]')")
    line = f.js("document.querySelector('[data-rehearsal-plan]').innerText")
    assert t(f, "rehearse.homeDays", {"days": 3}) in line, line
    f.run("document.querySelector('[data-rehearsal-plans]').scrollIntoView({ block: 'center' })")
    f.shot("home")

    # A part without a plan asks the day, one field.
    f.go("/rehearse/amazing-grace/tenor", "document.querySelector('[data-rehearse-ask]')")
    f.run(f"const i = document.querySelector('[data-rehearse-date]'); i.value = {json.dumps(in_days(f, 1))}; i.dispatchEvent(new Event('input'));")
    f.run("document.querySelector('[data-rehearse-ask]').requestSubmit()")
    f.wait("document.querySelector('[data-rehearse-days]')", what="the tenor's plan")
    # One day left: the whole part today, and the ready check.
    assert f.js("document.querySelectorAll('[data-rehearse-day]').length") == 1, "short on time isn't the whole part today"
    f.shot("short")

    # Remove: asked first, naming the plan; gone from Home after.
    f.run("document.querySelector('[data-rehearse-remove]').click()")
    f.wait("document.querySelector('[role=dialog]')", what="the removal asked")
    assert "Amazing Grace" in f.js("document.querySelector('[role=dialog]').innerText"), "the ask doesn't name the plan"
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("location.pathname === '/'", what="Home")
    f.run("const s = await import('/js/stores/learning.js'); await s.setRehearsals([]);")


if __name__ == "__main__":
    run("rehearsal_plan", journey)
