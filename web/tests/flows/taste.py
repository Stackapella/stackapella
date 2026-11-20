"""A guest's taste of a library song (Hillel, 2026-11-15: "Sing the first phrase, then ask"; docs/decisions.md).

A guest opens a library song: its sheet offers Start on the first line (no account), Sing opens on that line only,
and once it's sung along the free account's invitation comes in place of the next step. Not now goes on with the
line (on your own); the ask then waits, one quiet line in its place. Keep going is the sign-up, which says why it asks
the birth month and year; signed in, the same project opens on the whole song, the first line heard already.

    python3 -I tests/flows/taste.py http://127.0.0.1:8197
    TASTE_LANG=es python3 -I tests/flows/taste.py http://127.0.0.1:8197   # in Spanish
"""
import json
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
SESSION = "(await import('/js/stores/session.js'))"
PROJECT = f"{SESSION}.session.project"
LANG = os.environ.get("TASTE_LANG", "en")
SONG = "silent-night"  # not among the app's own songs: the mock answers a guest with its first phrase (grant "phrase")


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def play_step(f, key):
    """The step's action on the line: the transport's Play on a wide screen, the big button on a phone."""
    if f.size == "desktop":
        f.click_t("studio.play")
    else:
        f.wait("document.querySelector('[data-coach] .btn-primary')", what=f"the {key} action")
        f.run("document.querySelector('[data-coach] .btn-primary').click()")


