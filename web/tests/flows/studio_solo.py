"""A beta singer (2026-10-29): "the mute and solo buttons in the studio do not work reliably". In
the beta guide studio (a part recorded for Hillel, the singer's practice focus on it), each row's
Solo and Mute decide what the engine plays: every part, stopped and while playing, two solos, a solo
then a mute, a quick double tap, and after a reload (the saved state). Then the follow-up ("when I
want to listen to my own playback, I can only hear the original tone"): once the part has a take,
the take plays in its place, and solos like any row. And "I had it loop a section, but now can't get
it to unloop that section": Stop looping, the banner's button pressed again, and Escape each end the
loop, and playing goes on past the phrase. Measured on the engine's own mix, not the buttons.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
SESSION = "(await import('/js/stores/session.js'))"
# The song's parts the engine plays now (their guides, none recorded yet), by template key.
HEARD = ("(await (async () => { const a = await import('/js/audio.js'); const s = await import('/js/stores/session.js');"
         " return [...a.engine.parts.entries()].filter(([id, p]) => id.endsWith(':guide') && p.mix?.audible)"
         ".map(([id]) => s.session.parts.find((p) => p.id + ':guide' === id)?.templatePartKey).sort().join(' '); })())")


# The takes the engine plays now, by template key.
TAKES = ("(await (async () => { const a = await import('/js/audio.js'); const s = await import('/js/stores/session.js');"
         " return [...a.engine.parts.entries()].filter(([id, p]) => !id.includes(':') && p.mix?.audible)"
         ".map(([id]) => s.session.parts.find((p) => p.id === id)?.templatePartKey).filter(Boolean).sort().join(' '); })())")


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def press(f, which, key):
    """Clicks a row's Solo or Mute ('solo' or 'mute') by the part's name."""
    name = t(f, f"parts.{key}")
    label = t(f, "studio.soloNamed" if which == "solo" else "studio.muteNamed", {"name": name})
    f.run(f"document.querySelector('[data-{which}][aria-label={json.dumps(label)}]').click()")


def expect(f, keys, what):
    want = " ".join(sorted(keys))
    f.wait(f"({HEARD}) === {json.dumps(want)}", timeout=5, what=f"{what}: {want or 'nothing'} (heard: " + str(f.js(HEARD)) + ")")


def journey(f):
    f.go("/beta?api=mock&scenario=&as=singer", "document.querySelector('main h1')")
    f.mic_checked()
    f.wait_text(t(f, "beta.parts.title"))
    f.run("[...document.querySelectorAll('li.card')].find((li) => li.textContent.includes('Amazing Grace')).querySelector('.btn-primary').click()")
    f.wait("location.pathname.startsWith('/p/') && document.querySelectorAll('[data-solo]').length === 4", what="the beta studio")
    project = f.js("location.pathname.split('/')[2]")
    f.js(f"{AUDIO}.unlock()")
    assert f.js(f"!!{SESSION}.session.practice.focus"), "the beta part isn't in focus (this flow is about that case)"
    f.shot("beta-studio")  # the part's notes on its row, each ending at its breath, with the breath marks
    # Every part plays with nothing soloed or muted (the beta part's guide as its practice says).
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.positionInfo()?.position > 0.5", what="playing")
    everyone = f.js(HEARD).split()
    assert everyone == ["alto", "bass", "soprano", "tenor"], f"not every part plays: {everyone}"
    # Solo while playing: the soprano alone; a second solo adds the bass.
    press(f, "solo", "soprano")
    expect(f, ["soprano"], "the soprano soloed while playing")
    press(f, "solo", "bass")
    expect(f, ["soprano", "bass"], "two solos")
    f.shot("two-solos")
    # Solos off, then a mute: everyone but the tenor.
    press(f, "solo", "soprano")
    press(f, "solo", "bass")
    expect(f, everyone, "the solos off")
    press(f, "mute", "tenor")
    expect(f, [k for k in everyone if k != "tenor"], "the tenor muted")
    # A solo with a mute on: the solo decides.
    press(f, "solo", "alto")
    expect(f, ["alto"], "the alto soloed, the tenor still muted")
    # A quick double tap is two toggles: back where it was.
    press(f, "solo", "alto")
    press(f, "solo", "alto")
    expect(f, ["alto"], "a double tap undone")
    # Stopped, then played again: the same.
    f.click_t("studio.pause")
    f.wait(f"!{AUDIO}.engine.playing", what="paused")
    press(f, "solo", "alto")
    press(f, "solo", "soprano")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing", what="playing again")
    expect(f, ["soprano"], "a solo chosen while stopped")
    f.click_t("studio.pause")
    f.wait(f"!{AUDIO}.engine.playing", what="paused")
    # After a reload, the saved state plays the same.
    f.wait(f"{SESSION}.session.saving === 0 && (await (await import('/js/db.js')).allBy('parts', 'projectId', '{project}')).filter((p) => p.solo).map((p) => p.templatePartKey).join() === 'soprano'", what="the solo saved")
    f.go(f"/p/{project}", "document.querySelectorAll('[data-solo]').length === 4")
    f.js(f"{AUDIO}.unlock()")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing", what="playing after the reload")
    expect(f, ["soprano"], "the saved solo after a reload")
    f.click_t("studio.pause")
    f.wait(f"!{AUDIO}.engine.playing", what="paused")
    press(f, "solo", "soprano")
    # The beta part (the alto) recorded: its take plays instead of its guide, and solos like any row.
    f.run(f"""const s = {SESSION}; const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
        const part = s.session.parts.find((p) => p.templatePartKey === 'alto');
        const samples = renderPart([{{ beat: 0, length: 8, pitch: 62 }}], {{ bpm: 80, sampleRate: engine.sampleRate }});
        await s.storeTake(part, {{ sampleRate: engine.sampleRate, frames: samples.length, offsetSeconds: 0, nudgeSeconds: 0, peaks: null }}, samples);""")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing", what="playing with the take")
    f.wait(f"({TAKES}) === 'alto' && !({HEARD}).split(' ').includes('alto')", timeout=5, what="the alto's take, not its guide: takes " + str(f.js(TAKES)) + ", guides " + str(f.js(HEARD)))
    expect(f, ["bass", "soprano"], "the other parts' guides with it (the tenor still muted)")
    press(f, "solo", "alto")
    f.wait(f"({TAKES}) === 'alto' && ({HEARD}) === ''", timeout=5, what="the take soloed")
    press(f, "solo", "alto")
    f.click_t("studio.pause")
    f.wait(f"!{AUDIO}.engine.playing", what="paused")
    # Hillel's note's phrase, looped from the banner: said, with Stop looping; off, the song goes on.
    looping = f"{SESSION}.session.practice.loop && {AUDIO}.engine.playing && !!document.querySelector('[data-loop-on]')"
    phrase_end = f"{SESSION}.session.phraseList[{SESSION}.session.practice.phrase].endSeconds"
    f.run("document.querySelector('[data-loop-phrase]').click()")
    f.wait(looping, what="the phrase looping")
    assert f.js("document.querySelector('[data-loop-phrase]').getAttribute('aria-pressed')") == "true", "the banner's Loop isn't shown pressed"
    f.shot("looping")
    f.run("document.querySelector('[data-loop-off]').click()")
    f.wait(f"!{SESSION}.session.practice.loop && {AUDIO}.engine.playing && !document.querySelector('[data-loop-on]')", what="the loop off, still playing")
    f.wait(f"{SESSION}.session.position > ({phrase_end}) + 0.3", timeout=20, what="playing on past the phrase")
    # The banner's button again: on, then off.
    f.run("document.querySelector('[data-loop-phrase]').click()")
    f.wait(looping, what="looping again")
    f.run("document.querySelector('[data-loop-phrase]').click()")
    f.wait(f"!{SESSION}.session.practice.loop && {AUDIO}.engine.playing", what="the banner's button turned it off")
    # Escape ends it too.
    f.run("document.querySelector('[data-loop-phrase]').click()")
    f.wait(looping, what="looping once more")
    f.press("Escape")
    f.wait(f"!{SESSION}.session.practice.loop && !document.querySelector('[data-loop-on]')", what="Escape ended the loop")
    f.click_t("studio.pause")


if __name__ == "__main__":
    run("studio_solo", journey)
