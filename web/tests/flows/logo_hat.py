"""The logo's Santa hat (components/icon.js Logo; public/season.js hatDay; Hillel, 2026-11-10): on the device's own
Christmas Eve and Christmas Day the app's logo wears the site's hat, and on any other day it doesn't. The hat never moves
the mark or the name, and the mark stays the name's previous sibling (logo_match measures it so). The device's date is
set before any page script runs."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Date, from the page's first script on, as if it were this local day at noon (the clock still runs).
FAKE_DAY = """(() => {{ const Real = Date; const start = new Real({year}, {month}, {day}, 12).getTime() - Real.now();
  class Fake extends Real {{ constructor(...a) {{ super(...(a.length ? a : [Real.now() + start])); }} static now() {{ return Real.now() + start; }} }}
  window.Date = Fake; }})();"""
LOGO = """JSON.stringify((() => {
  const word = document.querySelector('#app header [data-ui=wordmark]');
  const mark = word.previousElementSibling;
  const hat = document.querySelector('#app header [data-hat-christmas]');
  const box = (e) => { const b = e.getBoundingClientRect(); return [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)]; };
  return { mark: box(mark), word: box(word), markIsSvgBox: !!mark.querySelector('svg'), hat: hat && hat.getClientRects().length ? box(hat) : null };
})())"""


def logo_on(f, day, path="/?api=mock&as=member"):
    """The logo on `day` ((year, month from 0, date) or None for the real day)."""
    script = None
    if day:
        script = f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": FAKE_DAY.format(year=day[0], month=day[1], day=day[2])})["identifier"]
    try:
        f.go(path, "document.querySelector('#app header [data-ui=wordmark]')")
        f.wait("document.fonts.status === 'loaded'", what="fonts")
        if day:
            assert f.js("new Date().getMonth()") == day[1] and f.js("new Date().getDate()") == day[2], "the device's date wasn't set"
        return json.loads(f.js(LOGO))
    finally:
        if script:
            f.chrome.send("Page.removeScriptToEvaluateOnNewDocument", {"identifier": script})


def journey(f):
    plain = logo_on(f, (2026, 11, 23))
    assert plain["hat"] is None, f"a hat on 23 December: {plain}"
    for date in (24, 25):
        dressed = logo_on(f, (2026, 11, date))
        assert dressed["hat"], f"no hat on {date} December"
        assert dressed["markIsSvgBox"], "the mark isn't the name's previous sibling any more"
        assert dressed["mark"] == plain["mark"] and dressed["word"] == plain["word"], f"the hat moved the logo: {dressed} against {plain}"
        hat, mark = dressed["hat"], dressed["mark"]
        assert hat[0] < mark[0] < hat[0] + hat[2] and hat[1] < mark[1], f"the hat isn't over the mark's top-left corner: {dressed}"
        if date == 24:
            f.shot("home-christmas-eve")
    assert logo_on(f, (2026, 11, 26))["hat"] is None, "a hat on 26 December"
    # The sign-in page's logo too (views/auth.js), the same component.
    signin = logo_on(f, (2026, 11, 25), "/signin?api=mock&as=signedOut")
    assert signin["hat"], "no hat on the sign-in page's logo on Christmas Day"
    f.shot("signin-christmas")


if __name__ == "__main__":
    run("logo_hat", journey)
