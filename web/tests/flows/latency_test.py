"""The latency test for /tools (js/public/tools/latency-test.js) on the tools' one layout, in the frame
(web/tests/latency-demo.html). Tap along first: Start, pinned, plays a steady click on the audio clock and the
panel becomes the pad; taps 60 ms after each click come back as about 60 ms, Late, with what to do. Then the
more exact way behind ⋯: on Bluetooth clap along (its words; a clapper is timing_clap's), on Wired the mic hears the
clicks (the app's timing check, over loopback: near zero, Tight), and the visit's two results sit side by side. Start and the pad above the fold at 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#latency [data-ui=tool]')"
L = "window.__latency"
PRIMARY = f"{TOOL}.querySelector('[data-ui=tool-primary]')"


def journey(f):
    f.go("/tests/latency-demo.html?loopback", f"{L} && document.querySelector('header [data-theme-toggle]')")
    # A fresh start: the route a run before remembered (tool-prefs) isn't this one's.
    f.run("localStorage.removeItem('stackapella.tools.audio-latency-test')")
    f.go("/tests/latency-demo.html?loopback", f"{L} && document.querySelector('header [data-theme-toggle]')")
    f.no_sideways("the latency test")
    assert f.js(f"{TOOL}.querySelectorAll('[data-route]').length") == 3, "three ways to listen"
    assert f.js(f"{PRIMARY}.getBoundingClientRect().bottom <= innerHeight + 1"), "Start above the fold"
    f.shot("ready")
    # Tap along: the clicks run on the audio clock; the test taps 60 ms after each (as a steady person would).
    f.run(f"{PRIMARY}.click()")
    f.wait(f"{TOOL}.dataset.phase === 'tapping' && {L}.clickTimes().length", what="the clicks")
    assert f.js(f"{TOOL}.querySelector('[data-pad]').getBoundingClientRect().height > 60"), "the pad fills the panel"
    f.shot("tapping")
    f.run(f"""for (let i = 0; i < 8; i++) {{
        const at = {L}.clickTimes()[i] + 60 + (i % 2 ? 4 : -4);
        await new Promise((r) => setTimeout(r, Math.max(0, at - performance.now())));
        {L}.tap(at);
    }}""")
    f.wait(f"{TOOL}.dataset.phase === 'done'", timeout=15, what="the tapped result")
    result = f.js("window.__result")
    print(f"    tapped: {result}")
    assert result["method"] == "tap" and 50 <= result["ms"] <= 70 and result["verdict"] == "late", result
    assert f.js(f"{TOOL}.querySelectorAll('[data-what-now-list] li').length") == 3, "what to do with it"
    assert f.js(f"{TOOL}.querySelector('[data-unit]').textContent").startswith("ms ±"), "a spread"
    assert f.js(f"{TOOL}.querySelector('[data-ui=tool-status]').textContent").strip().startswith("Late"), "the verdict"
    f.no_sideways("the tapped result")
    f.shot("tapped")
    # More exact on Bluetooth is clap along (earbuds stop playing out of the ear), with the AirPods tip; a clapper is
    # the app's own flow's (timing_clap) and the module's tests', so here its words.
    f.run(f"{TOOL}.querySelector('[data-route=bluetooth]').click()")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    f.wait(f"{TOOL}.querySelector('[data-ui=tool-sheet]').open", what="the sheet")
    assert f.js(f"{TOOL}.querySelector('[data-mic-start]').textContent") == "Start the clicks", "Bluetooth's check is clap along"
    assert f.js(f"!{TOOL}.querySelector('[data-clap-tip]').hidden"), "the AirPods tip"
    f.shot("sheet-bluetooth")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-sheet]').close?.()")
    # Wired: the mic hears the clicks (the app's timing check, over loopback).
    f.run(f"{TOOL}.querySelector('[data-route=wired]').click()")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    f.wait(f"{TOOL}.querySelector('[data-ui=tool-sheet]').open", what="the sheet")
    assert f.js(f"{TOOL}.querySelector('[data-mic-start]').textContent") == "Start listening", "wired: the click check"
    f.shot("sheet")
    f.run(f"{TOOL}.querySelector('[data-mic-start]').click()")
    f.wait(f"window.__result.method === 'mic' || {TOOL}.dataset.phase === 'failed'", timeout=40, what="the mic's result")
    assert f.js(f"{TOOL}.dataset.phase") == "done", f.js(f"{TOOL}.innerText")
    result = f.js("window.__result")
    print(f"    by mic: {result}")
    assert result["route"] == "wired" and 0 <= result["ms"] < 25 and result["verdict"] in ("tight", "fine"), result
    assert f.js(f"{TOOL}.querySelectorAll('[data-visit-list] li').length") == 2, "this visit, side by side"
    f.no_sideways("the mic's result")
    f.shot("result")
    f.run(f"{L}.stop()")


if __name__ == "__main__":
    run("latency_test", journey, sizes=("phone", "se", "desktop"))
