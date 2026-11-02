// Choir groups' live demo in the shared slot (the Choirs feature page; docs/demo-pages-plan.md): the
// director's view of one shared song, as the app shows it (js/views/groups.js): the song's summary
// ("3 of 4 parts covered · 1 recorded"), the part strip (each part, who sings it and how far they've
// got: Not started, Learning, Recorded, or Nobody yet), and Assign parts, a list of the members with a
// part for each. Next day moves the week on to rehearsal: each singer with a part gets a little
// further, at their own pace, so the board fills up. A demo choir, made-up names, nothing sent: the
// state lives in this page. The words are the view's (the frame's JSON).

const ORDER = ['none', 'started', 'recorded'];

const fill = (text, values) => Object.entries(values).reduce((t, [k, v]) => t.replaceAll(`:${k}`, String(v)), text);
const el = (tag, attrs = {}, kids = []) => {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'text') node.textContent = v;
        else node.setAttribute(k, v);
    }
    node.append(...kids);
    return node;
};

export async function mount(stage) {
    const root = stage.querySelector('[data-choir]');
    const data = JSON.parse(root.querySelector('script[data-choir-data]').textContent);
    const { words, parts } = data;
    const start = () => data.members.map((m) => ({ ...m }));
    let members = start();
    let day = 0;
    let editing = false;

    const board = root.querySelector('[data-choir-board]');
    const say = root.querySelector('[data-choir-say]');
    const next = root.querySelector('[data-choir-next]');

    const covered = () => parts.filter((p) => members.some((m) => m.part === p.key)).length;
    function summary() {
        if (!members.some((m) => m.part)) return words.nobodyYet;
        const recorded = members.filter((m) => m.part && m.status === 'recorded').length;
        // As the app's summary (js/views/groups.js): the parts nobody has, while some do.
        const open = parts.filter((p) => !members.some((m) => m.part === p.key)).map((p) => p.name);
        const bits = [fill(words.covered, { count: covered(), total: parts.length }), fill(words.recorded, { count: recorded })];
        if (open.length && open.length < parts.length) bits.push(fill(words.noOne, { parts: new Intl.ListFormat(document.documentElement.lang || 'en', { type: 'disjunction' }).format(open) }));
        return bits.join(' · ');
    }

    // The day's line, and a nudge while a part has nobody on it.
    function tell(template, values = {}) {
        const gap = parts.find((p) => !members.some((m) => m.part === p.key));
        const line = fill(template, { day: words.days[day], summary: summary(), ...values });
        say.textContent = gap && day < words.days.length - 1 ? `${line} ${fill(words.gap, { part: gap.name })}` : line;
    }

    function draw() {
        const head = el('div', { class: 's-gb-head' }, [
            el('div', { class: 's-gb-t' }, [
                el('p', { class: 's-gb-song', text: data.song }),
                el('p', { class: `s-gb-sum${members.some((m) => m.part) ? '' : ' s-gb-warn'}`, text: summary() }),
            ]),
            el('button', { type: 'button', class: 's-gb-btn', 'data-choir-assign': '', 'aria-expanded': String(editing), text: editing ? words.done : words.assign }),
        ]);
        const strip = el('ul', { class: 's-gb-strip', 'aria-label': words.whoSings }, parts.map((p) => {
            const who = members.filter((m) => m.part === p.key);
            return el('li', { class: `s-gb-part s-v${p.color}` }, [
                el('span', { class: 's-gb-pname' }, [el('span', { class: 's-gb-dot', 'aria-hidden': 'true' }), el('span', { text: p.name })]),
                ...(who.length
                    ? who.map((m) => el('span', { class: 's-gb-who' }, [
                        el('b', { text: m.name }),
                        el('small', { class: `s-gb-${m.status}`, text: words.status[m.status] }),
                    ]))
                    : [el('span', { class: 's-gb-nobody', text: words.nobody })]),
            ]);
        }));
        const kids = [head, strip];
        if (editing) {
            kids.push(el('ul', { class: 's-gb-assign' }, members.map((m, i) => el('li', {}, [
                el('span', { text: m.name }),
                el('select', { 'data-member': String(i), 'aria-label': fill(words.partFor, { name: m.name }) }, [
                    el('option', { value: '', text: words.noPart }),
                    ...parts.map((p) => el('option', { value: p.key, text: p.name })),
                ]),
            ]))));
        }
        board.replaceChildren(...kids);
        board.querySelectorAll('select[data-member]').forEach((select) => { select.value = members[Number(select.dataset.member)].part ?? ''; });
        next.querySelector('[data-label]').textContent = day < words.days.length - 1 ? words.next : words.again;
    }

    // A day passes: each singer with a part moves on when their pace says so (the pace is the demo's
    // own, so the week always ends with most parts recorded and one still learning).
    function nextDay() {
        if (day >= words.days.length - 1) {
            members = start();
            day = 0;
            editing = false;
            draw();
            tell(words.dayNow);
            return;
        }
        day += 1;
        for (const m of members) {
            if (!m.part || m.status === 'recorded') continue;
            if ((day + m.pace) % 2 === 0) m.status = ORDER[ORDER.indexOf(m.status) + 1];
        }
        draw();
        tell(day === words.days.length - 1 ? words.rehearsal : words.dayNow);
    }

    board.addEventListener('click', (event) => {
        if (!event.target.closest('[data-choir-assign]')) return;
        editing = !editing;
        draw();
        board.querySelector(editing ? 'select[data-member]' : '[data-choir-assign]')?.focus();
    });
    board.addEventListener('change', (event) => {
        const select = event.target.closest('select[data-member]');
        if (!select) return;
        const m = members[Number(select.dataset.member)];
        // A new part starts from the beginning.
        if (m.part !== (select.value || null)) m.status = 'none';
        m.part = select.value || null;
        draw();
        tell(m.part ? words.assigned : words.unassigned, { name: m.name, part: parts.find((p) => p.key === m.part)?.name ?? '' });
        board.querySelector(`select[data-member="${select.dataset.member}"]`)?.focus();
    });
    next.addEventListener('click', nextDay);

    draw();
    tell(words.dayNow);
}
