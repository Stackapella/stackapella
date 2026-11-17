"""The home page's glass: css/landing.css is its own sheet (the home page links no other but the Game mode switch's),
so it carries a copy of css/glass.css, from its gem-spec marker to its end, between its own glass markers. This writes
the copy; tests/glass.test.js fails when it differs. Run from web/: python3 -I tool/glass_copy.py"""
from pathlib import Path

CSS = Path(__file__).resolve().parent.parent / "css"
START, END = "/* glass:start (tool/glass_copy.py writes this from css/glass.css; never edit it here) */", "/* glass:end */"


def block():
    glass = (CSS / "glass.css").read_text()
    at = glass.index("/* gem-spec:start */")
    return glass[at:].rstrip() + "\n"


def main():
    path = CSS / "landing.css"
    text = path.read_text()
    a, b = text.index(START), text.index(END)
    new = text[: a + len(START)] + "\n" + block() + text[b:]
    if new != text:
        path.write_text(new)
        print("landing.css: the glass copy written")
    else:
        print("landing.css: the glass copy up to date")


if __name__ == "__main__":
    main()
