"""Practice reminders (M5), against the mock: Settings leads to the page; the days start from the
weekly goal; Turn on asks the browser (granted here) and subscribes this device (the push service is
stubbed in headless Chrome), keeps the device's token and the notification's words for the service
worker; Turn off removes them.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
DB = "(await import('/js/db.js'))"

FAKE_PUSH = """
const fake = { endpoint: 'https://push.example/abc', toJSON: () => ({ endpoint: 'https://push.example/abc', keys: { p256dh: 'BA', auth: 'AA' } }), unsubscribe: async () => true };
PushManager.prototype.subscribe = async () => fake;
PushManager.prototype.getSubscription = async () => window.subscribed ? fake : null;
"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.chrome.send("Browser.grantPermissions", {"permissions": ["notifications"], "origin": f.base})
    f.go("/settings?api=mock&as=member", "document.querySelector('h1')")
    f.wait("document.querySelector('a[href=\"/settings/reminders\"]')", what="the reminders row")
    f.run("document.querySelector('a[href=\"/settings/reminders\"]').click()")
    f.wait("location.pathname === '/settings/reminders'", what="the reminders page")
    f.run(FAKE_PUSH)
    f.wait_text(t(f, "reminders.days"), timeout=30)
    f.shot("reminders")
    # The mock's reminder is already on for "this device" (111.png): a quick pick saves at once, and
    # the next reminder is said in words; turning on is checked through the module (the switch's call).
    f.wait("document.querySelector('[data-reminders] [role=switch]')?.getAttribute('aria-checked') === 'true'", what="the switch on")
    f.click_t("reminders.weekdays")
    f.wait(f"document.querySelectorAll('[data-reminders] .grid-cols-7 [aria-pressed=true]').length === 5", what="the weekdays picked")
    f.wait(f"document.body.innerText.includes({t(f, 'reminders.next', {'when': ''})!r}.trim())", what="the next reminder")
    f.wait("(await navigator.serviceWorker.getRegistration())?.active", timeout=60, what="the service worker")
    f.run("await (await import('/js/reminders.js')).turnOn({ days: [1, 3, 5], time: '18:30' }, 'BOr6xA');")
    device = f.js(f"{DB}.get('meta', 'reminderDevice')")
    words = f.js(f"{DB}.get('meta', 'reminderWords')")
    assert device and device["token"], f"the device's token kept: {device}"
    assert words and words["title"] == t(f, "reminders.push.title"), f"the words kept: {words}"
    f.run("document.querySelector('[data-reminders] [role=switch]').click()")
    f.wait_text(t(f, "reminders.turnedOff"))
    assert not f.js(f"{DB}.get('meta', 'reminderDevice')"), "the device forgotten"


if __name__ == "__main__":
    run("reminders", journey)
