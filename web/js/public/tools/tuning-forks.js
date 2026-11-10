// The tuning forks (/tools/tuning-forks), on the tools' one layout (tool-layout.js; docs/design-rules.md, "Free tools
// share one layout"). The hero is one big fork to strike, A440 first, with a ring that empties as it fades; the status
// pill says it's ringing; the readout is the shelf of forks (each pick strikes it too) and what the chosen one is
// for; the bar's primary strikes it. A press strikes the fork and holds it ringing; let go, it rings on and fades by
// itself, as a real one does. More's sheet holds your own fork at any Hz (with its nearest note), more forks, keeping
// a fork ringing until it's tapped again, several ringing together (to hear them beat), and a speaker boost for the
// low forks. The address names the fork (?f=415), so a link opens it, your own too. Each shelf fork's Hz is in its
// words. No key control: a fork is its own reference.
// UI and state only: the sound is tones.js's strike, the nearest note dsp.js's, its name notes.js's.
//
//   mountTuningForks(el, { words, keySlot })   // words: every key in WORDS; returns { stop() }

import { nearestNote } from '../../dsp.js';
import { pitchName } from '../../notes.js';
import { toolLayout } from '../tool-layout.js';
import { strike } from '../tones.js';
import { warmAudio } from '../voices.js';
import { atClock, fill, num, onHidden, press, remember, switchRow, toggle, unlock } from './kit.js';

/** The words the page passes (lang site.tools.pages.tuning-forks.card). */
export const WORDS = [
    'shelf', 'more_forks', 'shelf_label', 'more_label', 'idle', 'ringing', 'held', 'latched', 'strike', 'strike_label', 'more', 'done',
    'latch', 'together', 'boost', 'custom_label', 'custom_strike', 'custom_invalid', 'custom_name', 'custom_tag', 'nearest', 'sharp',
    'flat', 'exact', 'custom_about', 'notes',
    'settingsTitle', 'reset',
];

// Everything the tuning forks can be set to, in one place.
export const FORKS = {
    first: 'a440',
    level: 0.3, // one fork, a pure tone
    together: 0.15, // each fork's level when several ring at once
    most: 4, // forks ringing together at most (the oldest is damped): together they stay well under clipping
    boost: 0.35, // the octave above, against the fork's own level, with Speaker boost
    decay: 7, // seconds a struck fork rings
    own: [20, 4000], // your own fork's Hz, lowest and highest
    exact: 0.05, // cents from a note that count as on it
};

const HERO = `
<button type="button" class="s-fork" data-strike>
  <span class="s-fork-name" translate="no" data-fork-name></span>
  <span class="s-fork-tag" data-fork-tag></span>
  <span class="s-fork-ring" aria-hidden="true"><b></b></span>
</button>`;

const READOUT = `
<div class="s-fork-shelf" role="group" data-shelf></div>
<p class="help" data-fork-about></p>`;

const SHEET = `
<div>
  <label class="label" for="fork-hz" data-w="custom_label"></label>
  <div class="s-fork-own mt-2">
    <input id="fork-hz" class="input" type="number" inputmode="decimal" step="0.01" value="440" aria-describedby="fork-near" data-fork-hz>
    <span data-own></span>
  </div>
  <p class="help mt-1.5" id="fork-near" aria-live="polite" data-fork-near></p>
</div>
<div>
  <p class="label" id="more-forks-l" data-w="more_label"></p>
  <div class="s-fork-shelf mt-2" role="group" aria-labelledby="more-forks-l" data-more-shelf></div>
</div>
<div data-switch="latch"></div>
<div data-switch="together"></div>
<div data-switch="boost"></div>`;

