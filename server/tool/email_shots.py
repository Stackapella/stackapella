"""Photographs every email `php artisan app:emails:render <dir>` wrote: a desktop (680 px) and a phone
(390 px) picture of each, light and dark, next to its .html, and a "Pictures:" line in index.md.

    cd server && php artisan app:emails:render ../tmp/emails && python3 -I tool/email_shots.py ../tmp/emails [names...]

Names (like mudeo-new or verify) photograph only those; none, every one.

Needs Google Chrome (driven by web/tool/cdp.py, the standard library only). Dark is the reader's
prefers-color-scheme, as Apple Mail and Gmail on iOS apply it; the emails' own dark styles decide the rest.
"""
import base64
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "web" / "tool"))
from cdp import Chrome  # noqa: E402

SIZES = {"desktop": (680, False), "phone": (390, True)}


def shoot(chrome, html, out, dark):
    chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-color-scheme", "value": "dark" if dark else "light"}]})
    chrome.goto(html.resolve().as_uri())
    height = int(chrome.evaluate("Math.ceil(document.documentElement.scrollHeight)"))
    width = int(chrome.evaluate("document.documentElement.clientWidth"))
    data = chrome.send("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": True,
                                                  "clip": {"x": 0, "y": 0, "width": width, "height": height, "scale": 1}})["data"]
    out.write_bytes(base64.b64decode(data))


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    folder = Path(sys.argv[1])
    only = set(sys.argv[2:])
    pages = [p for p in sorted(folder.glob("*.html")) if not only or p.stem.split("-", 1)[1] in only]
    if not pages:
        sys.exit(f"No emails in {folder}: run php artisan app:emails:render first.")

    for size, (width, mobile) in SIZES.items():
        chrome = Chrome(width=width, height=900, mobile=mobile)
        try:
            for page in pages:
                for dark in (False, True):
                    shoot(chrome, page, folder / f"{page.stem}-{size}{'-dark' if dark else ''}.png", dark)
        finally:
            chrome.close()

    index = folder / "index.md"
    if index.exists():
        text = index.read_text()
        for page in pages:
            pictures = ", ".join(f"[{n}]({n})" for n in (f"{page.stem}-{s}{d}.png" for s in SIZES for d in ("", "-dark")))
            text = re.sub(rf"(- \*\*Files:\*\* \[{re.escape(page.stem)}\.html\][^\n]*)", rf"\1\n- **Pictures:** {pictures}", text, count=1)
        index.write_text(text)
    print(f"Photographed {len(pages)} emails in {folder}.")


if __name__ == "__main__":
    main()
