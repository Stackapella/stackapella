"""The free tools' variant presets (docs/seo-growth-plan.md, item 3), on web/tests/tool-variants-demo.html: the ukulele and
violin tuners open on that instrument, the voice tuner on Voice, /tools/tanpura's drone in tanpura mode, and the A432 page's
forks with A432 chosen and its set on the shelf. A preset is for the visit: the person's saved settings stay as they were,
and a setting they change is saved as theirs. Both skins (FLOW_SKINS=game,serious)."""
import json
import sys
import urllib.parse
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PRESSED = "document.querySelector('[data-ui=tool-modes] [data-segment={}]')?.getAttribute('aria-pressed') === 'true'"


def stored(f, slug):
    """The tool's saved settings: the shared store's record ({v, updatedAt, settings}) or an old bare object."""
    record = f.js(f"JSON.parse(localStorage.getItem('stackapella.tools.{slug}') || 'null')")
    return record["settings"] if isinstance(record, dict) and isinstance(record.get("settings"), dict) else record


def mount(f, tool, preset, saved=None):
    """The variant page, with `saved` as the person's own settings for the tool beforehand."""
    f.go("/tests/tool-variants-demo.html", "document.body")
    slug = tool
    f.run(f"localStorage.removeItem('stackapella.tools.{slug}')" if saved is None else f"localStorage.setItem('stackapella.tools.{slug}', {json.dumps(json.dumps(saved))})")
    f.go(f"/tests/tool-variants-demo.html?tool={tool}&preset={urllib.parse.quote(json.dumps(preset))}", "window.__ready")


def journey(f):
    # The ukulele tuner, for someone who keeps the tuner on Voice: it opens on the ukulele, their Voice stays saved.
    mine = {"v": 1, "updatedAt": 1, "settings": {"mode": "voice", "instrument": "chromatic", "naming": "letters"}}
    mount(f, "tuner", {"instrument": "ukulele"}, mine)
    assert f.js(PRESSED.format("instrument")), "the ukulele tuner opens on Instrument"
    assert f.js("document.querySelector('[data-instrument]').value") == "ukulele", "on the ukulele"
    assert f.js("document.querySelector('[data-strings]')?.textContent.replace(/\\s+/g, ' ')") is not None
    f.shot("tuner-ukulele")
    f.run("document.querySelector('[data-namings] [data-segment=\"fixed-do\"]').click()")
    after = stored(f, "tuner")
    assert after["naming"] == "fixed-do" and after["mode"] == "voice" and after["instrument"] == "chromatic", f"the preset was saved over theirs: {after}"
    # Changing the instrument makes it theirs.
    f.run("(() => { const s = document.querySelector('[data-instrument]'); s.value = 'violin'; s.dispatchEvent(new Event('change')); })()")
    assert stored(f, "tuner")["instrument"] == "violin", "a change the person makes is saved"

    # The violin tuner with nothing saved, and the voice tuner for someone on Instrument.
    mount(f, "tuner", {"instrument": "violin"})
    assert f.js("document.querySelector('[data-instrument]').value") == "violin" and f.js(PRESSED.format("instrument")), "the violin tuner"
    mount(f, "tuner", {"mode": "voice"}, {"v": 1, "updatedAt": 1, "settings": {"mode": "instrument", "instrument": "cello"}})
    assert f.js(PRESSED.format("voice")), "the voice tuner opens on Voice"
    assert stored(f, "tuner")["mode"] == "instrument", "their Instrument stays saved"
    f.shot("tuner-voice")
    # An unknown instrument changes nothing.
    mount(f, "tuner", {"instrument": "theremin"})
    assert f.js("document.querySelector('[data-instrument]').value") == "chromatic" and f.js(PRESSED.format("voice")), "an unknown preset is ignored"

    # /tools/tanpura: the drone in tanpura mode, the person's drone mode kept until they pick one.
    mount(f, "drone", {"mode": "tanpura"}, {"mode": "drone", "volume": 0.5})
    assert f.js(PRESSED.format("tanpura")), "the tanpura page opens in tanpura mode"
    assert f.js("!document.querySelector('[data-firsts]').hidden && document.querySelector('[data-sounds]').hidden"), "tanpura's controls showing"
    f.shot("tanpura")
    f.run("document.querySelector('[data-ui=tool-modes] [data-segment=drone]').click()")
    f.run("document.querySelector('[data-ui=tool-modes] [data-segment=tanpura]').click()")
    assert stored(f, "drone")["mode"] == "tanpura", "picking a mode makes it theirs"
    mount(f, "drone", {"mode": "tanpura"}, {"mode": "drone"})
    f.run("document.querySelector('[data-ui=tool-modes] [data-segment=drone]').click()")
    assert stored(f, "drone")["mode"] == "drone"

    # The A432 page: A432 chosen, its set on the shelf in order, the rest under More.
    mount(f, "tuning-forks", {"first": "a432", "shelf": ["a432", "c256", "a440", "nope"]})
    shelf = f.js("[...document.querySelectorAll('[data-shelf] [data-fork]')].map((c) => c.dataset.fork)")
    more = f.js("[...document.querySelectorAll('[data-more-shelf] [data-fork]')].map((c) => c.dataset.fork)")
    assert shelf == ["a432", "c256", "a440"], f"the shelf: {shelf}"
    assert sorted(more[:2]) == ["a435", "a442"], f"the rest under More: {more}"
    assert f.js("document.querySelector('[data-fork-name]').textContent") == "A432", "A432 chosen first"
    assert "f=" not in f.js("location.search.replace(/preset=[^&]*/, '')"), "the address names no fork for the page's own first"
    f.no_sideways("the A432 forks")
    f.shot("forks-a432")


if __name__ == "__main__":
    run("tool_variants", journey)
