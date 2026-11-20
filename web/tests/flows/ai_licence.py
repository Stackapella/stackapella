"""The AI voice licence, agreed in the app (docs/ai-voices-build-plan.md, phase 1b; js/components/ai-licence.js): a beta
singer an admin has offered it to sees one offer on My parts (never a banner), reads the short version and then the full
terms, chooses the credit in the label's own words, says 18 or over and accepts; changes the credit; and withdraws in a
styled confirmation that names exactly which parts go. Not now leaves one quiet line; not offered, nothing shows. Admin
offers it on the singer's card and sees who accepted which version.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
NAME_LABEL = "AI voice based on {name}'s singing, with permission"
PLAIN_LABEL = "AI voice, waiting for a real singer"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def my_parts(f, licence):
    f.go("/?api=mock&scenario=&as=singer", "document.querySelector('#app *')")
    f.run(f"sessionStorage.setItem('stackapella.mockAiLicence', {json.dumps(licence)}); localStorage.removeItem('stackapella.aiLicenceLater');")
    f.go("/beta?api=mock&scenario=&as=singer", "document.querySelector('main h1')")


def journey(f):
    # Not offered: nothing at all on My parts.
    my_parts(f, "off")
    f.wait(f"document.body.innerText.toLowerCase().includes({json.dumps(t(f, 'beta.parts.title').lower())})", what="My parts' heading")
    assert not f.js("document.querySelector('[data-ai-licence]')"), "nothing while the licence isn't offered"

    # Offered: one offer, and Not now leaves a quiet line.
    my_parts(f, "none")
    f.wait("document.querySelector('[data-ai-licence-offer]')", what="the offer")
    f.run("document.querySelector('[data-ai-licence-offer]').scrollIntoView({ block: 'center' })")
    f.shot("offer")
    f.run("document.querySelector('[data-ai-licence-later]').click()")
    f.wait("document.querySelector('[data-ai-licence-quiet]') && !document.querySelector('[data-ai-licence-offer]')", what="the quiet line after Not now")

    # Read it: the short version, then the full terms; the credit in the label's own words; 18 or over; Accept.
    f.run("document.querySelector('[data-ai-licence-quiet] [data-ai-licence-read]').click()")
    f.wait("document.querySelector('[data-ai-licence-short]') && document.querySelector('[data-ai-licence-full]')", what="the licence")
    # The label previews the singer's credit name (their beta credit), as learners would see it.
    name = f.js("(async () => (await (await import('/js/api.js')).api.beta.me.show()).data.credit?.name)()")
    labels = f.js("[...document.querySelectorAll('[data-ai-licence-terms] [data-ai-credit]')].map((r) => r.closest('label').textContent.trim())")
    assert labels == [NAME_LABEL.format(name=name), PLAIN_LABEL], labels
    assert t(f, "beta.ai.accept").startswith("I'm 18 or over"), "the one button says the 18 or over it confirms"
    f.no_sideways("the licence")
    f.shot("terms")
    assert f.js("document.querySelector('[data-ai-licence-accept]').disabled"), "no credit chosen for them: Accept waits"
    f.run("document.querySelector('[data-ai-licence-terms] [data-ai-credit=plain]').click();")
    f.wait("!document.querySelector('[data-ai-licence-accept]').disabled", what="Accept, once a credit is chosen")
    f.run("document.querySelector('[data-ai-licence-accept]').click()")
    f.wait("document.querySelector('[data-ai-licence-voice]')", what="the licensed voice on My parts")
    assert f.js("document.querySelector('[data-ai-licence-label]').textContent.trim()") == PLAIN_LABEL
    sings = f.js("document.querySelector('[data-ai-licence-sings]').textContent")
    assert "Silent Night" in sings, sings
    f.run("document.querySelector('[data-ai-licence-voice]').scrollIntoView({ block: 'center' })")
    f.shot("accepted")

    # The credit, changed at any time: the label follows.
    f.run("document.querySelector('[data-ai-licence-credit]').click()")
    f.wait("document.querySelector('[role=dialog] [data-ai-credit=named]')", what="the credit choice")
    f.run("document.querySelector('[role=dialog] [data-ai-credit=named]').click(); document.querySelector('[role=dialog] form').requestSubmit();")
    f.wait(f"document.querySelector('[data-ai-licence-label]')?.textContent.trim() === {json.dumps(NAME_LABEL.format(name=name))}", what="credited by name")

    # Withdraw: a styled confirmation naming exactly what goes, then it's gone.
    f.run("document.querySelector('[data-ai-licence-withdraw]').click()")
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the withdraw confirmation")
    body = f.js("document.querySelector('[role=dialog] form p').textContent")
    assert "Silent Night" in body and t(f, "parts.alto") in body and "deleted" in body, body
    f.shot("withdraw")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("document.querySelector('[data-ai-licence][data-standing=withdrawn]')", what="withdrawn")

    # Admin: offer it on a singer's card, and see who accepted which version on AI voices.
    f.go("/admin/singers?api=mock&scenario=&as=admin", "document.querySelector('[data-singer-ai-offer]')")
    f.run("document.querySelector('[data-singer-ai-offer]').click()")
    f.wait("document.querySelector('[data-singer-ai-offer-body]')", what="the offer sheet")
    f.shot("admin-offer")
    f.run("document.querySelector('[data-singer-ai-offer-body]').closest('form').requestSubmit()")
    f.wait("!document.querySelector('[data-singer-ai-offer-body]')", what="offered")
    f.go("/admin/ai-voices?api=mock&scenario=&as=admin", "document.querySelector('[data-ai-voice-licence]')")
    assert "version 1" in f.js("document.querySelector('[data-ai-voice-licence]').textContent")


if __name__ == "__main__":
    run("ai_licence", journey)
