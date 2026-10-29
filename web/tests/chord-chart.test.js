// The chord chart (js/components/create/chord-chart.js): four bars a line, "%" for a bar that
// repeats the one before, a line that repeats the line before shown once with its count.
import { barNames, chartLines } from '/js/components/create/chord-chart.js';
import { assert, test } from './harness.js';

test('chord chart: four to a line, % for a repeated bar, a repeated line counted', () => {
    const lines = chartLines(['C', 'C', 'F', 'G', 'C', 'C', 'F', 'G', 'Am', 'F', 'C', 'G']);
    assert(lines.length === 2, `${lines.length} lines`);
    assert(lines[0].cells.map((c) => c.text).join(' ') === 'C % F G' && lines[0].times === 2, JSON.stringify(lines[0]));
    assert(lines[0].cells[1].bars.join() === '1,5', 'a folded cell stands for both bars');
    assert(lines[1].cells.map((c) => c.text).join(' ') === 'Am F C G' && lines[1].times === 1, JSON.stringify(lines[1]));
});

test("chord chart: the bars named in the chosen key's letters", () => {
    assert(barNames([['I'], ['V'], ['vi'], ['IV']], { tonic: 7, mode: 'major' }).join(' ') === 'G D Em C', barNames([['I'], ['V'], ['vi'], ['IV']], { tonic: 7, mode: 'major' }).join(' '));
    assert(barNames([['I', 'vi'], ['ii', 'V']], { tonic: 0, mode: 'major' }).join(' | ') === 'C Am | Dm G', 'two to a bar');
});
