"""A first visit in another language (the coordinator's check for lesson 1's words): the person's own
file is loaded in full before anything shows, so lesson 1 never flashes English from the short list.
Only English exists today, so a made-up French file (en.json with "FR " before every string) is
served in its place at /locales/fr.json.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Before the page's own scripts: /locales/fr.json answered from en.json, and lesson 1's button's first words kept.
FRENCH = """(() => {
    const real = window.fetch.bind(window);
    const french = (node) => (typeof node === 'string' ? 'FR ' + node : Object.fromEntries(Object.entries(node).map(([k, v]) => [k, french(v)])));
    window.fetch = async (input, init) => {
        const url = new URL(typeof input === 'string' ? input : input.url, location.href);
        if (url.pathname !== '/locales/fr.json') return real(input, init);
        const english = await (await real('/locales/en.json')).json();
        return new Response(JSON.stringify(french(english)), { headers: { 'Content-Type': 'application/json' } });
    };
    new MutationObserver((changes, observer) => {
        const button = document.querySelector('[data-go-on]');
        if (!button || !button.textContent.trim()) return;
        window.__firstButton = button.textContent.trim();
        observer.disconnect();
    }).observe(document, { childList: true, subtree: true, characterData: true });
})();"""


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": FRENCH})
    f.go("/learn/1-comfortable?api=mock&scenario=&lang=fr", "document.querySelector('main h1') && document.querySelector('[data-go-on]')")
    if f.js("!!navigator.serviceWorker?.controller"):
        return  # a controlled visit reads every file in full from the cache
    assert f.js("(await import('/js/i18n.js')).locale.lang") == "fr", "the person's language"
    first = f.js("window.__firstButton")
    assert first and first.startswith("FR "), f"the first words shown are French, not English: {first!r}"
    assert f.js("document.querySelector('[data-go-on]').textContent.trim().startsWith('FR ')"), "lesson 1 in French"
    f.wait("performance.getEntriesByType('resource').some((r) => new URL(r.name).pathname === '/locales/en.json')", timeout=20, what="English in full, once idle, for any key French lacks")
    assert f.js("(await import('/js/i18n.js')).words.missed") == [], "no key missing"
    f.shot("french")


if __name__ == "__main__":
    run("first_words_other", journey)
