"""A free tool's result turns the page's band into its next step, and a tool gets its data from the page (docs/tools-plan.md): after the range test finds a
range, the band's line says the range and its voice type, and its button opens the app already knowing the range
(?range=C3-A4, letter names; docs/retention-plan.md, idea 7) with the tool's ?ref= tag. It runs the manual entry (C3 to A4, a tenor), so no mic is needed, against the server's own site:

    python3 -I tests/flows/tools_result.py http://127.0.0.1:8412
"""
import sys
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8412"
PAGE = "/tools/vocal-range-test"


def listed(path=PAGE):
    try:
        with urllib.request.urlopen(SITE + path):
            return True
    except urllib.error.HTTPError as error:
        if error.code == 404:
            return False
        raise


def key_finder(failures):
    """The key finder gets the live library's songs from the page: the picker lists them, and picking one shows a
    link to learn it (ToolPageData)."""
    if not listed("/tools/vocal-key-finder"):
        print("skip tools_result key finder (not listed yet)")
        return
    f = flow.Flow(SITE, "phone", "tools_result", skin="serious")
    try:
        f.go("/tools/vocal-key-finder", "document.querySelector('[data-tool]')?.dataset.state === 'ready'")
        assert f.js("!document.querySelector('[data-song-label]').hidden"), "the song picker is hidden: no songs reached the tool"
        first = f.js("document.querySelector('[data-song]').options[1]?.textContent ?? ''")
        assert first, "the song picker has no songs"
        f.run("const s = document.querySelector('[data-song]'); s.value = '0'; s.dispatchEvent(new Event('change', { bubbles: true }))")
        f.wait("!document.querySelector('[data-learn]').hidden", timeout=5, what="the Learn it link")
        href = f.js("document.querySelector('[data-learn]').getAttribute('href')")
        assert href.startswith("/songs/"), f"the Learn it link leads to {href}"
        print(f"ok   tools_result (key finder: {first}, {href})")
    except Exception as error:  # noqa: BLE001
        failures.append(f"key finder: {error}")
        print(f"FAIL tools_result (key finder): {error}")
    finally:
        f.close()


def drone(failures):
    """The drone's free lesson waits until it has been sung with: nothing while it plays, the line once it's stopped after
    20 s or more (the page's clock moved on here, not waited for)."""
    if not listed("/tools/drone"):
        print("skip tools_result drone (not listed yet)")
        return
    f = flow.Flow(SITE, "phone", "tools_result", skin="serious")
    try:
        f.go("/tools/drone", "document.querySelector('[data-tool]')?.dataset.state === 'ready' && document.querySelector('[data-ui=tool-next]')")
        f.run("document.querySelector('[data-ui=tool-primary]').click()")
        f.run("document.querySelector('[data-ui=tool-primary]').click()")
        assert not f.js("!!document.querySelector('[data-ui=tool-next] a')"), "a moment's drone isn't the lesson's moment"
        f.run("document.querySelector('[data-ui=tool-primary]').click()")
        f.run("const real = performance.now.bind(performance); performance.now = () => real() + 25000;")
        f.run("document.querySelector('[data-ui=tool-primary]').click()")
        f.wait("document.querySelector('[data-ui=tool-next] a')", what="the lesson line after singing with it")
        lesson = f.js("document.querySelector('[data-ui=tool-next] a').getAttribute('href')")
        assert "/learn/pp-1-bullseye?ref=tool-drone" in lesson, f"the drone's lesson: {lesson}"
        print("ok   tools_result drone")
    except Exception as error:  # noqa: BLE001
        failures.append(f"drone: {error}")
        print(f"FAIL tools_result drone: {error}")
    finally:
        f.close()


def main():
    failures = []
    key_finder(failures)
    drone(failures)
    if not listed():
        print("skip tools_result (the range test isn't listed yet)")
        sys.exit(1 if failures else 0)
    for size in ("phone", "desktop"):
        f = flow.Flow(SITE, size, "tools_result", skin="serious")
        try:
            f.go(PAGE, "document.querySelector('[data-ui=tool-primary]') && document.querySelector('[data-try-line]')")
            before = f.js("document.querySelector('[data-try-line]').textContent")
            f.run("document.querySelector('[data-ui=tool-primary]').click(); document.querySelector('[data-manual]').click()")
            f.wait("document.querySelector('[data-key=\"48\"]')", what="the keyboard")
            f.run("document.querySelector('[data-key=\"48\"]').click(); document.querySelector('[data-key=\"69\"]').click()")
            f.wait(f"document.querySelector('[data-try-line]').textContent !== {before!r}", timeout=5, what="the band's result line")
            line = f.js("document.querySelector('[data-try-line]').textContent")
            href = f.js("document.querySelector('[data-try-link]').getAttribute('href')")
            assert "C3 to A4" in line and "(tenor)" in line, f"{size}: the line doesn't say the range and its type: {line}"
            assert ":" not in line.replace("C3 to A4", ""), f"{size}: a placeholder is left in the line: {line}"
            assert "range=C3-A4" in href and "ref=tool-vocal-range-test" in href, f"{size}: the button's address: {href}"
            # A range found: the tool's bar offers its free lesson (page.js), a guest's first lesson, tagged.
            f.wait("document.querySelector('[data-ui=tool-next] a')", what="the lesson line")
            lesson = f.js("document.querySelector('[data-ui=tool-next] a').getAttribute('href')")
            assert "/learn/range-1-span?ref=tool-vocal-range-test" in lesson, f"{size}: the lesson's address: {lesson}"
            print(f"ok   tools_result ({size})")
        except Exception as error:  # noqa: BLE001 (both sizes are tried)
            failures.append(f"{size}: {error}")
            print(f"FAIL tools_result ({size}): {error}")
        finally:
            f.close()
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
