"""The song library's filters (Hillel, 2026-11-12: Browse all is "unwieldy, maybe add filtering like we have on the
songs page by category"): over the open library, a title search and the part you sing, then the categories as chips
with their counts, the one in season first; the library by category with a heading each, or A to Z once a chip, a part
or a search narrows it; nothing found says so with the way back to every song. Against the mock, with a bigger
catalogue: copies of its songs under other titles and genres, one in season and one with its Spanish title.
"""
import json
import sys
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

UNTIL = (date.today() + timedelta(days=10)).isoformat()
# id, title, genres, Spanish title, the alto's lowest note (MIDI)
SONGS = [
    ("silent-night", "Silent Night", ["christmas", "carol"], "Noche de paz", 57),
    ("deck-the-hall", "Deck the Hall", ["christmas", "carol"], None, 57),
    ("o-come", "O Come All Ye Faithful", ["christmas", "hymn"], None, 57),
    ("abide-with-me", "Abide with Me", ["hymn"], None, 57),
    ("be-thou", "Be Thou My Vision", ["hymn"], None, 57),
    ("steal-away", "Steal Away", ["spiritual"], None, 50),
    ("michael-row", "Michael Row the Boat Ashore", ["spiritual", "folk"], None, 57),
    ("dona-nobis", "Dona Nobis Pacem", ["round", "latin"], None, 57),
    ("shenandoah", "Shenandoah", ["folk"], None, 40),
    ("wellerman", "The Wellerman", ["sea shanty", "folk"], None, 57),
    ("drunken-sailor", "Drunken Sailor", ["sea shanty"], None, 57),
    ("hava-nagila", "Hava Nagila", ["jewish", "celebration"], None, 57),
    ("ave-verum", "Ave Verum Corpus", ["classical", "latin"], None, 57),
    ("goodnight", "Goodnight, Sweetheart", [], None, 57),
]
CATALOGUE = """(() => {
    const songs = %s;
    const real = window.fetch.bind(window);
    window.fetch = async (input, init) => {
        const url = new URL(typeof input === 'string' ? input : input.url, location.href);
        if (url.pathname !== '/contract/examples/templates.catalogue.200.json') return real(input, init);
        const json = await (await real(input, init)).json();
        const base = json.data[0];
        json.data = songs.map(([id, title, genre, es, low]) => ({ ...base, id, title, genre, titles: es ? { es } : {}, access: 'free',
            season: id === 'silent-night' ? { key: 'carols', until: '%s' } : null,
            parts: base.parts.map((p) => p.key === 'alto' ? { ...p, range: { low, high: low + 12 } } : p) }));
        return new Response(JSON.stringify(json), { headers: { 'Content-Type': 'application/json' } });
    };
})();""" % (json.dumps(SONGS), UNTIL)

