// The courses page's filters (site/courses.blade.php; Hillel's design, 2026-11-13): one chip pressed at a time (All or a
// group) and a search, which show only the matching course cards, hide a group left with none, and say so when nothing
// matches. Nothing reloads. The chip is remembered in this browser only (a convenience; the page works without it), and
// a screen reader hears how many courses show.

const KEY = 'stackapella.coursesChip';

/** The chip kept in this browser, or null (storage blocked: nothing is kept between visits). */
function read() {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

/** Keep the chip for next time; false where storage is blocked (the filter still applies). */
function keep(value) {
    try {
        localStorage.setItem(KEY, value);
        return true;
    } catch {
        return false;
    }
}
const bar = document.querySelector('[data-courses-filter]');

if (bar) {
    const chips = [...bar.querySelectorAll('[data-filter]')];
    const search = bar.querySelector('[data-courses-search]');
    const shown = bar.querySelector('[data-courses-shown]');
    const groups = [...document.querySelectorAll('[data-course-group]')];
    const empty = document.querySelector('[data-courses-empty]');
    const emptyLine = empty?.querySelector('[data-words]');
    let chosen = 'all';

    const kept = read();
    if (kept && chips.some((chip) => chip.dataset.filter === kept)) chosen = kept;

    // "3 courses shown": the words carry their own plural forms ({0} ...|{1} ...|[2,*] ...).
    const countWords = (n) => {
        const forms = (shown?.dataset.words ?? '').split('|');
        const pick = forms.find((f) => f.startsWith(`{${n}}`)) ?? forms.find((f) => f.startsWith('[2,*]')) ?? '';
        return pick.replace(/^(\{\d+\}|\[\d+,\*\])\s*/, '').replace(':count', String(n));
    };

    // `say`: tell a screen reader how many show (after the visitor acts, never on load).
    function apply(say = false) {
        const q = (search?.value ?? '').trim().toLowerCase();
        let total = 0;
        for (const group of groups) {
            const inChip = chosen === 'all' || group.dataset.courseGroup === chosen;
            let visible = 0;
            for (const card of group.querySelectorAll('[data-course-card]')) {
                const show = inChip && (q === '' || card.dataset.text.includes(q));
                card.hidden = !show;
                if (show) visible += 1;
            }
            group.hidden = visible === 0;
            total += visible;
        }
        for (const chip of chips) chip.setAttribute('aria-pressed', String(chip.dataset.filter === chosen));
        if (empty) {
            empty.hidden = total > 0;
            if (emptyLine && total === 0) emptyLine.textContent = (emptyLine.dataset.words ?? '').replace(':q', search?.value.trim() ?? '');
        }
        if (shown && say) shown.textContent = countWords(total);
    }

    for (const chip of chips) {
        chip.addEventListener('click', () => {
            chosen = chip.dataset.filter;
            keep(chosen);
            apply(true);
        });
    }
    search?.addEventListener('input', () => apply(true));
    document.querySelector('[data-courses-reset]')?.addEventListener('click', () => {
        if (search) search.value = '';
        chosen = 'all';
        keep(chosen);
        apply(true);
        chips[0]?.focus();
    });
    apply();
}
