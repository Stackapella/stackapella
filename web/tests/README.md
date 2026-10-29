# Testing the web app

There's no npm, so there's no Jest or Playwright. Everything runs in headless Chrome, driven by
`tool/cdp.py` (Python's standard library only), with the audio muted. Three kinds of tests:

| Kind | Where | Run | For |
|---|---|---|---|
| **Unit tests** | `tests/*.test.js`, listed in `tests/index.html` | `tool/test.sh` | Pure modules (the maths, the stores, the contract), and the audio engine over loopback |
| **Flow scripts** | `tests/flows/*.py` | `tool/flows.sh` (all), `tool/flows.sh <name>` (one) | Whole journeys through the app, at phone (390 x 844) and desktop (1440 x 900) size |
| **Checks** | `tool/i18n_check.py`, `tool/offline_check.py`, `tool/api_check.py` | `tool/test.sh` runs them first | The words, the offline list, and that only `api.js` calls `/api` |

`tool/test.sh` must pass before anything lands on `main`. Run the flows for anything you touched.
**Test the error paths too:** for every screen, the refusals the contract lists (a mock scenario),
silence and noise for audio, and leaving halfway.

## A unit test

A test is `test(name, body)` from `tests/harness.js`; `body` may be async. `assert(condition,
message)` and `close(actual, expected, tolerance, what)` fail with your message.

**Example:** a new helper `formatBeats(beats, bpm)` in `js/theme.js`. Add `tests/theme.test.js`:

```js
import { formatBeats } from '/js/theme.js';
import { assert, close, test } from './harness.js';

test('theme: beats become seconds at the tempo', () => {
    close(formatBeats(4, 120), 2, 1e-9, '4 beats at 120 BPM');
});

test('theme: a missing tempo is refused, not guessed', () => {
    let refused = false;
    try {
        formatBeats(4, null);
    } catch {
        refused = true;
    }
    assert(refused, 'no tempo should throw');
});
```

Then add `import './theme.test.js';` to `tests/index.html`, and run `tool/test.sh`. To watch it in a
browser, serve the app (`php -S localhost:8081 tool/serve.php`) and open
http://localhost:8081/tests/.

- **Name tests as sentences** that say what must hold ("a take survives a call"), prefixed by the
  module.
- **Audio** is tested over loopback: `engine.setLoopback(true)` feeds what's played straight into
  the recorder, so timing must come out exact to the sample. See `tests/engine.test.js`. Never
  "sounds right": compare samples.
- **Singing** is synthesized by `sing()` in `tests/fixtures.js` (cents off, vibrato, scoops, late
  entries, an octave away, legato glides, noise), so the pitch tracker and scoring are tested on
  known faults. See `tests/scoring.test.js`.
- **Storage** tests use a database of their own: `useDatabase('stackapella-test-...')` from
  `js/db.js`, and switch back in a `finally`.

## A flow script

A flow is a journey through the app, run at both sizes. It uses `tests/flows/flow.py`:

| Call | Does |
|---|---|
| `f.go(path, wait)` | Opens a page and waits for a JS condition |
| `f.click(text)` / `f.click_t(key, values)` | Clicks the button or link with that text, or with the words `t(key)` gives (works in any language) |
| `f.type(selector, text)` | Types into a field (and tells Vue) |
| `f.wait(js, what=...)` / `f.wait_text(text)` | Waits for a condition, or for words on the page |
| `f.js(expression)` / `f.run(statements)` | Runs JavaScript in the page; `await import('/js/audio.js')` reaches the app's own modules |
| `f.shot(label)` | Saves a screenshot to `tests/flows/shots/` |
| `f.expected_errors.append(text)` | A console error the journey causes on purpose (any other fails the flow) |

**Example:** Tell Hillel refuses an empty note. Add `tests/flows/tell_hillel.py`:

```python
"""Tell Hillel: an empty note is refused kindly, a real one is sent."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def journey(f):
    f.go("/beta?api=mock", "document.querySelector('main')")
    f.click_t("beta.tellHillel")
    f.click_t("common.send")
    f.wait_text(f.js("(await import('/js/i18n.js')).t('beta.feedback.empty')"))
    f.shot("empty")
    f.type("[role=dialog] textarea", "The timing check failed twice")
    f.click_t("common.send")
    f.wait_text(f.js("(await import('/js/i18n.js')).t('beta.feedback.sent')"))


if __name__ == "__main__":
    run("tell_hillel", journey)
```

Run it with `tool/flows.sh tell_hillel`. It runs at both sizes and prints `ok` or `FAIL` with the
step that timed out; the screenshot of the failure is in `tests/flows/shots/`.

- **Against the real server** (the integration check at the end of a milestone): start Laravel on
  :8000 (the backend's README), then `API=http://127.0.0.1:8000 tool/flows.sh <name>`. `serve.php`
  forwards `/api` and the server's own pages on the same origin, so cookies and CSRF behave as in
  production. `tests/flows/admin_integration.py` is the M1 example.
- **The pseudo-language:** `tests/flows/pseudo_language.py` opens each screen with `?lang=pseudo`
  and fails on any text that didn't come through `t()`. Add new screens to it.
- **Audio in a flow:** `(await import('/js/audio.js')).engine.setLoopback(true)` after
  `unlock()`, as `tests/flows/record_interrupted.py` does. Headless Chrome has a fake mic.

## A mock scenario

`?api=mock` (on this machine) answers every call from `contract/api.md` and its examples, so
screens are built against the same JSON the backend is checked against. `?scenario=` picks another
case:

| Scenario | Answers |
|---|---|
| `?scenario=not_admin` | Every call is refused with that code (any generic code works: `rate_limited`, `paused`, `read_only`, `app_outdated`, `server_error`, `unavailable`, `bad_request`, `unauthenticated`) |
| `?scenario=admin.flags.update:sudo_required` | Only that operation is refused; the others answer as usual |
| `?scenario=csrf_mismatch` | Each operation is refused once, so you see the retry |
| `?scenario=a:x,b:y` | Several at once |
| `?scenario=` | Back to normal |

The choice is remembered for the tab, so it survives moving around the app; `?api=real` turns
the mock off.

**Example:** the admin's switches when the person must confirm with their second factor first:

1. Open http://localhost:8081/admin/safety?api=mock&scenario=admin.flags.update:sudo_required.
2. Change a switch and give a reason: the app sends you to `/admin/second-factor?sudo=1`
   (under the mock, `serve.php` shows a stand-in page with a link back).
3. In a flow, as `tests/flows/admin_mock.py` does:

```python
f.go("/admin/safety?api=mock&scenario=admin.flags.update:sudo_required", "document.querySelectorAll('[aria-pressed]').length > 1")
f.run("document.querySelectorAll('[aria-pressed=false]')[0].click()")
f.type("[role=dialog] textarea", "x")
f.click_t("admin.confirmChange")
f.wait("location.pathname === '/admin/second-factor'", what="the sudo check")
```

- **A case with its own example** (`contract/examples/<operation>.<code>.json`) answers with that
  example and its status from `api.md`'s table. Others get `{ message, code }` with the generic
  status.
- **State:** the mock keeps the admin's switches, limits and blocklist as they're changed, for the
  length of the page. Reload to start again.
- **A new operation** needs its row in `api.md`, its function in `js/api.js`, and its examples; the
  contract test (`tests/api.test.js`) fails until all three agree.