CARD_TITLES = "[...document.querySelectorAll('#home-songs [data-song-card] [data-ui=cartridge-title]')].map((e) => e.textContent.trim())"
HEADINGS = "[...document.querySelectorAll('#home-songs [data-library-heading]')].map((e) => e.dataset.libraryHeading)"
CHIPS = "[...document.querySelectorAll('[data-library-group]')].map((e) => e.dataset.libraryGroup)"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def open_library(f, query=""):
    f.go(f"/?api=mock&scenario=&as=member{query}", "document.querySelector('#home-songs [data-song-card]')")
    f.run("sessionStorage.removeItem('stackapella.libraryFilters')")
    # Past the first week (three picks and Browse all), with a range only some altos fit.
    f.run("const { learning } = await import('/js/stores/learning.js'); learning.progress.startedAt = Date.now() - 30 * 86400000;")
    # The library stays open for the tab once opened (a reload keeps it): Browse all only when it's shut.
    f.wait("document.querySelector('[data-browse-all], [data-library-filters]')", what="Browse all")
    f.run("document.querySelector('[data-library-filters]') || document.querySelector('[data-browse-all]').click()")
    f.wait("document.querySelector('[data-library-filters]')", what="the library's filters")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": CATALOGUE})
    open_library(f)
    total = f.js("document.querySelectorAll('#home-songs [data-song-card]').length")
    assert total >= len(SONGS), f"{total} cards for {len(SONGS)} catalogue songs"
    f.js("document.querySelector('#band-songs').scrollIntoView({ block: 'start' })")
    f.shot("open")

    # The chips: All, then the groups with their counts, the one in season first; the list by group, a heading each.
    chips = f.js(CHIPS)
    assert chips[0] == "" and chips[1] == "christmas", f"chips: {chips}"
    assert f.js("document.querySelector('[data-library-group=christmas]').hasAttribute('data-season')"), "the season isn't marked"
    assert f.js(HEADINGS)[0] == "christmas", f"headings: {f.js(HEADINGS)}"
    assert set(f.js(HEADINGS)) == set(chips[1:]), f"a heading for each chip: {f.js(HEADINGS)} vs {chips}"
    count_of = lambda key: f.js(f"+document.querySelector('[data-library-group={key}] span').textContent")  # noqa: E731
    # Each group's count is the cards under its heading (the app's own bundled songs join the catalogue's).
    under = f.js("""(() => { const out = {}; let at = null; for (const li of document.querySelectorAll('#home-songs > li')) {
        if (li.dataset.libraryHeading) at = li.dataset.libraryHeading; else if (at && li.matches('[data-song-card]')) out[at] = (out[at] ?? 0) + 1; } return out; })()""")
    assert all(under.get(key) == count_of(key) for key in chips[1:]), f"counts {[(k, count_of(k)) for k in chips[1:]]} vs cards {under}"
    assert count_of("rounds") >= 1 and count_of("sea-shanties") >= 2 and count_of("christmas") >= 3, "group counts"
    if f.size == "phone":
        # One row that scrolls sideways (design rules: never one chip alone on a second line).
        tops = f.js("[...document.querySelectorAll('[data-library-group]')].map((e) => Math.round(e.getBoundingClientRect().top))")
        assert len(set(tops)) == 1, f"the chips wrap: {tops}"
        assert f.js("document.documentElement.scrollWidth <= innerWidth + 1"), "the page scrolls sideways"

    # A chip: that group alone, A to Z, no headings; the same chip again is everything again.
    f.run("document.querySelector('[data-library-group=hymns]').click()")
    f.wait("document.querySelector('[data-library-group=hymns]').getAttribute('aria-pressed') === 'true'", what="Hymns chosen")
    titles = f.js(CARD_TITLES)
    assert {"Abide with Me", "Be Thou My Vision"} <= set(titles) and "Steal Away" not in titles and len(titles) == count_of("hymns"), titles
    assert titles == sorted(titles, key=lambda x: x.lower()), f"not A to Z: {titles}"
    assert not f.js(HEADINGS), "headings in a filtered list"
    assert f.js("(() => { const row = document.querySelector('[data-library-groups]').getBoundingClientRect(); const chip = document.querySelector('[data-library-group=hymns]').getBoundingClientRect(); return chip.left >= row.left - 1 && chip.right <= row.right + 1; })()"), "the chosen chip is out of sight"
    f.shot("hymns")
    f.run("document.querySelector('[data-library-group=hymns]').click()")
    f.wait(f"{HEADINGS}.length > 1", what="every group again")

    # Search: by title, any case and accent; nothing found says so and offers every song again.
    f.run("const i = document.querySelector('[data-library-search]'); i.value = 'SAILOR'; i.dispatchEvent(new Event('input'));")
    f.wait(f"{CARD_TITLES}.length === 1", what="one match")
    assert f.js(CARD_TITLES) == ["Drunken Sailor"], f.js(CARD_TITLES)
    f.run("const i = document.querySelector('[data-library-search]'); i.value = 'zzzz'; i.dispatchEvent(new Event('input'));")
    f.wait("document.querySelector('[data-library-none]')", what="nothing found")
    f.shot("none")
    f.run("document.querySelector('[data-library-none] button').click()")
    f.wait(f"{CARD_TITLES}.length >= {total} && !document.querySelector('[data-library-search]').value", what="every song again")

    # I sing: with a measured range, only the songs whose alto fits it (Sing moves the key up to 7 semitones).
    f.run("const { learning } = await import('/js/stores/learning.js'); learning.progress.range = { low: 55, high: 72 };")
    f.run("const s = document.querySelector('[data-library-voice]'); s.value = 'alto'; s.dispatchEvent(new Event('change'));")
    f.wait(f"!{CARD_TITLES}.includes('Shenandoah')", what="the altos out of range gone")
    titles = f.js(CARD_TITLES)
    assert "Steal Away" in titles and "Silent Night" in titles and "Shenandoah" not in titles, titles
    assert titles == sorted(titles, key=lambda x: x.lower()), f"not A to Z: {titles}"
    # Kept for the tab: a reload opens the library as it was left.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-library-filters]')")
    assert f.js("document.querySelector('[data-library-voice]').value") == "alto", "the part wasn't kept"
    f.run("const s = document.querySelector('[data-library-voice]'); s.value = ''; s.dispatchEvent(new Event('change'));")

    # In Spanish: the song's Spanish title shows and is found.
    open_library(f, "&lang=es")
    f.run("const i = document.querySelector('[data-library-search]'); i.value = 'noche'; i.dispatchEvent(new Event('input'));")
    f.wait(f"{CARD_TITLES}.length === 1", what="Noche de paz found")
    assert f.js(CARD_TITLES) == ["Noche de paz"], f.js(CARD_TITLES)
    f.run("const i = document.querySelector('[data-library-search]'); i.value = ''; i.dispatchEvent(new Event('input'));")
    f.js("document.querySelector('#band-songs').scrollIntoView({ block: 'start' })")
    f.shot("open-es")


if __name__ == "__main__":
    run("library_filters", journey)
