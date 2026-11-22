// The site's mic chooser (js/public/mic-pick.js), as every listening tool uses it (Hillel, 2026-11-22: "all of the tool
// pages with mics need to include a mic chooser"): a row of its own first in a tool's sheet, hidden until the mic is
// allowed and there's more than one input; a stand-in mic (no deviceId or use) never breaks a tool. The pages
// themselves are tests/flows/tools_mic_pick.py and mic_pick.py.
import { assert, test } from './harness.js';
import { micRow, settleMic, showPicker } from '/js/public/mic-pick.js';
import { sheetRow } from '/js/public/tool-layout.js';

const assertEq = (actual, expected) => assert(JSON.stringify(actual) === JSON.stringify(expected), `${JSON.stringify(actual)} should be ${JSON.stringify(expected)}`);

function withInputs(devices, run) {
    const md = navigator.mediaDevices;
    const before = md.enumerateDevices;
    md.enumerateDevices = async () => devices;
    return run().finally(() => (md.enumerateDevices = before));
}

const two = [
    { kind: 'audioinput', deviceId: 'default', label: 'Default - Mic A' },
    { kind: 'audioinput', deviceId: 'a', label: 'Mic A' },
    { kind: 'audioinput', deviceId: 'b', label: 'Mic B' },
    { kind: 'audiooutput', deviceId: 'out', label: 'Speakers' },
];

test('mic-pick: the chooser is a row of its own, first in the sheet, hidden until there is a choice', async () => {
    const sheet = document.createElement('div');
    sheetRow(sheet).body.textContent = 'Reference pitch';
    const row = micRow(sheet, sheetRow);
    assertEq(sheet.firstElementChild === row.element, true);
    assertEq(row.element.hidden, true);
    const select = sheet.querySelector('[data-picker]');
    await withInputs(two, () => showPicker(select, 'b'));
    assertEq(row.element.hidden, false);
    assertEq([...select.options].map((o) => o.value), ['default', 'a', 'b']);
    assertEq(select.value, 'b');
    await withInputs(two.slice(0, 1), () => showPicker(select, 'default'));
    assertEq(row.element.hidden, true);
});

test('mic-pick: a picker inside a bigger row leaves that row alone', async () => {
    const section = document.createElement('section');
    section.dataset.ui = 'tool-setting';
    section.innerHTML = '<p>Mic check</p><label hidden><select data-picker></select></label>';
    await withInputs(two.slice(0, 2), () => showPicker(section.querySelector('select'), 'default'));
    assertEq(section.hidden, false);
});

test('mic-pick: a stand-in mic with no deviceId or use settles without failing', async () => {
    let failed = false;
    await withInputs(two, () => settleMic({}, null)).catch(() => (failed = true));
    assertEq(failed, false);
});
