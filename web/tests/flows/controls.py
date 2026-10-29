"""The forms system's shared controls on /styleguide (Hillel: "I like new design system, I think it
works well for game mode on or off"): each works and reads right in both skins. A switch flips its
aria-checked, the arrow keys move a segmented choice, a stepper stops at its limits, a slider's fill
follows its value, the birth date's error shows only for an answer that fails, and every control
keeps a 44px target. With OUT=<dir> (and OUT_DARK=1 for game) it shoots the styleguide's sections.

    PORT=8402 FLOW_SKINS=game,serious tool/flows.sh controls
    OUT=~/Code/shots-review/forms-system/styleguide OUT_DARK=1 PORT=8402 tool/flows.sh controls
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, compress, shoot  # noqa: E402

SECTIONS = ("sg-buttons", "sg-controls", "sg-forms")


def journey(f):
    f.go("/styleguide?api=mock&scenario=", "document.querySelector('[data-sg-controls]')")
    if OUT:
        # As it opens: the samples' own states (the birth date's error among them).
        for section in SECTIONS:
            shoot(f, section, scroll=f"document.getElementById('{section}').scrollIntoView({{ block: 'start' }}); window.scrollBy(0, -16)")
        compress()
        f.run("window.scrollTo(0, 0)")
    # The page never scrolls sideways (the sample table scrolls inside its card on a phone, by design).
    assert f.js("document.documentElement.scrollWidth - document.documentElement.clientWidth") <= 0, f"[{f.name}, {f.size}] the styleguide scrolls sideways"

    # The switch: the whole row is the button, and it flips.
    switch = "document.querySelector('[data-sg-controls] [role=switch]')"
    assert f.js(f"{switch}.getAttribute('aria-checked')") == "true", "Sounds starts on"
    f.run(f"{switch}.click()")
    f.wait(f"{switch}.getAttribute('aria-checked') === 'false'", what="the switch off")
    f.run(f"{switch}.click()")

    # The segmented control: the arrow keys move the choice and the focus, wrapping at the ends.
    seg = "document.querySelector('[data-sg-controls] [role=radiogroup]')"
    chosen = f"[...{seg}.querySelectorAll('[role=radio]')].findIndex((b) => b.getAttribute('aria-checked') === 'true')"
    assert f.js(chosen) == 1, "Notes starts chosen"
    f.run(f"{seg}.querySelector('[aria-checked=true]').focus(); {seg}.dispatchEvent(new KeyboardEvent('keydown', {{ key: 'ArrowRight', bubbles: true }}))")
    f.wait(f"{chosen} === 2", what="ArrowRight to Staff")
    f.run(f"{seg}.dispatchEvent(new KeyboardEvent('keydown', {{ key: 'ArrowRight', bubbles: true }}))")
    f.wait(f"{chosen} === 0 && document.activeElement === {seg}.querySelector('[role=radio]')", what="ArrowRight wraps to Words, focused")
    assert f.js(f"[...{seg}.querySelectorAll('[role=radio]')].map((b) => b.tabIndex).join()") == "0,-1,-1", "only the chosen one is in the tab order"

    # The stepper stops at its limits, and a typed number is kept within them.
    stepper = "document.querySelector('[data-sg-controls] .stepper')"
    f.run(f"const i = {stepper}.querySelector('input'); i.value = '500'; i.dispatchEvent(new Event('change'))")
    f.wait(f"{stepper}.querySelector('input').value === '200' && {stepper}.querySelectorAll('button')[1].disabled", what="the stepper held at 200, + off")

    # The slider's fill follows its value.
    f.run("const r = document.querySelector('#sg-range'); r.value = '25'; r.dispatchEvent(new Event('input'))")
    f.wait("document.querySelector('#sg-range').style.getPropertyValue('--p') === '25%'", what="the slider's fill at 25%")

    # The birth date: the error shows for an answer under 13 (the sample), and goes for a good one.
    f.wait("document.querySelector('[data-sg-forms] fieldset .field-error')", what="the under-13 message")
    f.run("const y = document.querySelector('[data-sg-forms] select[autocomplete=bday-year]'); y.value = String(new Date().getFullYear() - 30); y.dispatchEvent(new Event('change'))")
    f.wait("!document.querySelector('[data-sg-forms] fieldset .field-error')", what="no message for a good answer")

    # Every control is 44px tall or keeps a 44px target (a ::after box).
    small = f.js("""[...document.querySelectorAll('[data-sg-buttons] button, [data-sg-controls] [role=switch], [data-sg-controls] [role=radio], [data-sg-controls] .stepper, [data-sg-controls] .range, [data-sg-controls] .check-row, [data-sg-forms] .input')]
        .filter((e) => { const r = e.getBoundingClientRect(); const after = getComputedStyle(e, '::after'); const target = after.content !== 'none' ? Math.max(r.height, parseFloat(after.height) || 0) : r.height; return r.height && target < 43.5; })
        .map((e) => `${e.tagName.toLowerCase()}.${[...e.classList].slice(0, 3).join('.')} ${Math.round(e.getBoundingClientRect().height)}px`)""")
    # A segment sits in its 44px track (36px, as the spec has it).
    small = [s for s in small if "seg-option" not in s]
    assert not small, f"[{f.name}, {f.size}] controls under 44px: {small[:6]}"
    f.shot("styleguide")


if __name__ == "__main__":
    run("controls", journey)
