"""/songs leads with the festival coming up (App\\Support\\Pages\\Festivals, js/public/coming-up.js; Hillel, 2026-11-12:
"have the main songs page automatically highlight which section is coming up next"): on the device's own date, the
festival on now or nearest as its card in its collection's tone, a second as a smaller line, and that collection's panel
first; nothing near, no card and the panels in their usual order. Only live collections take part (no American songs page
is open, so mid-July has nothing). Needs the real server (a site page) on a date before 13 November or after,
as the cards the server renders are those opening within 60 days of its own day."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Date and time, from the page's first script on, as if it were this local moment (the clock still runs; as hanukkiah.py).
FAKE_NOW = """(() => {{ const Real = Date; const start = new Real({year}, {month}, {day}, {hour}, {minute}).getTime() - Real.now();
  class Fake extends Real {{ constructor(...a) {{ super(...(a.length ? a : [Real.now() + start])); }} static now() {{ return Real.now() + start; }} }}
  window.Date = Fake; }})();"""

LOOK = """JSON.stringify((() => {
  const box = document.querySelector('[data-coming-up]');
  const shown = box ? [...box.querySelectorAll('[data-festival]')].filter((c) => c.getClientRects().length) : [];
  const card = shown[0];
  const also = box ? [...box.querySelectorAll('[data-also]')].find((p) => p.getClientRects().length) : null;
  const text = (e) => e && e.getClientRects().length ? e.textContent.trim() : null;
  return {
    box: !!box && !box.hidden, cards: shown.length, key: card?.dataset.key ?? null,
    kick: text(card?.querySelector('[data-kick]')), when: text(card?.querySelector('[data-when]')), until: text(card?.querySelector('[data-until]')),
    songs: card ? card.querySelectorAll('.s-jcount-songs a').length : 0, link: card?.querySelector('.s-jcount-all')?.getAttribute('href') ?? null,
    also: also ? also.textContent.replace(/\\s+/g, ' ').trim() : null,
    panels: [...document.querySelectorAll('.s-sh-grid > [data-band]')].map((p) => p.dataset.band),
    orders: [...document.querySelectorAll('.s-sh-grid > [data-band]')].map((p) => Number(p.dataset.order)),
  };
})())"""


def songs_on(f, when):
    y, m, d, h = when
    script = f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": FAKE_NOW.format(year=y, month=m - 1, day=d, hour=h, minute=0)})["identifier"]
    try:
        f.go("/songs", "document.querySelector('.s-sh-grid')")
        f.wait("document.readyState === 'complete' && document.fonts.status === 'loaded'", what="the page and its scripts")
        assert f.js("new Date().getDate()") == d, "the device's date wasn't set"
        return json.loads(f.js(LOOK))
    finally:
        f.chrome.send("Page.removeScriptToEvaluateOnNewDocument", {"identifier": script})


def journey(f):
    # Thanksgiving's hymns opened after this flow was written: on 20 November it leads, Hanukkah as the line under it.
    nov = songs_on(f, (2026, 11, 20, 12))
    assert nov["box"] and nov["cards"] == 1 and nov["key"] == "thanksgiving", f"20 Nov: Thanksgiving's card: {nov}"
    assert nov["kick"] == "Coming up" and nov["when"] == "In 6 days" and nov["until"] is None, f"20 Nov: how soon: {nov}"
    assert nov["songs"] >= 1 and nov["link"] == "/songs/thanksgiving-hymns", f"20 Nov: its songs and its page: {nov}"
    assert nov["also"] and "Hanukkah" in nov["also"] and "In 14 days" in nov["also"], f"20 Nov: Hanukkah as the line: {nov}"
    assert nov["panels"][0] == "thanksgiving-hymns", f"20 Nov: the Thanksgiving hymns' panel first: {nov['panels']}"
    f.no_sideways("20 November")
    f.shot("20-nov")

    dec = songs_on(f, (2026, 12, 10, 12))
    assert dec["key"] == "hanukkah" and dec["kick"] == "On now" and dec["when"] is None and dec["until"], f"10 Dec: Hanukkah on: {dec}"
    assert dec["also"] and "Christmas" in dec["also"] and "In 15 days" in dec["also"], f"10 Dec: Christmas near: {dec}"
    assert dec["panels"][0] == "jewish-songs", f"10 Dec: {dec['panels']}"
    f.shot("10-dec")

    # The Scottish songs are open now, so New Year leads on 28 December.
    ny = songs_on(f, (2026, 12, 28, 12))
    assert ny["box"] and ny["key"] == "new-year" and ny["link"] == "/songs/scottish-songs", f"28 Dec: New Year's card: {ny}"
    assert ny["panels"][0] == "scottish-songs", f"28 Dec: the Scottish songs' panel first: {ny['panels']}"
    f.no_sideways("28 December")
    f.shot("28-dec")

    quiet = songs_on(f, (2027, 7, 15, 12))
    # No American songs page is open, so Independence Day takes no part: nothing near.
    assert not quiet["box"] and quiet["cards"] == 0 and quiet["also"] is None, f"15 Jul: nothing near: {quiet}"
    assert quiet["orders"] == sorted(quiet["orders"]), f"15 Jul: the panels in their usual order: {quiet}"
    f.no_sideways("15-jul")
    f.shot("15-jul")


run("coming_up", journey)
