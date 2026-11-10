// The shared grand staff (js/public/staff.js): where notes sit, their ledger lines and sharps.
import { grandStaff, ledgers, ledgersOn, staffStep } from '/js/public/staff.js';
import { assert, test } from './harness.js';

test('staff: notes sit on their lines, with ledger lines only where they\'re needed', () => {
    assert(staffStep(60).step === 28 && staffStep(64).step === 30 && staffStep(43).step === 18, 'C4, E4, G2');
    assert(staffStep(61).sharp && !staffStep(62).sharp && staffStep(61).step === 28, 'C♯4 is a C with a sharp');
    assert(ledgers(28).join() === '28', 'middle C has its own line');
    assert(ledgers(29).length === 0 && ledgers(27).length === 0 && ledgers(39).length === 0 && ledgers(17).length === 0, 'D4, B3, G5 and F2 need none');
    assert(ledgers(42).join() === '40,42' && ledgers(14).join() === '16,14', 'C6 and C2 need two');
    const svg = grandStaff([43, 64], { label: 'G2 to E4' });
    assert(svg.querySelectorAll('ellipse').length === 2 && svg.getAttribute('aria-label') === 'G2 to E4', 'two notes, labelled');
    assert(svg.querySelectorAll('line').length === 11, `ten staff lines and the brace line: ${svg.querySelectorAll('line').length}`);
});

test('staff: a chord stacks in one column with its name over it, a second set beside', () => {
    const svg = grandStaff([[76, 67, 60, 48], [74, 72, 55, 43]], { names: ['C', 'G7'] });
    assert(svg.querySelectorAll('[data-column]').length === 2 && svg.querySelectorAll('ellipse').length === 8, 'two columns of four');
    assert([...svg.querySelectorAll('text')].map((t) => t.textContent).filter((t) => /^[A-G]/.test(t)).join() === 'C,G7', 'the names');
    const xs = [...svg.querySelectorAll('[data-column="1"] ellipse')].map((e) => Number(e.getAttribute('cx')));
    assert(new Set(xs).size === 2 && Math.max(...xs) > Math.min(...xs), `C5 and D5, a second: side by side (${xs})`);
    const first = [...svg.querySelectorAll('[data-column="0"] ellipse')].map((e) => Number(e.getAttribute('cx')));
    assert(new Set(first).size === 1, 'no seconds: one column');
});

test('staff: parts on their own staff, a tenor\'s middle C over the bass staff with its ledger line', () => {
    assert(ledgersOn(28, false).join() === '28' && ledgersOn(30, false).join() === '28,30' && ledgersOn(28, true).join() === '28' && ledgersOn(26, true).join() === '28,26', 'ledgers per staff');
    const svg = grandStaff([[72, 64, 60, 48]], { upper: 2 });
    const ys = [...svg.querySelectorAll('ellipse')].map((e) => Number(e.getAttribute('cy')));
    const tenor = ys.toSorted((a, b) => a - b)[2];
    const plain = [...grandStaff([60]).querySelectorAll('ellipse')].map((e) => Number(e.getAttribute('cy')))[0];
    assert(tenor > plain, `the tenor's C4 sits on the bass staff, lower than a treble C4 (${tenor} vs ${plain})`);
});

test('staff: a sharp sits left of its own note, even beside a second', () => {
    const svg = grandStaff([[61, 63]]);
    const notes = [...svg.querySelectorAll('ellipse')].map((e) => Number(e.getAttribute('cx')));
    const signs = [...svg.querySelectorAll('text')].filter((t) => t.textContent === '♯').map((t) => Number(t.getAttribute('x')));
    assert(signs.length === 2 && new Set(signs).size === 2 && signs.every((x, i) => x < notes[i]), `each sign left of its note: ${signs} / ${notes}`);
});
