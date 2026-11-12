// The tools hub's search and chips (/tools, Hillel's tools page, 2026-11-12), with no reload. The chips are links to
// their groups' sections (#tools-tune), so without this every tool is still listed and a chip jumps to its group; with
// it a chip shows that group alone and the search narrows the cards as you type. Most used shows only for All and no
// search. No imports: a public page's own module (tool/public_imports_check.py).

const search = document.querySelector('[data-tools-search]');
const chips = [...document.querySelectorAll('[data-tools-filter]')];
const groups = [...document.querySelectorAll('[data-tools-group]')];
const most = document.querySelector('[data-tools-most]');
const none = document.querySelector('[data-tools-none]');
const noneText = document.querySelector('[data-tools-none-text]');

let group = ''; // the chip chosen ('' is All)

function show() {
    const q = (search?.value ?? '').trim().toLowerCase();
    let shown = 0;
    for (const section of groups) {
        const inGroup = !group || section.dataset.toolsGroup === group;
        let any = false;
        for (const card of section.querySelectorAll('[data-tools-card]')) {
            const hit = inGroup && (!q || card.dataset.search.includes(q));
            card.hidden = !hit;
            any ||= hit;
        }
        section.hidden = !any;
        if (any) shown++;
    }
    if (most) most.hidden = !!q || !!group;
    for (const chip of chips) {
        const on = chip.dataset.toolsFilter === group;
        chip.classList.toggle('is-on', on);
        if (on) chip.setAttribute('aria-current', 'true');
        else chip.removeAttribute('aria-current');
    }
    if (none) {
        none.hidden = shown > 0;
        if (noneText) noneText.textContent = shown > 0 ? '' : (none.dataset.text ?? '').replace(':q', q);
    }
}

function choose(key) {
    group = key;
    show();
}

for (const chip of chips) {
    chip.addEventListener('click', (event) => {
        event.preventDefault();
        const key = chip.dataset.toolsFilter;
        choose(group === key ? '' : key);
        // The address names the group, so a shared link opens on it; All clears it.
        history.replaceState(history.state, '', group ? `#tools-${group}` : location.pathname + location.search);
    });
}
search?.addEventListener('input', () => {
    if (search.value.trim() && group) group = ''; // a search looks through every group
    show();
});
document.querySelector('[data-tools-reset]')?.addEventListener('click', () => {
    if (search) search.value = '';
    choose('');
    search?.focus();
});

// Opened on a group's address (#tools-voice): that group, as its chip shows it.
const opened = /^#tools-([a-z]+)$/.exec(location.hash)?.[1];
if (opened && chips.some((chip) => chip.dataset.toolsFilter === opened)) choose(opened);
