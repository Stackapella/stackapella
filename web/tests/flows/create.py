"""The template creator (M7; docs/template-creator-plan.md, "Verification"): Sing it with a voice
simulated through loopback, line by line (counted in 1 2 3) (the lines' notes and words land), an empty take's message,
Check the notes, Suggest the parts in a style and Try another chord, name and save, and the template
under Your templates with its own page. Then a draft left halfway shows under Unfinished and Resume
brings it back; and Make a copy of Amazing Grace, moved up a tone in the editor, saved, and started.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from sound import TAP  # noqa: E402
from help_shots import light_first  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
STORE = "(await import('/js/stores/templates.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def draft(f):
    """The draft being made, as saved (the creator autosaves)."""
    return f.js(f"(async () => {{ await new Promise((r) => setTimeout(r, 900)); return {STORE}.loadDraft(location.pathname.split('/').pop()); }})()")


def sing_line(f, number, start, end, shift):
    """Sings Amazing Grace's tune from beat `start` to `end` (the synth, through loopback), moved by
    `shift` beats so its pickup lands in the lead-in bar."""
    f.click_t("create.sing.singLine", {"number": str(number)}) if number == 1 else f.click_t("create.sing.nextLine")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
        const song = await (await fetch('/templates/amazing-grace.json')).json();
        const notes = song.parts[0].guide.notes.filter((n) => n.beat >= {start} && n.beat < {end}).map((n) => ({{ ...n, beat: n.beat + {shift} }}));
        const sr = engine.sampleRate; const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime - 0.02);
        const sung = renderPart(notes, {{ bpm: 80, sampleRate: sr }});
        const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr));
        engine.preview(voice, sr);""")
    # It stops by itself after a quiet bar and a half, then finds the notes.
    f.wait(f"!{AUDIO}.engine.playing && document.body.innerText.includes({I18N}.t('create.sing.again'))", timeout=45, what=f"line {number} transcribed")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    f.js(f"{STORE}.loadMine()")
    f.run("document.querySelector('[data-new-song]').click()")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.click(t(f, "create.home.sing"), "[role=dialog] button")
    f.wait("location.pathname.startsWith('/create/')", what="a draft's address")
    f.wait_text(t(f, "create.tempo.title"))
    # Tempo and feel: 80, in 3.
    f.run("const el = document.querySelector('input[type=number]'); el.value = '80'; el.dispatchEvent(new Event('change', { bubbles: true }));")
    f.click(t(f, "create.tempo.sig.three"))
    f.shot("tempo")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    # The words, split into syllables.
    f.wait_text(t(f, "create.words.title"))
    f.type("textarea", "Amazing grace, how sweet the sound\nThat saved a wretch like me")
    f.wait("document.querySelectorAll('ol input.input').length === 2", what="two lines")
    assert f.js("document.querySelector('ol input.input').value") == "A-maz-ing grace, how sweet the sound", "the split"
    f.shot("words")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    # Sing: a high voice, through loopback.
    f.click_t("create.sing.octaveHigh")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    # Hillel: "offer to play the key note first". Before anything is sung, the key so far is C; it sounds.
    assert "(C)" in f.js("document.querySelector('[data-key-note]').textContent"), "no key note to play at the start"
    f.js(TAP)
    f.run("document.querySelector('[data-key-note]').click()")
    f.js("new Promise((r) => setTimeout(r, 1200))")
    peak = f.js("(() => { window.__listening = false; return window.__peak; })()")
    assert peak > 0.01, f"the key note is silent (peak {peak})"
    # A take with no singing (loopback carries only the click): nothing caught, and the way out.
    # Hillel: "on the sing it line by line it didn't count 1 2 3 4". The same count-in as everywhere,
    # counting up with the clicks: in 3, 1 2 3.
    f.run("""window.__shown = []; window.__started = false; const watch = () => {
            const n = +(document.querySelector('[data-count-in]')?.dataset.countIn ?? 0);
            if (n && window.__shown.at(-1) !== n) window.__shown.push(n);
            if (!window.__started) requestAnimationFrame(watch); };
        requestAnimationFrame(watch);""")
    f.click_t("create.sing.singLine", {"number": "1"})
    f.wait(f"{AUDIO}.engine.playing?.recording && {AUDIO}.engine.positionSeconds() > 0.3", what="recording")
    f.run("window.__started = true;")
    shown = f.js("window.__shown")
    assert shown == [1, 2, 3], f"line by line counted in {shown}, not 1 2 3"
    f.click_t("create.sing.done")
    f.wait_text(t(f, "create.sing.empty"), timeout=20)
    f.shot("empty")
    # Line 1 sung for real: the pickup "A-" (beat 0 of the song) goes at beat 2 of the lead-in bar.
    sing_line(f, 1, 0, 12, 2)
    saved = draft(f)
    line = saved["lines"][0]
    assert len(line["notes"]) >= 10, f"line 1 notes: {line['notes']}"
    assert line["lyrics"][0] == "A-" and "grace," in line["lyrics"], f"line 1 words: {line['lyrics']}"
    f.shot("line-1")
    # Line 2 ("That" at the song's beat 11) the same way.
    sing_line(f, 2, 11, 24, 2 - 11)
    saved = draft(f)
    melody = next(p for p in saved["template"]["parts"] if p.get("melody"))["guide"]["notes"]
    assert saved["template"]["pickupBeats"] == 1, f"pickup {saved['template']['pickupBeats']}"
    assert saved["template"]["key"] in ("G", "Em", "D"), f"key {saved['template']['key']}"
    note = saved["template"]["key"].rstrip("m")
    f.wait(f"document.querySelector('[data-key-note]')?.textContent.includes('({note})')", what="the key note following the key found")
    assert sum(1 for n in melody if n.get("br")) == 2, "two lines"
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    # Check the notes.
    f.wait_text(t(f, "create.check.title"))
    f.wait("document.querySelector('svg[role=img] rect')", what="the tune on the lane")
    f.shot("check")
    # Hillel: "on the check page offer to auto fix notes". Line 1's fourth note jumps an octave (a mic
    # slip): Fix all puts it back, says what changed, and Undo restores it.
    pitch = draft(f)["lines"][0]["notes"][3]["pitch"]
    f.run(f"""const s = {STORE}; const d = await s.loadDraft(location.pathname.split('/').pop());
        d.lines[0].notes[3].pitch += 12; const r = (await import('/js/creator.js')).melodyFromLines(d.lines, d.template.timeSignature);
        d.unsure = r.unsure; d.template.parts.find((p) => p.melody).guide.notes = r.notes; await s.saveDraft(d);""")
    f.go(f.js("location.pathname"), "document.querySelector('svg[role=img] rect')")
    f.wait("document.querySelector('[data-fix-all]')", what="Fix all, for the slip")
    f.run("document.querySelector('[data-fix-all]').click()")
    f.wait("document.querySelector('[data-fixed]')?.textContent.includes('→')", what="what the fix changed")
    assert draft(f)["lines"][0]["notes"][3]["pitch"] == pitch, "the slip is back an octave"
    light_first(f)  # the shot goes to Hillel's review, in the light theme
    f.js("document.querySelector('[data-fixed]').scrollIntoView({ block: 'center' })")
    f.shot("check-fixed")
    f.run("document.querySelector('[data-undo-fix]').click()")
    f.wait("!document.querySelector('[data-fixed]')", what="Undo")
    assert draft(f)["lines"][0]["notes"][3]["pitch"] == pitch + 12, "Undo restores the note"
    # One at a time: the note's own Fix it.
    f.run("document.querySelector('[data-fix-all]') && [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === " + json.dumps(t(f, "create.check.start")) + ").click()")
    f.wait("document.querySelector('[data-fix-it]')", what="Fix it on the note")
    f.run("document.querySelector('[data-fix-it]').click()")
    f.wait(f"(async () => (await {STORE}.loadDraft(location.pathname.split('/').pop())).lines[0].notes[3].pitch === {pitch})()", what="Fix it")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    # The other parts: suggested, Warm, and another chord somewhere.
    f.click_t("create.parts.suggest")
    f.wait_text(t(f, "create.parts.chordsTitle"))
    f.click(t(f, "create.parts.style.warm"), "[role=radio]")
    before = json.dumps(draft(f)["template"]["chords"])
    f.click_t("create.parts.another")
    after = draft(f)["template"]
    assert json.dumps(after["chords"]) != before, "another chord"
    assert len(after["parts"]) == 4, "four parts"
    f.wait_text(t(f, "create.parts.version", {"count": 4, "parts": ""}).strip(" :"))
    f.shot("parts")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    # Name it (the first line is offered) and save.
    f.wait_text(t(f, "create.save.title"))
    assert f.js("document.querySelector('form input').value") == "Amazing grace, how sweet the sound", "the title offered"
    f.type("form input", "My Grace")
    f.click(t(f, "create.save.mine"), "label")
    f.shot("save")
    f.click_t("create.save.save")
    f.wait("location.pathname.startsWith('/t/')", what="the template's page")
    f.wait_text(t(f, "template.learn"))
    f.wait_text("My Grace")
    f.shot("template")
    # In Your songs on the home (written by you), never in the library; and the draft has gone.
    f.go("/", "document.querySelector('#app header')")
    f.wait("document.querySelector('section[aria-labelledby=band-projects] [data-kind=written]')?.innerText.includes('My Grace')", what="the written song in Your songs")
    assert not f.js("document.querySelector('#home-songs').innerText.includes('My Grace')"), "the written song is in the library too"
    assert not f.js("document.querySelector('[data-kind=draft]')"), "no draft left"

    # Leave a draft halfway: it's in Your songs as a draft, and Resume brings it back.
    f.run("document.querySelector('[data-new-song]').click()")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.click(t(f, "create.home.write"), "[role=dialog] button")
    f.wait_text(t(f, "create.tempo.title"))
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    f.wait_text(t(f, "create.edit.title"))
    f.click(t(f, "create.leave"))
    f.wait("location.pathname === '/'", what="home")
    f.wait("document.querySelector('section[aria-labelledby=band-projects] [data-kind=draft]')", what="the draft in Your songs")
    assert not f.js("document.querySelector('#home-songs [data-kind=draft]')"), "the draft is in the library"
    f.run("document.querySelector('section[aria-labelledby=band-projects] [data-kind=draft] > button').click()")  # a tap on the draft resumes it
    f.wait_text(t(f, "create.edit.title"))
    f.shot("resumed")

    # Make a copy of Amazing Grace, a tone higher, saved and started.
    f.go("/", "document.querySelector('#app header')")
    f.wait_text("Amazing Grace")
    f.run("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace')).querySelector('button').click()")
    f.wait("document.querySelector('[role=dialog] [data-song-start]')", what="the song's sheet")
    f.run("document.querySelector('[role=dialog] [data-song-edit]').click()")  # Hillel: Edit is Make a copy
    f.wait_text(t(f, "create.edit.title"))
    if f.size == "desktop":
        f.run("document.querySelector('[data-note]').dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))")
        f.run("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', metaKey: true, bubbles: true }))")
        f.run("window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))")
        f.run("window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))")
    else:
        f.run("document.querySelector('[data-note]').dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))")
        f.wait("document.querySelector('[role=dialog]')", what="the note's sheet")
        f.click_t("create.check.up")
        f.click_t("create.check.up")
        f.click_t("common.close")
    copy = draft(f)["template"]
    first = copy["parts"][0]["guide"]["notes"][0]["pitch"]
    assert first == 64, f"the first note moved up a tone: {first}"
    f.shot("copy-edited")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    f.click(t(f, "create.save.traditional"), "label")
    f.click_t("create.save.save")
    f.wait("location.pathname.startsWith('/t/') && document.querySelector('[role=radio][aria-checked=true]')", what="the copy's page, a part picked")
    f.shot("song-page")
    f.run("document.querySelector('#pick-heading').closest('section').querySelector('button.btn-primary').click()")
    f.wait("location.pathname.endsWith('/sing')", what="a project from the copy")


if __name__ == "__main__":
    run("create", journey)