export function mountTuningForks(el, { words = {}, keySlot = null, preset = null } = {}) {
    warmAudio(); // the sound's AudioContext made while the page is idle, so the first tap only resumes it (voices.js)
    const config = { ...FORKS };
    // A variant page's set (docs/seo-growth-plan.md, item 3: the A432 page): `{ first, shelf }`, the fork chosen
    // first and the forks on the shelf in that order (from either list; the rest move to More). Nothing is saved.
    const every = { ...(words.shelf ?? {}), ...(words.more_forks ?? {}) };
    const shelfIds = (preset?.shelf ?? []).filter((id) => id in every);
    const onShelf = shelfIds.length ? shelfIds : Object.keys(words.shelf ?? {});
    const inMore = Object.keys(every).filter((id) => !onShelf.includes(id));
    if (preset?.first && preset.first in every) config.first = preset.first;
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(el, { keySlot, more: w('more'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.element.dataset.tool = 'tuning-forks';
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.sheet.innerHTML = SHEET;
    const $ = (sel) => el.querySelector(sel);
    for (const node of el.querySelectorAll('[data-w]')) node.textContent = w(node.dataset.w);
    ui.primary.dataset.start = '';
    const big = $('[data-strike]');
    big.style.setProperty('--decay', `${config.decay}s`);

    // The forks: the shelf's, More's, and your own (its Hz typed in), each a chip that picks it and strikes it.
    const chip = (id, fork, label = fork.name) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 's-tool-pick s-fork-chip';
        Object.assign(b.dataset, { fork: id, hz: String(fork.hz), name: fork.name, tag: fork.tag, about: fork.about });
        b.setAttribute('aria-pressed', 'false');
        b.setAttribute('translate', 'no');
        b.textContent = label;
        return b;
    };
    $('[data-shelf]').setAttribute('aria-label', w('shelf_label'));
    $('[data-shelf]').append(...onShelf.map((id) => chip(id, every[id])));
    $('[data-more-shelf]').append(...inMore.map((id) => chip(id, every[id])));
    const own = chip('custom', { hz: 440, name: '', tag: w('custom_tag'), about: '' }, w('custom_strike'));
    own.removeAttribute('translate');
    $('[data-own]').replaceWith(own);
    const chips = [...el.querySelectorAll('[data-fork]')];
    const ownHz = $('[data-fork-hz]');
    ownHz.min = String(config.own[0]);
    ownHz.max = String(config.own[1]);
    const near = $('[data-fork-near]');

    const saved = remember('tuning-forks', { latch: false, together: false, boost: false });
    const set = saved.value;
    const switches = {
        latch: toggle(switchRow($('[data-switch=latch]'), 'latch', w('latch')), (on) => {
            saved.save({ latch: on });
            if (!on) quietAll();
        }),
        together: toggle(switchRow($('[data-switch=together]'), 'together', w('together')), (on) => saved.save({ together: on })),
        boost: toggle(switchRow($('[data-switch=boost]'), 'boost', w('boost')), (on) => saved.save({ boost: on })),
    };
    Object.entries(switches).forEach(([k, s]) => s.set(set[k]));
    // More's Reset: the switches back to their defaults, and every fork quiet.
    ui.onReset(() => {
        saved.reset();
        Object.entries(switches).forEach(([k, s]) => s.set(set[k]));
        quietAll();
    });

    // Your own fork: its nearest note against A440, said under it.
    function tune() {
        const hz = Number(ownHz.value);
        const ok = ownHz.value !== '' && hz >= config.own[0] && hz <= config.own[1];
        ownHz.setAttribute('aria-invalid', String(!ok));
        own.disabled = !ok;
        if (!ok) {
            near.textContent = w('custom_invalid');
            return;
        }
        const { midi, cents } = nearestNote(hz);
        const off = Math.abs(cents) < config.exact ? w('exact') : fill(cents > 0 ? w('sharp') : w('flat'), { n: num(Math.abs(cents), 1) });
        const note = `${(words.notes ?? [])[((midi % 12) + 12) % 12] ?? ''}${pitchName(midi).octave}`;
        near.textContent = fill(w('nearest'), { note, off });
        own.dataset.hz = String(Math.round(hz * 100) / 100);
        own.dataset.name = fill(w('custom_name'), { hz: own.dataset.hz });
        own.dataset.about = fill(w('custom_about'), { note, off });
        if (chosen === own) choose(own);
    }
    ownHz.addEventListener('input', tune);

    const linked = new URLSearchParams(location.search).get('f');
    let chosen = chips.find((c) => c !== own && c.dataset.hz === linked) ?? null;
    if (!chosen && linked && Number(linked) >= config.own[0] && Number(linked) <= config.own[1]) {
        ownHz.value = linked;
        chosen = own;
    }
    chosen ??= chips.find((c) => c.dataset.fork === config.first);
    const ringing = new Map(); // fork id -> { tones, held, cancel }

    function render() {
        chips.forEach((c) => c.toggleAttribute('data-ringing', ringing.has(c.dataset.fork)));
        const now = ringing.get(chosen.dataset.fork);
        if (now) big.dataset.ringing = now.held ? 'held' : 'decay';
        else delete big.dataset.ringing;
        if (now) ui.setStatus({ tone: 'good', text: now.held ? (set.latch ? w('latched') : w('held')) : w('ringing') });
        else ui.setStatus({ hint: w('idle') });
        ui.setPrimary({ label: fill(w('strike'), { name: chosen.dataset.name }), active: Boolean(now) });
    }

    function choose(chip) {
        chosen = chip;
        chips.forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
        $('[data-fork-name]').textContent = chip.dataset.name;
        $('[data-fork-tag]').textContent = chip.dataset.tag;
        $('[data-fork-about]').textContent = chip.dataset.about;
        const label = fill(w('strike_label'), { name: chip.dataset.name });
        big.setAttribute('aria-label', label);
        ui.primary.setAttribute('aria-label', label);
        const url = new URL(location.href);
        if (chip.dataset.fork === config.first) url.searchParams.delete('f');
        else url.searchParams.set('f', chip.dataset.hz);
        history.replaceState(history.state, '', url);
        render();
    }

    // Stops a fork at once (a fade of about 100 ms).
    function damp(id) {
        const was = ringing.get(id);
        if (!was) return;
        was.cancel?.();
        was.tones.forEach((t) => t.stop());
        ringing.delete(id);
    }

    function quietAll() {
        [...ringing.keys()].forEach(damp);
        render();
    }

    // Struck and held at full while pressed (with Keep ringing on, until tapped again); let go, it rings out.
    function sound(chip) {
        unlock();
        const id = chip.dataset.fork;
        if (set.latch && ringing.has(id)) {
            damp(id); // tapped again: stopped
            render();
            return;
        }
        [...ringing.keys()].filter((other) => other === id || !set.together).forEach(damp);
        while (ringing.size >= config.most) damp(ringing.keys().next().value);
        const f = Number(chip.dataset.hz);
        const level = set.together ? config.together : config.level;
        const shape = { level, decay: config.decay, hold: true };
        const tones = [strike(f, shape)];
        if (set.boost) tones.push(strike(f * 2, { ...shape, level: level * config.boost }));
        ringing.set(id, { tones, held: true });
        if (chip === chosen) {
            // The ring starts full again: the fade restarts with the strike.
            delete big.dataset.ringing;
            void big.offsetWidth;
        }
        render();
    }

    // Let go: it rings on by itself and fades over `decay`, and the display lets go of it when the sound does.
    function letGo(chip) {
        const id = chip.dataset.fork;
        const now = ringing.get(id);
        if (!now || !now.held || set.latch) return;
        now.tones.forEach((t) => t.ring());
        now.held = false;
        now.cancel = atClock(unlock().currentTime + config.decay, () => {
            if (ringing.get(id) !== now) return;
            ringing.delete(id);
            render();
        });
        render(); // the ring's fade starts as it goes from held to dying away
    }

    for (const pad of [big, ui.primary]) press(pad, { down: () => sound(chosen), up: () => letGo(chosen) });
    chips.forEach((c) => press(c, {
        down: () => {
            choose(c);
            sound(c);
        },
        up: () => letGo(c),
    }));
    tune();
    choose(chosen);

    const off = onHidden(quietAll);
    return {
        stop() {
            quietAll();
            off();
        },
    };
}
