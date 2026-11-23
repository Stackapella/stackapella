"""The design language as pictures (Hillel, 2026-11-22: "a page of our design language for serious and game mode"):
/styleguide and /styleguide/game rendered full page at 1440 and 390 wide, saved as PNGs to $STYLEGUIDE_OUT (else the
flows' shots folder). Run with FLOW_SIZES=desktop FLOW_SKINS=serious; the widths are set here.
"""
import base64
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SHOTS, run  # noqa: E402

PAGES = (("serious", "/styleguide?api=mock&as=admin"), ("game", "/styleguide/game?api=mock&as=admin"))
WIDTHS = ((1440, 1, ""), (390, 2, " phone"))


def journey(f):
    out = Path(os.environ.get("STYLEGUIDE_OUT") or SHOTS)
    out.mkdir(parents=True, exist_ok=True)
    for name, path in PAGES:
        for width, scale, suffix in WIDTHS:
            f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 900, "deviceScaleFactor": scale, "mobile": width < 768})
            f.go(path, "document.querySelector('h1')")
            f.run("await document.fonts.ready")
            f.js("new Promise((r) => setTimeout(r, 1200))")  # images and the gems' first paint
            height = f.js("Math.ceil(document.documentElement.scrollHeight)")
            png = f.chrome.send("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": True, "clip": {"x": 0, "y": 0, "width": width, "height": height, "scale": 1}})["data"]
            target = out / f"Design language - {name}{suffix}.png"
            target.write_bytes(base64.b64decode(png))
            print(f"    wrote {target.name} ({width} x {height})")


if __name__ == "__main__":
    run("styleguide_png", journey)
