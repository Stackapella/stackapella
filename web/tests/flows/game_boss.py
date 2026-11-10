"""A unit's boss round (docs/boss-round-plan.md). The course's unit 2 gets a fixed boss here (three 4-beat holds, as the
courses lead wrote it but without the drone, which the loopback would hear as the voice), so the flow doesn't depend on
which content is pinned. In the first week no boss
shows on the course map and its link is locked; after it, the map has the boss's row. In game mode with Boss rounds on:
the boss stage's intro (the banner, the gem, 80% to beat), the bar that drains as each round scores, no stars, and
Boss beaten! with 100 XP once and the Mastered mark on the unit; a weak try ends on Continue? with the honest percent.
With the switch off, and in serious mode, it's the calm Unit review: no bar, the same score, "Review passed". Run in
serious (FLOW_SKINS=serious), it checks the map's row says Unit review and plays the review through.
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, progress, t  # noqa: E402
from game_frames import every_theme  # noqa: E402

DAY = 86400000
BOSS = {"id": "2-boss", "title": {"en": "Three vowels, steady"}, "steps": [
    {"kind": "play", "text": {"en": f"Hold \"{v}\" on the drone."}, "exercise": {"tempo": 72, "turn": False, "score": "hold", "notes": [{"beat": 2, "length": 4, "step": 0, "lyric": v}]}}
    for v in ("oo", "oh", "ah")]}
# Before any page script: the course's unit 2 has this boss (whatever the pinned content says).
PATCH = """(() => { const real = window.fetch; window.fetch = async (url, init) => { const answer = await real(url, init);
  if (!String(url).endsWith('/course/course.json')) return answer;
  const course = await answer.clone().json(); const unit = course.units?.find((u) => u.id === 'steady-notes'); if (unit) unit.boss = %s;
  return new Response(JSON.stringify(course), { status: 200, headers: { 'Content-Type': 'application/json' } }); }; })();""" % json.dumps(BOSS)
UNITS = ["1-comfortable", "1-match", "1-high-low", "1-breath", "1-challenge", "2-hold", "2-drone", "2-vowels", "2-challenge"]
STARRED = {lesson: {"stars": 2, "bestPercent": 80, "at": 0} for lesson in UNITS}
ROW = "document.querySelector('[data-unit=\"steady-notes\"] [data-boss-row]')"


def seed(f, days):
    """Units 1 and 2 done, the course started `days` ago, no boss tried."""
    progress(f, STARRED, 300, extra=f", startedAt: Date.now() - {days} * {DAY}, home: 57, range: {{ low: 50, high: 70 }}")


def course_map(f):
    f.go("/learn?api=mock&scenario=&as=member", "document.querySelector('[data-course-map]')")
    f.run("const head = document.querySelector('[data-unit=\"steady-notes\"] [data-unit-head]'); if (head.getAttribute('aria-expanded') !== 'true') head.click();")


def sing_round(f, beats, cents):
    """One round: the take started, a held A3 sung for `beats` from the note's start (beat 2), `cents` off, scored."""
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="the take")
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ sing }} = await import('/tests/fixtures.js'); const sr = engine.sampleRate; const beat = 60 / 72;
        const sung = sing(sr, {{ notes: [{{ beat: 0, length: {beats}, pitch: 57 }}], bpm: 72, cents: {cents} }});
        const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime) + 2 * beat;
        const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr)); engine.preview(voice, sr);""")
    f.wait("document.querySelector('[data-boss-round]')", timeout=40, what="the round scored")
    print("     " + f.js("document.querySelector('[data-boss-round]').textContent"))


def play_boss(f, cents):
    """Every round of the boss sung as a whole hold, `cents` off the note, to its end."""
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    for i in range(3):
        sing_round(f, 4.2, cents)
        scored = f.js("Number(document.querySelector('[data-boss-round]').textContent.match(/(\\d+)%/)[1])")
        if f.skin == "game" and f.js("!!document.querySelector('[data-boss-bar]')"):
            left = f.js("Number(document.querySelector('[data-boss-bar]').dataset.left)")
            assert (left < 100) == (scored > 0), f"round {i + 1} at {scored}%: the bar drains only by what's scored ({left} left)"
        assert not f.js("!!document.querySelector('[data-result-heading]')"), "a round shows its percent, never stars"
        f.click_t("course.finish" if i == 2 else "course.continue")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.wait("document.querySelector('[data-boss-end]')", timeout=20, what="the boss's end")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": PATCH})
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); sessionStorage.setItem('stackapella.headphones', '1');")
    f.mic_checked()

    # The first week: no boss on the map, and its link is locked.
    seed(f, 3)
    course_map(f)
    assert not f.js(f"!!{ROW}"), "no boss in the first week"
    # Waits for the locked words themselves: under load the lesson's loading line is the first paragraph.
    f.go("/learn/2-boss?api=mock&scenario=&as=member", "document.querySelector('main p')")
    f.wait_text(t(f, "boss.locked"))

    # Past it: the row, open (the challenge has stars), and into the boss stage.
    seed(f, 8)
    course_map(f)
    f.wait(f"{ROW}?.hasAttribute('data-open')", what="the boss's row, open")
    if f.skin != "game":
        # A serious run (FLOW_SKINS=serious): the row is the calm Unit review, then the review itself.
        assert t(f, "boss.map.review").upper() in f.js(f"{ROW}.innerText").upper(), "serious mode says Unit review"
        f.no_sideways("the course map with its review")
        every_theme(f, "the review's row")
        calm_review(f)
        return
    assert t(f, "boss.map.boss").upper() in f.js(f"{ROW}.innerText").upper(), "game mode says Boss"
    f.no_sideways("the course map with its boss")
    f.run(f"{ROW}.scrollIntoView({{ block: 'center' }})")
    f.shot("boss-row")
    every_theme(f, "the boss's row")
    f.run(f"{ROW}.querySelector('[data-boss-go]').click()")
    f.wait("document.querySelector('[data-game-boss][data-boss-intro]')", what="the boss stage's intro")
    f.wait("!document.querySelector('[data-boss-start]').disabled", timeout=15, what="its notes counted")
    words = f.js("document.querySelector('[data-boss-intro]').innerText")
    assert "80%" in words and t(f, "boss.noTry") in words, f"80% to beat, no try yet: {words}"
    f.no_sideways("the boss intro")
    f.run("for (const a of document.getAnimations()) { try { a.finish(); } catch {} }")
    f.shot("boss-intro")
    every_theme(f, "the boss intro")
    f.run("document.querySelector('[data-boss-start]').click()")
    f.wait("document.querySelector('[data-boss-bar]')", what="the boss's bar")
    assert f.js("document.querySelector('[data-boss-bar]').dataset.left") == "100", "a full bar before a note"
    f.shot("boss-bar")
    every_theme(f, "the boss's bar")

    # A weak try: each hold whole but a third of a tone sharp, so heard and under the line: Continue?, the honest percent,
    # nothing paid.
    xp = f.js("(await import('/js/stores/learning.js')).learning.progress.xp.total")
    play_boss(f, 70)
    end = f.js("document.querySelector('[data-boss-end]').innerText")
    assert not f.js("document.querySelector('[data-boss-end]').hasAttribute('data-beaten')"), f"not beaten: {end}"
    assert t(f, "game.done.continue").upper() in end.upper() and "80%" in end, f"Continue? and the line to beat: {end}"
    assert f.js("(await import('/js/stores/learning.js')).learning.progress.xp.total") == xp, "a lost try pays nothing"
    f.shot("boss-continue")
    every_theme(f, "Continue?")

    # Again, straight into the first round (the intro seen): every hold whole, so beaten.
    f.run("document.querySelector('[data-boss-again-or-back]').click()")
    f.wait("document.querySelector('[data-boss-bar]')", what="straight back into round 1")
    play_boss(f, 3)
    f.wait("document.querySelector('[data-boss-end][data-beaten]')", what="Boss beaten!")
    end = f.js("document.querySelector('[data-boss-end]').innerText")
    assert t(f, "boss.game.beaten").upper() in end.upper() and "+100 XP" in end, f"beaten, 100 XP: {end}"
    kept = f.js("JSON.parse(JSON.stringify((await import('/js/stores/learning.js')).learning.progress))")
    assert kept["bosses"]["steady-notes"]["beatAt"] and kept["badges"].get("bossBeaten"), f"kept and badged: {kept.get('bosses')}"
    assert all(kept["lessons"][lesson]["stars"] == 2 for lesson in UNITS), "the boss gave no stars"
    f.no_sideways("the boss beaten")
    f.shot("boss-beaten")
    every_theme(f, "Boss beaten!")
    f.run("document.querySelector('[data-boss-again-or-back]').click()")
    f.wait("location.pathname === '/learn'", what="back to the course")
    course_map(f)
    f.wait("document.querySelector('[data-unit=\"steady-notes\"] [data-unit-mastered]')", what="the unit's Mastered mark")

    # The switch off: the calm Unit review in the game skin, no bar.
    f.run("(await import('/js/game/effects.js')).effects.boss = false")
    f.go("/learn/2-boss?api=mock&scenario=&as=member", "document.querySelector('[data-boss-intro]')")
    assert f.js("!!document.querySelector('[data-unit-review]') && !document.querySelector('[data-game-boss]')"), "the switch off: the calm review"
    assert t(f, "boss.review.title") in f.js("document.querySelector('[data-boss-intro]').innerText"), "named Unit review"
    f.run("localStorage.removeItem('stackapella.gameEffects')")

    # Serious mode: the same, calm, with "Review passed" and no bar.
    f.run("window.stackapellaTheme.choose({ skin: 'serious' })")
    time.sleep(0.5)
    calm_review(f)
    f.run("window.stackapellaTheme.choose({ skin: 'game' })")


def calm_review(f):
    """The calm Unit review played through: no bar, "Review passed"."""
    f.go("/learn/2-boss?api=mock&scenario=&as=member", "document.querySelector('[data-unit-review]')")
    f.wait("!document.querySelector('[data-boss-start]').disabled", timeout=15, what="its notes counted")
    f.shot("review-intro")
    f.run("document.querySelector('[data-boss-start]').click()")
    f.wait("document.querySelector('[data-step-line]')", what="round 1")
    assert not f.js("!!document.querySelector('[data-boss-bar]')"), "no bar in serious mode"
    play_boss(f, 3)
    end = f.js("document.querySelector('[data-boss-end]').innerText")
    assert f.js("document.querySelector('[data-boss-end]').hasAttribute('data-beaten')") and t(f, "boss.review.passedLine") in end, f"passed: {end}"
    f.no_sideways("the review passed")
    f.shot("review-passed")


if __name__ == "__main__":
    run("game_boss", journey)
