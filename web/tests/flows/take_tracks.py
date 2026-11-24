"""Learning tracks to take away (Hillel's approved build plan, 2026-11-23, brief C): a library song's practice track,
your part louder or without it, is an MP3 with a free account. A part page's link (?take=louder&part=) and the song
sheet's Take it with you open the studio's export on that track; a guest meets the account ask in the song's words; a
free member makes the MP3 with no Plus in the way. Your own songs, every part and choir songs keep their gates.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

CATCH = """window.downloads = [];
HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloads.push(this.download); };"""
EXPORT = "document.querySelector('[data-export]')"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def opened_on_track(f):
    f.wait(f"{EXPORT} && {EXPORT}.querySelector('input[name=what][value=track]')?.checked", timeout=30, what="the export, on the practice track")
    assert f.js(f"{EXPORT}.querySelector('input[value=louder]').checked"), "not your part louder"
    assert f.js(f"[...{EXPORT}.querySelectorAll('[role=radio]')].find((b) => b.textContent.trim().startsWith('MP3'))?.getAttribute('aria-checked')") == "true", "not MP3"
    assert not f.js(f"[...{EXPORT}.querySelectorAll('[role=radio]')].some((b) => b.textContent.includes({json.dumps(t(f, 'home.plus'))}))"), "a Plus chip on the free track"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    clear_projects(f)

    # A guest from a part page: the account ask, in the song's words, and the way back to this very track.
    f.go("/?api=mock&scenario=&as=signedOut&song=amazing-grace&take=louder&part=alto", "location.pathname.startsWith('/p/')")
    opened_on_track(f)
    ask = f.js("document.querySelector('[data-export-account]')?.textContent.trim() ?? ''")
    assert "Amazing Grace" in ask and "alto" in ask, ask
    assert not f.js("document.querySelector('[data-export-plus]')"), "Plus asked of a free track"
    # The foot in view on opening: the format, the ask and its button, not the list of choices.
    # After the credits line has come in (it arrives late and pushed the button out of view in the first shots).
    f.wait("[...document.querySelectorAll('[data-export] p')].some((p) => /Sarah/.test(p.textContent))", what="the credits line")
    f.run("await new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done)));")
    f.wait("(() => { const b = document.querySelector('[data-export] [type=submit]').getBoundingClientRect(); return b.bottom <= innerHeight && b.top >= 0; })()", what="the button in view")
    f.shot("guest-ask")
    f.run(f"{EXPORT}.requestSubmit()")
    f.wait("location.pathname === '/signin' && location.search.includes('for=take')", what="the free account, then back")
    back = f.js("decodeURIComponent(new URLSearchParams(location.search).get('next'))")
    assert "take=louder" in back and "part=alto" in back, back

    # A free member from the song's sheet: Take it with you, then the MP3 made.
    f.go("/?api=mock&scenario=&as=member&song=amazing-grace", "document.querySelector('[role=dialog] [data-song-take]')")
    f.shot("sheet-take")
    f.run("document.querySelector('[data-song-take]').click()")
    f.wait("location.pathname.startsWith('/p/')", what="the studio")
    opened_on_track(f)
    assert not f.js("document.querySelector('[data-export-account], [data-export-plus]')"), "an ask on a member's free track"
    f.run(CATCH)
    f.run(f"{EXPORT}.requestSubmit()")
    f.wait("window.downloads.length === 1", timeout=90, what="the practice track's MP3")
    assert f.js("window.downloads[0]").endswith(".mp3"), f.js("window.downloads[0]")
    # The whole song as MP3 is still Plus's.
    f.go(f"{f.js('location.pathname')}?take=without&part=soprano", "document.querySelector('[data-export]')")
    f.run(f"{EXPORT}.querySelector('input[value=song]').click()")
    f.wait("document.querySelector('[data-export-plus]')", what="the whole song as MP3 still Plus")
    f.shot("song-mp3-plus")


if __name__ == "__main__":
    run("take_tracks", journey)
