"""Home's picks with news (docs/retention-plan.md, section 4, idea 5): after the first week, one of the three songs picked
for the singer's range may be a song in season (carols until 26 December) or one opened since this device last looked
at the library, with a small tag; at most one, only in range, never a new band. Opening Browse all is looking.
Against the mock: its catalogue's Silent Night marked in season (templates.catalogue `season`).
"""
import json
import sys
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

UNTIL = (date.today() + timedelta(days=10)).isoformat()
SEASON = """(() => {
    const real = window.fetch.bind(window);
    window.fetch = async (input, init) => {
        const url = new URL(typeof input === 'string' ? input : input.url, location.href);
        if (url.pathname !== '/contract/examples/templates.catalogue.200.json') return real(input, init);
        const json = await (await real(input, init)).json();
        for (const entry of json.data) {
            entry.openedAt = '2026-01-01T09:00:00Z';
            entry.season = entry.id === 'silent-night' ? { key: 'carols', until: '%s' } : null;
        }
        return new Response(JSON.stringify(json), { headers: { 'Content-Type': 'application/json' } });
    };
    localStorage.setItem('stackapella.library.lookedAt', '2026-01-02T00:00:00Z');
})();""" % UNTIL

TAGS = "[...document.querySelectorAll('#home-songs [data-news-tag]')]"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": SEASON})
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]')")
    # Past the first week, with a range every part fits: Home shows three picks and Browse all.
    f.run("const { learning } = await import('/js/stores/learning.js'); learning.progress.startedAt = Date.now() - 30 * 86400000; learning.progress.range = { low: 36, high: 96 };")
    f.wait(f"{TAGS}.length === 1", what="one pick with news")
    assert f.js("document.querySelectorAll('#home-songs [data-song-card]').length") == 3, "three picks, no new band"
    tag = f.js(f"({{ kind: {TAGS}[0].dataset.news, words: {TAGS}[0].textContent.trim(), title: {TAGS}[0].closest('[data-song-card]').querySelector('[data-ui=cartridge-title]').textContent.trim() }})")
    assert tag == {"kind": "season", "words": t(f, "home.songs.news.carols"), "title": "Silent Night"}, tag
    f.run("document.querySelector('#band-songs').scrollIntoView({ block: 'start' })")
    f.shot("pick-carol")
    # Out of range: no tag, even in season.
    f.run("(await import('/js/stores/learning.js')).learning.progress.range = { low: 90, high: 96 };")
    f.wait(f"{TAGS}.length === 0", what="no tag on a song out of range")
    f.run("(await import('/js/stores/learning.js')).learning.progress.range = { low: 36, high: 96 };")
    f.wait(f"{TAGS}.length === 1", what="back in range")
    # Browse all is looking at the library: the clock moves on (the season's tag stays while it's in season).
    f.run("document.querySelector('[data-browse-all]').click()")
    f.wait("Date.parse(localStorage.getItem('stackapella.library.lookedAt')) > Date.now() - 60000", what="looked at the library")
    assert f.js(f"{TAGS}.length") == 0, "no tag in the full library"


if __name__ == "__main__":
    run("home_news", journey)
