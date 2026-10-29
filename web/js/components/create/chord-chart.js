// A basic chord chart, lead-sheet style (Hillel: what "Show the chords" shows): the bars four to a
// line, | C | % | F | G |, "%" for a bar that repeats the one before, a line that repeats the line
// before as "x2" (or more) on it, the section's name on the left, and the bar playing highlighted.
// Letters are the chosen key's.

import { chordName, chordsFor } from '../../theory.js';

const PER_LINE = 4;

/**
 * The chart's lines from each bar's chords (`names`: one string a bar, chords space-separated):
 * `[{ cells: [{ text, bars }], times }]`. A bar the same as the one before is "%"; a line the same
 * as the line before folds into it (`times` 2 or more), so `bars` lists every bar a cell stands for.
 */
export function chartLines(names) {
    const lines = [];
    for (let start = 0; start < names.length; start += PER_LINE) {
        const slice = names.slice(start, start + PER_LINE);
        const last = lines.at(-1);
        if (last && last.names.join('|') === slice.join('|')) {
            last.times++;
            slice.forEach((_, k) => last.cells[k].bars.push(start + k));
            continue;
        }
        lines.push({
            names: slice,
            times: 1,
            cells: slice.map((name, k) => ({ text: start + k > 0 && name === names[start + k - 1] ? '%' : name, bars: [start + k] })),
        });
    }
    return lines.map(({ cells, times }) => ({ cells, times }));
}

/** Each bar's chords in letters, for `bars` (theory.js's form) in `key` ({ tonic, mode }). */
export function barNames(bars, key) {
    const chords = chordsFor(bars, { tonic: key.tonic, perBar: 4 });
    let at = 0;
    return bars.map((bar) => {
        const names = bar.map(() => chordName(chords[at++], key));
        return names.join(' ');
    });
}

export const ChordChart = {
    props: {
        bars: { type: Array, required: true }, // theory.js's form
        keyOf: { type: Object, required: true }, // { tonic, mode }
        section: { type: String, default: '' },
        current: { type: Number, default: -1 }, // the bar playing, from 0 (in the progression)
    },
    template: `
        <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-cream" role="table" :aria-label="t('create.chords.chart')">
            <template v-for="(line, i) in lines" :key="i">
                <span class="self-center text-xs font-bold uppercase tracking-wide text-muted" role="rowheader">{{ i === 0 ? section : '' }}</span>
                <div class="flex items-stretch" role="row">
                    <span class="w-px self-stretch bg-muted/60" aria-hidden="true"></span>
                    <template v-for="(cell, k) in line.cells" :key="k">
                        <span class="min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-center text-base font-bold tabular-nums transition-colors"
                            :class="cell.bars.includes(current) ? 'bg-accent text-accent-ink' : ''" role="cell" translate="no" :aria-current="cell.bars.includes(current) ? 'true' : null">{{ cell.text }}</span>
                        <span class="w-px self-stretch bg-muted/60" aria-hidden="true"></span>
                    </template>
                    <span v-if="line.times > 1" class="ms-2 self-center text-sm font-bold text-muted">{{ t('create.chords.repeats', { count: line.times }) }}</span>
                </div>
            </template>
        </div>`,
    computed: {
        lines() {
            return chartLines(barNames(this.bars, this.keyOf));
        },
    },
};
