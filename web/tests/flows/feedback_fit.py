"""Contact us's chips at 360 px in Spanish (docs/feedback-plan.md: the longest words): the topics and the choir ask's
what's off wrap inside the card, each on screen whole, nothing sideways, with feedback2 on. Against the mock."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

FITS = """[...document.querySelectorAll('{sel}')].every((c) => {{ const r = c.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth && c.scrollWidth <= c.clientWidth + 1; }})"""


def journey(f):
    f.go("/?api=mock&scenario=", "document.querySelector('#app header, #app main')")
    f.run("sessionStorage.setItem('stackapella.mockFeedback2', '1')")
    f.go("/contact?from=/settings&moment=settings&api=mock&scenario=&lang=es&as=signedOut", "document.querySelector('[data-topic]')")
    f.wait("(await import('/js/i18n.js')).locale.lang === 'es'", what="Spanish")
    assert f.js("innerWidth") <= 360, f.js("innerWidth")
    assert f.js(FITS.format(sel="[data-topic]")), "a topic chip doesn't fit"
    f.run("document.querySelector('[data-topic=broken]').click()")
    f.wait("document.querySelector('[data-sent-summary]')", what="Va con el mensaje")
    f.no_sideways("Contacto, 360 px")
    f.shot("es-topics")
    f.go("/contact?from=/t/silent-night/choir&moment=choir_ask&ask=choir&choir=Cr8tYp&api=mock&scenario=&lang=es&as=member", "document.querySelector('[data-issue]')")
    assert f.js(FITS.format(sel="[data-issue]")), "a choir chip doesn't fit"
    f.no_sideways("the choir ask's form, 360 px")
    f.shot("es-choir")
    f.run("sessionStorage.removeItem('stackapella.mockFeedback2')")


if __name__ == "__main__":
    run("feedback_fit", journey, sizes=("narrow",))
