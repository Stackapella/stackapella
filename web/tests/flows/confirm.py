"""No browser dialogs (Hillel's rule): deleting asks in the app's own sheet (78.png), which names the
thing, what happens and what's lost, with the action on its own button. Keep it has the focus, so
Enter keeps it, as Escape and the backdrop do; there's no extra close and no "Don't ask again".
Undo follows, in a toast with a countdown bar. Deleting a part in the studio asks the same way, and
discarding a draft from the home has its Undo too.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SHEET = "document.querySelector('[role=dialog]')"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def make(f, title):
    f.run(f"""const s = await import('/js/stores/projects.js'); const p = await s.createProject({{ title: {title!r} }});
        await s.savePart({{ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false,
            offsetSeconds: 0, frames: 4800, sampleRate: 48000, peaks: [0.2, 0.4], createdAt: Date.now() }}, new Float32Array(4800)); window.made = p.id;""")
    return f.js("window.made")


def listed(f, title):
    return f.js(f"[...document.querySelectorAll('section[aria-labelledby=band-projects] [data-kind] [translate], section[aria-labelledby=band-projects] [data-kind] .italic')].some((h) => h.textContent.trim() === {title!r})")


def menu(f, title, item):
    """A Your songs card's More menu, then one of its items (Delete, Discard...)."""
    label = t(f, "home.yours.moreNamed", {"title": title})
    f.wait(f"[...document.querySelectorAll('[data-card-menu]')].some((b) => b.getAttribute('aria-label') === {label!r})", what=f"the More menu for {title}")
    f.run(f"[...document.querySelectorAll('[data-card-menu]')].find((b) => b.getAttribute('aria-label') === {label!r}).click()")
    f.wait(f"document.querySelector('[data-menu-item={item}]')", what=f"{item} in the menu")
    f.run(f"document.querySelector('[data-menu-item={item}]').click()")


def trash(f, title):
    menu(f, title, "delete")


def home(f):
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('section[aria-labelledby=band-projects]')")
    time.sleep(0.4)


def journey(f):
    home(f)
    make(f, "Keep me")
    home(f)
    sheet_title = t(f, "confirm.deleteProject.title", {"title": "Keep me"})
    action = t(f, "confirm.deleteProject.action")

    keep = t(f, "confirm.keep")

    # What's lost, in the sheet (78.png); Keep it has the focus, and there's no extra close.
    trash(f, "Keep me")
    f.wait(f"{SHEET}?.innerText.includes({sheet_title!r})", what="the sheet names the song")
    f.wait(f"document.activeElement?.textContent.trim() === {keep!r}", what="Keep it focused")
    assert f.js(f"{SHEET}.querySelector('dl')?.innerText.includes({t(f, 'confirm.deleteProject.recorded')!r})"), "what's lost isn't shown"
    assert not f.js(f"!!{SHEET}.querySelector('header button')"), "a close button beside Keep it"
    assert not f.js(f"!!{SHEET}.querySelector('input[type=checkbox]')"), "Don't ask again on a delete"
    f.shot("sheet")
    f.press("Escape")
    f.wait(f"!{SHEET}", what="closed by Escape")
    assert listed(f, "Keep me"), "Escape deleted it"
    # The backdrop keeps it too, and so does Enter (on Keep it).
    trash(f, "Keep me")
    f.wait(SHEET, what="the sheet again")
    f.run("document.querySelector('[role=dialog]').parentElement.click()")
    f.wait(f"!{SHEET}", what="closed by the backdrop")
    assert listed(f, "Keep me"), "the backdrop deleted it"
    trash(f, "Keep me")
    f.wait(f"document.activeElement?.textContent.trim() === {keep!r}", what="Keep it focused")
    f.press("Enter")
    f.wait(f"!{SHEET}", what="closed by Enter")
    assert listed(f, "Keep me"), "Enter deleted it"
    # Delete song deletes it; Undo, with its countdown bar, brings it back.
    trash(f, "Keep me")
    f.wait(SHEET, what="the sheet")
    f.click(action)
    f.wait(f"!{SHEET} && !document.querySelector('section[aria-labelledby=band-projects] h3')?.textContent.includes('Keep me')", what="deleted")
    f.wait_text(t(f, "common.undo"))
    assert f.js("!!document.querySelector('[aria-live] span.origin-left')"), "no countdown bar on the Undo toast"
    f.shot("undo")
    f.click_t("common.undo")
    f.wait("document.body.innerText.includes('Keep me')", what="back with Undo")
    third = make(f, "Third song")
    home(f)

    # A part in the studio: asked in the same sheet, with Undo.
    f.go(f"/p/{third}", "document.querySelector('footer')")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 1", what="the studio with its part")
    # The part's row opens in place; Voice and more is its settings.
    f.run("[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes('Lead')).click()")
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[role=dialog]')", what="the part's settings")
    f.click_t("part.delete")
    f.wait(f"{SHEET}?.innerText.includes({t(f, 'confirm.deletePart.title', {'name': 'Lead'})!r})", what="the part's question")
    f.shot("part")
    f.click_t("confirm.deletePart.action", {"name": "Lead"})
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 0", what="the part deleted")
    f.wait_text(t(f, "common.undo"))
    f.click_t("common.undo")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 1", what="the part back with Undo")

    f.run("const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all) await s.deleteProject(p.id);")

    # Discarding a draft (the home's Unfinished): asked (it may be skipped, so it's undoable), then
    # Undo in the toast with its countdown puts the draft back exactly.
    f.run("const s = await import('/js/stores/templates.js'); await s.saveDraft({ id: 'draft-undo', step: 'words', lines: [{ text: 'Our draft' }], template: { title: 'Our draft' } });")
    f.go("/?api=mock&scenario=", "document.querySelector('#app header')")
    f.wait_text("Our draft")
    draft = "(await import('/js/stores/templates.js')).loadDraft('draft-undo').then((d) => d ? JSON.stringify(d) : null)"
    before = f.js(draft)
    menu(f, "Our draft", "discard")
    f.wait(f"{SHEET}?.innerText.includes({t(f, 'create.home.discardTitle')!r})", what="the discard question")
    f.click(t(f, "create.home.discard"), "[role=dialog] button")
    f.wait(f"{draft}.then((d) => d === null)", what="the draft discarded")
    f.wait_text(t(f, "create.home.discarded"))
    assert f.js("!!document.querySelector('.origin-left.bg-accent')"), "the Undo's countdown bar"
    f.shot("draft-discarded")
    f.click_t("common.undo")
    f.wait(f"{draft}.then((d) => d === {before!r})", what="the draft back as it was")
    f.wait_text("Our draft")
    f.run("await (await import('/js/stores/templates.js')).discardDraft('draft-undo');")


if __name__ == "__main__":
    run("confirm", journey)
