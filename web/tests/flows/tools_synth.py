"""The synth tools sound, on their real pages (js/public/tools/pitch-pipe.js, tuning-forks.js, drone.js): in both skins,
on a phone and a desktop, each page gets ready, its first action makes the right sound (the pipe's held A4 and the A440
fork at 440 Hz, the drone at all), letting go or Stop quietens it, More opens, and nothing scrolls sideways. A probe
between the tools' sound and the speakers measures what they play. It runs against the server's own site:

    python3 -I tests/flows/tools_synth.py http://127.0.0.1:8412
"""
import json
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8412"

# Every node that reaches the speakers goes through one analyser first, so the flow hears what the page plays.
PROBE = """(() => {
  const connect = AudioNode.prototype.connect;
  window.__probe = null;
  AudioNode.prototype.connect = function (dest, ...rest) {
    if (dest instanceof AudioDestinationNode) {
      if (!window.__probe || window.__probe.context !== dest.context) {
        const a = dest.context.createAnalyser(); a.fftSize = 16384; connect.call(a, dest); window.__probe = a;
      }
      return connect.call(this, window.__probe, ...rest);
    }
    return connect.call(this, dest, ...rest);
  };
  try { localStorage.clear(); } catch (e) {}
})();"""
# The level and the strongest frequency now.
HEAR = """(() => { const a = window.__probe; if (!a) return JSON.stringify({ rms: 0, hz: 0 });
  const t = new Float32Array(a.fftSize); a.getFloatTimeDomainData(t); let sum = 0; for (const x of t) sum += x * x;
  const f = new Float32Array(a.frequencyBinCount); a.getFloatFrequencyData(f); let best = 1; for (let i = 1; i < f.length; i++) if (f[i] > f[best]) best = i;
  return JSON.stringify({ rms: Math.sqrt(sum / t.length), hz: best * a.context.sampleRate / a.fftSize }); })()"""
DOWN = "(el) => el.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, button: 0, pointerId: 1, pointerType: 'mouse' }))"
UP = "(el) => el.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, button: 0, pointerId: 1, pointerType: 'mouse' }))"

# Each tool: what starts its sound, the frequency it should be strongest at (None: any), and what quietens it.
TOOLS = {
    "pitch-pipe": (f"({DOWN})(document.querySelector('[data-note=\"9\"]'))", 440, f"({UP})(document.querySelector('[data-note=\"9\"]'))"),
    "tuning-forks": (f"({DOWN})(document.querySelector('[data-strike]'))", 440, None),
    "drone": ("document.querySelector('[data-ui=tool-primary]').click()", None, "document.querySelector('[data-ui=tool-primary]').click()"),
}


def listed(path):
    try:
        with urllib.request.urlopen(SITE + path):
            return True
    except urllib.error.HTTPError as error:
        if error.code == 404:
            return False
        raise


def hear(f):
    return json.loads(f.js(HEAR))


def check(f, tool, start, hz, stop):
    path = f"/tools/{tool}"
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": PROBE})
    f.go(path, "document.querySelector('[data-tool]')?.dataset.state === 'ready'")
    f.no_sideways(path)
    f.run(start)
    time.sleep(0.8)
    sound = hear(f)
    assert sound["rms"] > 0.005, f"{tool}: silent after its first action ({sound})"
    if hz is not None:
        assert abs(sound["hz"] - hz) < 6, f"{tool}: strongest at {sound['hz']:.1f} Hz, not {hz}"
    if stop is not None:
        f.run(stop)
        time.sleep(1.6)  # the release and the analyser's window
        quiet = hear(f)
        assert quiet["rms"] < sound["rms"] / 4, f"{tool}: still sounding after letting go or Stop ({quiet} against {sound})"
    f.run("document.querySelector('[data-ui=tool-more]').click()")
    time.sleep(0.3)
    f.no_sideways(f"{path} with More open")


# Each tool remembers its settings (Hillel: "make sure all of the tools remember the settings locally"; the shared
# tool-prefs.js store): a change, the page reloaded, still there as a versioned record; then More's Reset puts it back.
REMEMBER = {
    "pitch-pipe": ("document.querySelector('[data-timbre=\"oo\"]').click()", "document.querySelector('[data-timbre=\"oo\"]').getAttribute('aria-pressed') === 'true'"),
    "tuning-forks": ("document.querySelector('[data-latch]').click()", "document.querySelector('[data-latch]').getAttribute('aria-checked') === 'true'"),
    "drone": ("document.querySelector('[data-segment=\"tanpura\"]').click()", "document.querySelector('[data-segment=\"tanpura\"]').getAttribute('aria-pressed') === 'true'"),
}