def journey(f):
    f.go(f"/?api=mock&scenario=&as=signedOut&lang={LANG}", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.asks'); localStorage.removeItem('stackapella.catalogue')")
    # The sheet: Start sings the first line, and says the rest needs a free account; no wall before a note.
    f.go(f"/?api=mock&scenario=&as=signedOut&lang={LANG}&song={SONG}&ref=song-page", "document.querySelector('#app header')")
    f.wait("document.querySelector('[role=dialog] [data-song-start]:not([disabled])')", what="the guest's Start")
    start = t(f, "templates.taste.start")
    assert f.js("document.querySelector('[role=dialog] [data-song-start]').textContent.trim()") == start, "Start doesn't say the first line"
    f.wait_text(t(f, "templates.taste.note"))
    # The song's description as the catalogue words it, in the visitor's language (backend, 2026-11-15), over the full
    # song file's English.
    f.wait(f"(await import('/js/stores/catalogue.js')).catalogue.list.some((e) => e.id === {SONG!r})", what="the catalogue")
    f.run(f"const e = (await import('/js/stores/catalogue.js')).catalogue.list.find((e) => e.id === {SONG!r}); window.__said = e.description; e.description = 'Una descripción en el idioma de quien la visita.';")
    f.wait("document.querySelector('[data-sheet-lead]')?.textContent === 'Una descripción en el idioma de quien la visita.'", what="the catalogue's description on the sheet")
    f.run(f"(await import('/js/stores/catalogue.js')).catalogue.list.find((e) => e.id === {SONG!r}).description = window.__said")
    assert not f.js("!!document.querySelector('[role=dialog] [data-song-signup], [role=dialog] [data-song-edit]')"), "a wall or Edit on a guest's taste"
    f.shot("sheet")
    f.run("document.querySelector('[role=dialog] [data-song-start]').click()")
    f.wait("/^\\/p\\/[^/]+\\/sing$/.test(location.pathname)", what="Sing, on the first line")
    project_path = f.js("location.pathname")
    f.wait(f"{PROJECT}?.taste?.song === {SONG!r}", what="the project marked a taste")
    assert f.js(f"{PROJECT}.template.sections.length === 1 && {PROJECT}.template.sections[0].phrases.length === 1"), "more than the first line"
    # Part by part, the first part offered.
    f.wait("document.querySelector('[data-way=parts]')", what="the chooser")
    f.run("document.querySelector('[data-way=parts]').click()")
    f.wait_text(t(f, "sing.chooser.yourPart"))
    name = f.js(f"{PROJECT}.template.parts[0].name")
    part = f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({name!r}))"
    f.wait(part, what="the first part to choose")
    f.run(f"{part}.click()")
    f.wait("document.querySelector('[data-coach]')", what="the coach")
    key = f.js(f"(({{ session }}) => session.parts.find((p) => p.id === session.practice.focus)?.templatePartKey)({SESSION})")
    f.js(f"{AUDIO}.unlock()")
    # Listen to the line, then sing it along.
    play_step(f, "listen")
    f.wait(f"({PROJECT}.practice.progress?.[{key!r}]?.phrases?.[0] ?? 0) >= 1", timeout=40, what="the line heard")
    f.run(f"{SESSION}.session.playing && await {SESSION}.stopPlayback()")
    f.click_t("sing.coach.next", {"step": t(f, "sing.coach.step.singAlong")})
    f.wait(f"{PROJECT}.practice.progress?.[{key!r}]?.step === 'singAlong'", what="Sing along")
    play_step(f, "singAlong")
    f.wait(f"({PROJECT}.practice.progress?.[{key!r}]?.phrases?.[0] ?? 0) >= 1", timeout=40, what="the line sung along")
    f.run(f"{SESSION}.session.playing && await {SESSION}.stopPlayback()")
    # The invitation, in place of the next step.
    f.wait("document.querySelector('[data-taste-ask]')", what="the free account's invitation")
    title = f.js(f"(await import('/js/project-title.js')).shownTitle({PROJECT})")
    f.wait_text(t(f, "sing.taste.body", {"title": title}))
    assert f.js("(await import('/js/asks.js')).readLedger().today") == "acct_phrase", "the ask didn't keep today's slot"
    f.shot("ask")
    # Not now: the line goes on, on your own.
    f.run("document.querySelector('[data-taste-not-now]').click()")
    f.wait(f"{PROJECT}.practice.progress?.[{key!r}]?.step === 'own'", what="on your own after Not now")
    assert f.js("!(await import('/js/asks.js')).askDue('acct_phrase')"), "Not now didn't put the ask away"
    # Back on Sing along's end later: one quiet line, not the invitation again.
    f.run(f"{SESSION}.session.practice.progress = {{ ...{SESSION}.session.practice.progress, [{key!r}]: {{ step: 'singAlong', phrases: {{ 0: 1 }} }} }}")
    f.wait("document.querySelector('[data-taste-quiet]')", what="the quiet line")
    assert not f.js("!!document.querySelector('[data-taste-ask]')"), "the invitation again after Not now"
    f.shot("quiet")
    # Its link is the sign-up, for the phrase, back to this song; the birth date says why it's asked.
    f.run("document.querySelector('[data-taste-quiet] button').click()")
    f.wait("location.pathname === '/signin' && new URLSearchParams(location.search).get('for') === 'phrase'", what="sign-up, for the phrase")
    assert f.js("new URLSearchParams(location.search).get('next')") == f"{project_path}", "sign-up doesn't come back to the song"
    f.wait_text(t(f, "auth.reason.phrase"))
    f.wait_text(t(f, "auth.born.why"))
    f.shot("signup")
    # Signed in (the mock's member): the same project, now the whole song, the first line heard already.
    f.run("sessionStorage.setItem('stackapella.mockAs', 'member')")
    f.go(f"{project_path}?api=mock&as=member&lang={LANG}", "document.querySelector('#app header')")
    f.wait(f"{PROJECT} && !{PROJECT}.taste", timeout=30, what="the whole song in place of the line")
    assert f.js(f"{PROJECT}.template.sections.flatMap((s) => s.phrases).length > 1"), "still only the first line"
    assert f.js(f"{SESSION}.session.parts.filter((p) => p.templatePartKey).length === (await import('/js/templates.js')).partsFor({PROJECT}.template, {PROJECT}.singers || undefined).length"), "a part missing from the whole song"
    progress = f.js(f"JSON.parse(JSON.stringify({PROJECT}.practice.progress?.[{key!r}] ?? null))")
    assert progress == {"step": "listen", "phrases": {"0": 1}}, f"the whole song's progress: {progress}"
    f.wait("document.querySelector('[data-coach]')", what="the coach on the whole song")
    f.shot("whole")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")


if __name__ == "__main__":
    run("taste", journey, sizes=("phone",))
