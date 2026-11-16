"""A library song's sheet music in the app (js/views/score-sheet.js; docs/song-sheet-music-plan.md): where a song
page's "Download · free with an account" lands. A guest who follows a link gets the invitation, "Free with an account",
whose button comes back to the same link, and nothing is engraved or made before it. Signed in, the MusicXML link serves
a file that opens (every part a staff, its words, the licence), the MIDI one a MIDI file, the PDF link the browser's
print over the engraved full score, and "Parts" one part's own sheet. A song withheld where the singer is says so, with
no score and no file."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Every download is caught, to read back; print is counted, never opened.
CATCH = """
window.downloads = [];
HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloads.push({ href: this.href, name: this.download }); };
window.prints = 0; window.print = () => { window.prints += 1; };
"""
STATE = "document.querySelector('[data-score-sheet]')?.dataset.state"
SETTLED = "['guest', 'ready', 'withheld', 'failed'].includes(" + STATE + ")"


def journey(f):
    # A guest: the invitation, and the way back to this very link.
    f.go("/", "document.querySelector('#app header')")
    f.go("/t/amazing-grace/sheet?get=musicxml&ref=song-page&api=mock&as=signedOut", SETTLED)
    f.run(CATCH)
    assert f.js(STATE) == "guest", f"a guest is asked: {f.js(STATE)}"
    ask = f.js("document.querySelector('[data-score-ask]').textContent")
    assert "Free with an account" in ask, f"the ask is an invitation: {ask!r}"
    href = f.js("document.querySelector('[data-score-signup]').getAttribute('href')")
    assert "mode=signup" in href and "for=score" in href and "next=%2Ft%2Famazing-grace%2Fsheet%3Fget%3Dmusicxml" in href, f"sign-up comes back here: {href}"
    assert not f.js("document.querySelector('[data-score-paper]')") and f.js("window.downloads.length") == 0, "nothing is made for a guest"
    f.no_sideways("the ask")
    f.shot("ask")

    # Signed in: the file, at once, and it opens.
    f.go("/t/amazing-grace/sheet?get=musicxml&auto=0&api=mock&as=member", STATE + " === 'ready'")
    f.run(CATCH)
    f.wait("document.querySelectorAll('[data-score-systems] svg').length > 0", what="the engraved score")
    f.run("document.querySelector('[data-score-file=musicxml]').click()")
    f.wait("window.downloads.length === 1", what="the MusicXML")
    score = json.loads(f.js("""(async () => {
        const d = window.downloads[0]; const text = await (await fetch(d.href)).text();
        const doc = new DOMParser().parseFromString(text, 'application/xml');
        return JSON.stringify({ name: d.name, broken: !!doc.querySelector('parsererror'), parts: doc.querySelectorAll('part').length,
            lyrics: doc.querySelectorAll('lyric').length, rights: doc.querySelector('rights')?.textContent ?? '' });
    })()"""))
    assert score["name"] == "Amazing Grace.musicxml" and not score["broken"], f"a MusicXML file that opens: {score}"
    assert score["parts"] == 4 and score["lyrics"] > 20 and "CC BY-SA 4.0" in score["rights"], f"every part, its words, the licence: {score}"
    assert "Amazing Grace.musicxml" in f.js("document.querySelector('[data-score-sent]').textContent"), "the page says the download started"
    f.run("document.querySelector('[data-score-file=midi]').click()")
    f.wait("window.downloads.length === 2", what="the MIDI")
    midi = f.js("(async () => { const d = window.downloads[1]; const b = new Uint8Array(await (await fetch(d.href)).arrayBuffer()); return d.name + ' ' + String.fromCharCode(...b.slice(0, 4)); })()")
    assert midi == "Amazing Grace.mid MThd", f"a MIDI file: {midi}"

    # The full score: four staves a system with their words, on paper; Print is the browser's.
    first = "document.querySelector('[data-score-systems] svg')"
    assert f.js(f"{first}.querySelectorAll('[data-staff]').length") == 4, "the full score has every part"
    assert f.js(f"[...{first}.querySelectorAll('.eg-word')].some((w) => w.textContent === 'grace,')"), "the words are under the notes"
    assert f.js("getComputedStyle(document.querySelector('[data-score-paper]')).backgroundColor") == "rgb(255, 255, 255)", "paper is white in any skin"
    assert f.js("[...document.querySelectorAll('[data-score-systems] [data-print-system]')].length") >= 3, "a system a block, so none splits over two pages"
    f.run("document.querySelector('[data-score-print]').click()")
    assert f.js("window.prints") == 1, "Print opens the browser's print"
    f.no_sideways("the score")
    f.shot("score")

    # One part's own sheet.
    f.run("document.querySelector('[data-score-part=alto]').click()")
    f.wait(f"{first}.querySelectorAll('[data-staff]').length === 1", what="the alto's sheet")
    assert f.js(f"{first}.querySelector('[data-staff]').dataset.staff") == "alto", "the alto alone"
    f.shot("alto")

    # The links do it by themselves: PDF prints once the score is there, Parts opens on the part named.
    f.go("/", "document.querySelector('#app header')")
    f.run("window.addEventListener('beforeprint', () => 0)")
    f.go("/t/amazing-grace/sheet?get=parts&part=tenor&auto=0", STATE + " === 'ready'")
    f.wait("document.querySelector('[data-score-systems] [data-staff]')?.dataset.staff === 'tenor'", what="the tenor's sheet from the link")
    assert f.js("document.querySelector('[data-score-part=tenor]').getAttribute('aria-pressed')") == "true", "the tenor shows chosen"

    # Withheld where the singer is: why, and nothing else.
    f.expected_errors.append("451")
    f.run("sessionStorage.setItem('stackapella.scenario', 'templates.show:not_available_here')")
    f.go("/t/silent-night/sheet?get=pdf", SETTLED)
    f.run(CATCH)
    assert f.js(STATE) == "withheld", f"a withheld song is refused: {f.js(STATE)}"
    assert f.js("document.querySelector('[data-score-withheld]').textContent.trim().length") > 10, "it says why"
    assert not f.js("document.querySelector('[data-score-paper]')") and not f.js("document.querySelector('[data-score-file]')"), "no score and no file"
    assert f.js("window.prints") == 0 and f.js("window.downloads.length") == 0, "nothing prints or downloads"
    f.shot("withheld")
    f.run("sessionStorage.removeItem('stackapella.scenario')")


run("score_sheet", journey, sizes=("phone", "desktop"))
