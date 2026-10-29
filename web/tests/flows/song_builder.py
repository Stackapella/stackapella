"""Start from chords (docs/song-builder-plan.md, steps 2 to 4): from Make your own, pick a feel (the four
chords), hear it play and stop it, choose it; the key step suggests a key for a picked voice, with
Higher and Lower; Sing over it shows the chord row. (Singing itself needs a mic: the flow puts a tune
in as the step would, then checks the parts are voiced on the chosen chords.) Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from sound import TAP  # noqa: E402
from help_shots import light_first  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?api=mock&as=member", "document.querySelector('[data-new-song]')")
    light_first(f)  # the shots go to Hillel's review, in the light theme
    f.run("document.querySelector('[data-new-song]').click()")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.click(t(f, "create.home.chords"), "[role=dialog] button")
    f.wait("location.pathname.startsWith('/create/') && document.querySelector('h2')", what="the chords step")
    name = t(f, "theory.prog.fourChords.name")
    f.click(t(f, "create.chords.play", {"name": name}), "button")
    f.wait("(await import('/js/audio.js')).engine.playing !== null", what="it plays")
    f.click(t(f, "studio.stop"), "button")
    f.wait("(await import('/js/audio.js')).engine.playing === null", what="it stops")
    f.run(f"[...document.querySelectorAll('.card')].find((c) => c.innerText.includes({json.dumps(name)})).querySelector('[role=radio]').click()")
    f.shot("chords")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    f.wait(f"document.querySelector('h2')?.textContent.trim() === {json.dumps(t(f, 'create.chords.keyTitle'))}", what="the key step")
    f.click(t(f, "create.chords.voice.alto"), "[role=radio]")
    f.wait("document.querySelector('[data-key]')?.innerText.length > 0", what="a key suggested")
    key = f.js("(await import('/js/stores/templates.js')) && document.querySelector('[data-key]').innerText")
    f.click(t(f, "create.chords.higher"), "button")
    f.wait(f"document.querySelector('[data-key]').innerText !== {json.dumps(key)}", what="Higher moves it")
    f.shot("key")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    f.wait("document.querySelector('[role=table] [role=cell]')", what="the chord chart")
    assert f.js("document.querySelectorAll('[role=table] [role=cell]').length") == 4, "four bars of chords"
    # Once round, singing (headless Chrome's fake microphone beeps): it stops itself and writes it down.
    f.run("const s = document.querySelector('main select, select'); s.value = '1'; s.dispatchEvent(new Event('change'));")
    f.click(t(f, "create.chords.start"), "button")
    # The first time the mic is needed, Check your mic (103.png) waits for Continue once it hears you.
    f.wait("(await import('/js/audio.js')).engine.playing !== null || document.querySelector('[data-mic-check]')", what="the mic check or the backing")
    if f.js("!!document.querySelector('[data-mic-check]')"):
        f.wait("document.querySelector('[data-mic-check]')?.dataset.state === 'ok'", timeout=20, what="the mic heard")
        f.click_t("micCheck.continue")
    f.wait("(await import('/js/audio.js')).engine.playing !== null", what="the backing with the count-in")
    f.shot("hum")
    # Hillel: "in sing or hum over it i didn't hear the music to sing over". The chords sound while you
    # hum: the engine's output, measured.
    f.wait_text(t(f, "create.chords.roundOf", {"round": 1, "times": 1}), timeout=15)  # past the count-in's clicks
    f.js(TAP)
    f.js("new Promise((r) => setTimeout(r, 2000))")
    peak = f.js("(() => { window.__listening = false; return window.__peak; })()")
    print(f"    hum over it: peak {peak:.3f}")
    assert peak > 0.01, f"the chords are silent while you hum over them (peak {peak})"
    f.wait(f"!(await import('/js/audio.js')).engine.playing && !document.body.innerText.includes({json.dumps(t(f, 'create.sing.working'))})", timeout=40, what="once round, then written down")
    heard = f.js("(async () => { const { loadDraft } = await import('/js/stores/templates.js'); const d = await loadDraft(location.pathname.split('/')[2]); return { notes: d.template.parts.find((p) => p.melody)?.guide.notes.length ?? 0, chords: d.template.chords?.length ?? 0 }; })()")
    print("    fake mic gave", heard)


if __name__ == "__main__":
    run("song_builder", journey)
