// The user guide's search and folds (server/resources/views/pages/help-page.blade.php and help.blade.php).
// The search's rows (/help/search-index.json: each page and each of its sections) are fetched the first
// time a search box is used, then matched as you type: every word must appear, a heading's match ranks
// first. Arrow keys move through the results, Enter opens one, Escape closes them. On a wide screen the
// contents and "On this page" stand open beside the page; on a phone they fold.

const WIDE = window.matchMedia('(min-width: 1100px)');
const folds = document.querySelectorAll('details[data-open-wide]');
const unfold = () => folds.forEach((fold) => { fold.open = WIDE.matches; });
unfold();
WIDE.addEventListener('change', unfold);

// A link to a folded section (the mic page's devices) opens it.
const openTarget = () => {
    const target = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
    if (target instanceof HTMLDetailsElement) target.open = true;
};
openTarget();
window.addEventListener('hashchange', openTarget);

let rows = null;
const load = (url) => {
    rows ??= fetch(url, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : []))
        .catch(() => []);
    return rows;
};

const words = (text) => text.toLocaleLowerCase().split(/\s+/).filter(Boolean);

const find = (all, query) => {
    const wanted = words(query);
    if (!wanted.length) return [];
    const found = [];
    for (const row of all) {
        const heading = `${row.title} ${row.page}`.toLocaleLowerCase();
        const body = `${heading} ${row.text}`.toLocaleLowerCase();
        if (!wanted.every((word) => body.includes(word))) continue;
        found.push({ row, score: wanted.filter((word) => heading.includes(word)).length });
    }
    return found.sort((a, b) => b.score - a.score).slice(0, 8).map((hit) => hit.row);
};

for (const box of document.querySelectorAll('[data-help-search]')) {
    const input = box.querySelector('input');
    const list = box.querySelector('[role="listbox"]');
    const status = box.querySelector('[data-status]');
    let active = -1;

    const options = () => [...list.querySelectorAll('[role="option"]')];
    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const mark = (index) => {
        const all = options();
        active = all.length ? (index + all.length) % all.length : -1;
        all.forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        if (active >= 0) {
            input.setAttribute('aria-activedescendant', all[active].id);
            all[active].scrollIntoView({ block: 'nearest' });
        }
    };
    const show = (hits) => {
        list.replaceChildren(...hits.map((row, i) => {
            const option = document.createElement('a');
            option.id = `${list.id}-${i}`;
            option.href = row.url;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.tabIndex = -1;
            const title = document.createElement('b');
            title.textContent = row.title;
            const where = document.createElement('span');
            where.textContent = row.title === row.page ? row.text : row.page;
            option.append(title, where);
            return option;
        }));
        if (!hits.length) {
            const none = document.createElement('p');
            none.className = 's-hsearch-none';
            none.textContent = box.dataset.none;
            list.append(none);
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        status.textContent = hits.length ? (hits.length === 1 ? box.dataset.one : box.dataset.many).replace(':count', hits.length) : box.dataset.none;
        active = -1;
        input.removeAttribute('aria-activedescendant');
    };

    input.addEventListener('focus', () => load(box.dataset.helpSearch), { once: true });
    input.addEventListener('input', async () => {
        const query = input.value;
        if (!query.trim()) return close();
        const all = await load(box.dataset.helpSearch);
        if (input.value === query) show(find(all, query));
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (list.hidden) return;
            event.preventDefault();
            mark(active + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter') {
            const chosen = options()[active] ?? options()[0];
            if (chosen && !list.hidden) {
                event.preventDefault();
                window.location.href = chosen.href;
            }
        } else if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault();
            close();
        }
    });
    box.addEventListener('focusout', (event) => {
        if (!box.contains(event.relatedTarget)) close();
    });
}
