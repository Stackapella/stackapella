// The free tools' one layout (js/public/tool-layout.js): the slots a tool fills, the key control in the
// page's brand row (or at the tool's own top), the modes, the status pill, the label and stat, the
// primary action and the More sheet.
import { segmented, stepper, toolLayout } from '/js/public/tool-layout.js';
import { assert, test } from './harness.js';

test('tool layout: every slot is there, the status pill says the state, the primary action and More work', () => {
    const el = document.createElement('div');
    document.body.append(el);
    const ui = toolLayout(el, { more: 'More', done: 'Done' });
    for (const slot of ['element', 'key', 'modes', 'hero', 'readout', 'panel', 'sheet', 'primary', 'more']) assert(ui[slot], `the ${slot} slot`);
    for (const hook of ['tool', 'tool-modes', 'tool-hero', 'tool-status', 'tool-readout', 'tool-label', 'tool-stat', 'tool-panel', 'tool-bar', 'tool-primary', 'tool-more', 'tool-sheet']) assert(el.querySelector(`[data-ui=${hook}]`), `the ${hook} hook`);
    ui.setStatus({ tone: 'good', text: 'In tune', hint: 'Hold it there' });
    const pill = el.querySelector('[data-ui=tool-status]');
    assert(!pill.hidden && pill.textContent.includes('In tune') && pill.textContent.includes('· Hold it there') && pill.className.includes('text-good'), pill.className);
    ui.setStatus({ hint: 'Tap Listen' });
    assert(pill.firstElementChild.hidden && pill.className.includes('text-muted') && !pill.className.includes('bg-'), 'a hint alone has no dot and no tint');
    ui.setLabel('Last 8 seconds', '27% in tune');
    assert(el.querySelector('[data-ui=tool-label]').textContent === 'Last 8 seconds' && el.querySelector('[data-ui=tool-stat]').textContent === '27% in tune', 'label and stat');
    let pressed = 0;
    ui.onPrimary(() => pressed++);
    ui.setPrimary({ label: 'Stop listening', active: true });
    ui.primary.click();
    assert(pressed === 1 && ui.primary.textContent === 'Stop listening' && ui.primary.getAttribute('aria-pressed') === 'true' && ui.primary.className.includes('btn-secondary'), 'active primary');
    ui.more.click();
    assert(el.querySelector('[data-ui=tool-sheet]').open && ui.more.getAttribute('aria-expanded') === 'true', 'More opens the sheet');
    el.querySelector('[data-sheet-done]').click();
    assert(!el.querySelector('[data-ui=tool-sheet]').open, 'Done closes it');
    el.remove();
});

test('tool layout: the key control goes in the page\'s slot, the modes press the picked one', () => {
    const slot = document.createElement('div');
    const el = document.createElement('div');
    document.body.append(slot, el);
    const ui = toolLayout(el, { keySlot: slot });
    assert(ui.key === slot, 'the page slot');
    const steps = [];
    const key = stepper(ui.key, { label: 'A4 Hz', step: (by) => steps.push(by) });
    key.set(440, { atHigh: true });
    slot.querySelector('[data-step="-1"]').click();
    assert(steps.join() === '-1' && slot.querySelector('[data-step-value]').textContent === '440' && slot.querySelector('[data-step="1"]').disabled, 'the stepper');
    const picked = [];
    const modes = segmented(ui.modes, [{ key: 'voice', label: 'Voice' }, { key: 'instrument', label: 'Instrument' }], { current: 'voice', pick: (k) => picked.push(k) });
    el.querySelector('[data-segment=instrument]').click();
    el.querySelector('[data-segment=voice]').click();
    assert(picked.join() === 'instrument', 'only a change is heard');
    modes.set('instrument');
    assert(el.querySelector('[data-segment=instrument]').getAttribute('aria-pressed') === 'true', 'set');
    const brief = segmented(ui.modes, [{ key: 'all', label: 'With the others', short: 'All' }], { current: 'all' });
    const both = brief.element.querySelector('[data-segment=all]');
    assert(both.getAttribute('aria-label') === 'With the others' && both.children.length === 2 && both.children[1].textContent === 'All', 'a short word for a narrow phone, the full one spoken');
    slot.remove();
    el.remove();
});
