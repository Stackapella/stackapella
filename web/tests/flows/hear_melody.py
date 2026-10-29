"""Hear the melody (docs/decisions.md, 2026-10-28, "Melody always"): listening to a harmony part,
the song's melody plays under your guide, quieter, and the other parts don't. Part by part (the
coach's Listen, Amazing Grace's alto). The whole song's Listen (a lesson's or challenge's start: the
tenor chosen for you) plays every part. What you hear's melody row turns it off for the visit, and your own part
isn't listed while you sing the melody itself.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
SESSION = "(await import('/js/stores/session.js'))"
UNDER = "(await import('/js/practice.js')).MELODY_UNDER"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def new_project(f, focus_key=None):
    focus = f", focusKey: '{focus_key}'" if focus_key else ""
    return f.js(f"""(async () => {{
        const {{ startFromTemplate }} = await import('/js/stores/projects.js');
        const {{ loadTemplate }} = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), {{ singers: 4{focus} }})).id;
    }})()""")


def mix(f, key):
    """The engine's mix for a part's guide, by its template part key."""
    return f"{AUDIO}.engine.parts.get({SESSION}.session.parts.find((p) => p.templatePartKey === '{key}').id + ':guide')?.mix"


def melody_under(f, mine, other, what):
    f.wait(f"{mix(f, 'soprano')}?.audible && {mix(f, mine)}?.audible && !{mix(f, other)}?.audible", what=f"{what}: your guide and the melody, not the {other}")
    soprano = f"{SESSION}.session.parts.find((p) => p.templatePartKey === 'soprano').volume"
    assert f.js(f"Math.abs({mix(f, 'soprano')}.volume - {soprano} * {UNDER}) < 1e-6"), f"{what}: the melody isn't under your guide"


def open_hearing(f):
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-hearing]')", what="Session settings' What you hear")


def journey(f):
    f.go("/", "document.querySelector('#app main, #app h1, #app header')")
    f.mic_checked()
    # Part by part: the coach's Listen on the alto.
    grace = new_project(f)
    f.go(f"/p/{grace}/sing", "document.querySelector('h1')")
    f.run("document.querySelector(\'[data-way=parts]\').click()")  # the way starts at once
    alto = t(f, "parts.alto")
    f.wait_text(t(f, "sing.chooser.yourPart"))
    f.run(f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({alto!r})).click()")
    f.wait_text(t(f, "sing.coach.hint.listen", {"part": alto}))
    f.js(f"{AUDIO}.unlock()")
    if f.size == "desktop":
        f.click_t("studio.play")
    else:
        f.run("document.querySelector('[data-coach] .btn-primary').click()")
    f.wait(f"{AUDIO}.engine.playing", what="phrase 1 playing")
    melody_under(f, "alto", "bass", "the coach's Listen")
    f.click_t("studio.pause")
    # What you hear: the melody's own row turns it off, and the song remembers that.
    open_hearing(f)
    f.shot("hearing")
    f.run(f"""const name = {I18N}.t('parts.soprano'); [...document.querySelectorAll('[data-hearing] [role=switch]')].find((b) => b.textContent.includes(name)).click();""")
    f.wait(f"!{mix(f, 'soprano')}.audible", what="the melody off")
    f.press("Escape")
    f.js("new Promise((r) => setTimeout(r, 1200))")  # the song's practice saves settle before the reload
    f.go(f"/p/{grace}/sing", "document.querySelector('main')")
    f.wait_text(t(f, "sing.coach.hint.listen", {"part": alto}))
    assert f.js(f"{SESSION}.session.hearing.off.length === 1"), "What you hear wasn't remembered for the song"
    open_hearing(f)
    assert f.js(f"document.querySelector('[data-hearing]').innerText.includes({t(f, 'sing.hear.melody')!r})"), "the melody's row has no Melody tag"
    f.run(f"""const name = {I18N}.t('parts.soprano'); [...document.querySelectorAll('[data-hearing] [role=switch]')].find((b) => b.textContent.includes(name)).click();""")
    f.press("Escape")
    # The whole song's Listen, on a part chosen for you (as a lesson or challenge starts it).
    tenor = new_project(f, "tenor")
    f.go(f"/p/{tenor}/sing", "document.querySelector('h1')")
    f.run("document.querySelector(\'[data-way=whole]\').click()")  # the way starts at once
    f.click_t("practice.mode.listen")
    f.js(f"{AUDIO}.unlock()")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing", what="the whole song playing")
    # The whole song's Listen plays every part (Hillel), the melody at its own level.
    f.wait(" && ".join(f"{mix(f, k)}?.audible" for k in ("soprano", "alto", "tenor", "bass")), what="the whole song's Listen: every part")
    assert f.js(f"{mix(f, 'soprano')}.volume === 1"), "the melody is under in the whole song's Listen"
    f.click_t("studio.pause")
    # Singing the melody: What you hear lists yours first, as your guide.
    f.run(f"{SESSION}.session.practice.focus = {SESSION}.session.parts.find((p) => p.templatePartKey === 'soprano').id; {SESSION}.syncMix()")
    open_hearing(f)
    first = f.js("document.querySelector('[data-hearing] [role=switch]').textContent")
    assert t(f, "sing.hear.yours", {"part": t(f, "parts.soprano")}) in first, f"your guide isn't first in What you hear: {first}"

if __name__ == "__main__":
    run("hear_melody", journey)
