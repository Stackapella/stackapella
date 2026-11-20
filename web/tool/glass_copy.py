"""The pages with a sheet of their own carry the app's glass: css/landing.css (the home page) and css/your-choir.css
(Hear yourself as a choir) link no other sheet but the Game mode switch's, so each carries a copy of css/glass.css, from
its gem-spec marker to its end, between its own glass markers. This writes the copies; tests/glass.test.js fails when
one differs. Run from web/: python3 -I tool/glass_copy.py"""
from pathlib import Path

CSS = Path(__file__).resolve().parent.parent / "css"
START, END = "/* glass:start (tool/glass_copy.py writes this from css/glass.css; never edit it here) */", "/* glass:end */"


def block():
    glass = (CSS / "glass.css").read_text()
    at = glass.index("/* gem-spec:start */")
    return glass[at:].rstrip() + "\n"


SHEETS = ("landing.css", "your-choir.css")


def main():
    for name in SHEETS:
        path = CSS / name
        text = path.read_text()
        a, b = text.index(START), text.index(END)
        new = text[: a + len(START)] + "\n" + block() + text[b:]
        if new != text:
            path.write_text(new)
            print(f"{name}: the glass copy written")
        else:
            print(f"{name}: the glass copy up to date")


if __name__ == "__main__":
    main()