def remembers(f, tool):
    change, stuck = REMEMBER[tool]
    key = json.dumps(f"stackapella.tools.{tool}")
    ready = "document.querySelector('[data-tool]')?.dataset.state === 'ready'"
    f.go(f"/tools/{tool}", ready)
    f.run(f"localStorage.removeItem({key}); window.__old = true; location.reload()")
    f.wait(f"!window.__old && {ready}", what=f"{tool}: a fresh start")
    f.run(change)
    f.wait(stuck, what=f"{tool}: changed")
    record = json.loads(f.js(f"localStorage.getItem({key}) || 'null'"))
    assert record and record.get("v") and record.get("updatedAt") and "settings" in record, f"{tool}: saved as a versioned record: {record}"
    f.run("window.__old = true; location.reload()")
    f.wait(f"!window.__old && {ready}", what=f"{tool}: reloaded")
    f.wait(stuck, what=f"{tool}: remembered after a reload")
    # More's Reset: back to the default, and forgotten.
    f.run("document.querySelector('[data-ui=tool-more]').click()")
    f.wait("!document.querySelector('[data-sheet-reset]').hidden", timeout=5, what=f"{tool}: Reset in More")
    f.run("document.querySelector('[data-sheet-reset]').click()")
    f.wait(f"!({stuck})", timeout=5, what=f"{tool}: Reset puts it back")
    f.run(f"localStorage.removeItem({key})")

def embedded(f, tool):
    """The tool in another site's frame (/embed/tools/<tool>): it mounts compact, fits the width, and carries the credit."""
    path = f"/embed/tools/{tool}"
    f.go(path, "document.querySelector('[data-embed-stage]')?.children.length > 0")
    f.wait("document.querySelector('[data-tool]')?.dataset.state === 'ready' || document.querySelector('[data-ui=tool-primary]')", timeout=10, what=f"{tool}: embedded and ready")
    f.no_sideways(path)
    assert f.js("[...document.querySelectorAll('a[target=_blank]')].some((a) => a.rel.includes('noopener') && a.textContent.trim())"), f"{tool}: the credit link"


def main():
    tools = {t: v for t, v in TOOLS.items() if listed(f"/tools/{t}")}
    if not tools:
        print("skip tools_synth (no synth tool is listed yet)")
        return
    failures = []
    for skin in ("serious", "game"):
        for size in ("phone", "desktop"):
            for tool, (start, hz, stop) in tools.items():
                f = flow.Flow(SITE, size, "tools_synth", skin=skin)
                try:
                    check(f, tool, start, hz, stop)
                    print(f"ok   tools_synth ({tool}, {skin}, {size})")
                except Exception as error:  # noqa: BLE001 (every tool, skin and size is tried)
                    failures.append(f"{tool} {skin} {size}: {error}")
                    print(f"FAIL tools_synth ({tool}, {skin}, {size}): {error}")
                finally:
                    try:
                        f.close()
                    except AssertionError as error:  # page errors
                        failures.append(f"{tool} {skin} {size}: {error}")
                        print(f"FAIL tools_synth ({tool}, {skin}, {size}): {error}")
            # Remembering, in a browser of its own: the sound probe above starts every page with empty storage.
            if skin == "serious" and size == "phone":
                for tool in tools:
                    f = flow.Flow(SITE, size, "tools_synth", skin=skin)
                    try:
                        remembers(f, tool)
                        print(f"ok   tools_synth ({tool} remembers its settings, and Reset)")
                    except Exception as error:  # noqa: BLE001
                        failures.append(f"{tool} remembers: {error}")
                        print(f"FAIL tools_synth ({tool} remembers): {error}")
                    finally:
                        f.close()
    # Embedded (the drone and the pitch pipe are embeddable), at a narrow and a wide frame.
    flow.SIZES.setdefault("frame300", (300, 640, True))
    flow.SIZES.setdefault("frame600", (600, 700, False))
    for tool in ("drone", "pitch-pipe"):
        if tool not in tools or not listed(f"/embed/tools/{tool}"):
            continue
        for size in ("frame300", "frame600"):
            f = flow.Flow(SITE, size, "tools_synth", skin="serious")
            try:
                embedded(f, tool)
                print(f"ok   tools_synth ({tool} embedded, {size})")
            except Exception as error:  # noqa: BLE001
                failures.append(f"{tool} embedded {size}: {error}")
                print(f"FAIL tools_synth ({tool} embedded, {size}): {error}")
            finally:
                f.close()
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
