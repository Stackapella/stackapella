// Try one round (docs/lesson-page-try-it-plan.md; the lesson_try switch): a lesson's public page lets a reader try its
// first listen-and-choose round right there, with no mic. The round and its words come in the page; nothing loads until
// the reader taps Try it here, then the guide voice (ear-rounds.js renderClip, the same one the app's lessons and the
// ear trainers sing with) plays on the free tools' player (tones.js, on the audio clock). The choices are buttons, the
// result is said in words (a status line), and Keep going in the app opens the lesson there (?ref=lesson-try, so Growth
// counts it). Nothing is sent or kept. No framework; no text from the page is ever parsed as HTML.

// A page knows no singer's comfortable note, so its rounds sit in the middle of most voices (A3).
const BASE = 57;
const LETTERS = 'ABCDEFGH';

for (const box of document.querySelectorAll('[data-lesson-try]')) wire(box);

function wire(box) {
    let round;
    let words;
    try {
        round = JSON.parse(box.querySelector('[data-lesson-try-round]').textContent);
        words = JSON.parse(box.querySelector('[data-lesson-try-words]').textContent);
    } catch (error) {
        console.warn('The round on this page is unreadable', error);
        return;
    }
    const start = box.querySelector('[data-lesson-try-start]');
    start?.addEventListener('click', () => open(box, round, words, start));
}

/** The guide voice and the player, fetched on the first tap. */
let kit = null;
async function engine() {
    if (kit) return kit;
    const [{ renderClip }, { sound }, { audio }] = await Promise.all([import('../ear-rounds.js'), import('./tones.js'), import('./voices.js')]);
    kit = { renderClip, sound, audio };
    return kit;
}

function element(tag, className = '', text = null) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== null) node.textContent = text;
    return node;
}

const SVG = 'http://www.w3.org/2000/svg';
const MARKS = { right: 'M5 12.5l4.5 4.5L19 7', yours: 'M7 7l10 10M17 7L7 17' }; // a tick, a cross

/**
 * A choice's mark after the answer (the coordinator, 2026-11-09: the choices only greyed out): the right one a tick in
 * the success colour, a wrong pick a cross as yours, each said in words to a screen reader too, never by colour alone.
 */
function mark(choice, kind, words, picked) {
    const icon = document.createElementNS(SVG, 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('aria-hidden', 'true');
    icon.setAttribute('class', `size-4 shrink-0 ${kind === 'right' ? 'text-good' : 'text-bad'}`);
    const path = document.createElementNS(SVG, 'path');
    path.setAttribute('d', MARKS[kind]);
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '3');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    icon.append(path);
    const said = element('span', 'sr-only', ` (${[picked ? words.choose : null, kind === 'right' ? words.right : null].filter(Boolean).join(', ')})`);
    choice.prepend(icon);
    choice.append(said);
    choice.classList.add('inline-flex', 'items-center', 'gap-2');
    choice.style.opacity = '1'; // answered: the marked ones stay clear while the rest dim (disabled)
    choice.dataset.lessonTryMark = kind;
}

function unmark(choice) {
    choice.querySelector('svg')?.remove();
    choice.querySelector('.sr-only')?.remove();
    choice.classList.remove('inline-flex', 'items-center', 'gap-2');
    choice.style.opacity = '';
    delete choice.dataset.lessonTryMark;
}

function button(text, className) {
    const node = element('button', className, text);
    node.type = 'button';
    return node;
}

async function open(box, round, words, start) {
    // The round is drawn at once, in the tap's own moment, with its buttons waiting for the voice: drawn only once the
    // voice had loaded (over half a second on a slow line), it pushed the page down as a layout shift (CLS).
    start.remove();
    const status = element('p', 'text-sm');
    status.setAttribute('role', 'status');
    const panel = element('div', 'mt-2 grid gap-3');
    if (round.prompt) panel.append(element('p', 'font-semibold text-cream', round.prompt));

    // The clips, each its own Play.
    let playing = null;
    const plays = element('div', 'flex flex-wrap gap-2');
    round.clips.forEach((clip, i) => {
        const play = button(round.clips.length === 1 ? words.play : words.playClip.replace(':letter', LETTERS[i]), 'btn-secondary');
        play.dataset.lessonTryPlay = String(i);
        play.addEventListener('click', () => {
            playing?.stop();
            const samples = kit.renderClip(clip, BASE, { tempo: round.tempo, sampleRate: kit.audio().sampleRate });
            playing = kit.sound(samples, { at: kit.audio().currentTime + 0.05 });
        });
        plays.append(play);
    });

    // The choices: the round's options, or the clips themselves.
    const choices = element('div', 'flex flex-wrap gap-2');
    choices.setAttribute('role', 'group');
    choices.setAttribute('aria-label', words.choose);
    const labels = round.options ?? round.clips.map((_, i) => words.choiceClip.replace(':letter', LETTERS[i]));
    const after = element('div', 'flex flex-wrap items-center gap-3');
    after.hidden = true;
    labels.forEach((label, i) => {
        const choice = button(label, 'btn-secondary');
        choice.dataset.lessonTryChoice = String(i);
        choice.addEventListener('click', () => {
            for (const other of choices.children) other.disabled = true;
            const right = i === round.answer;
            mark(choices.children[round.answer], 'right', words, right);
            if (!right) mark(choice, 'yours', words, true);
            box.dataset.answered = right ? 'right' : 'wrong';
            status.textContent = `${right ? words.right : words.wrong}${round.why ? ` ${round.why}` : ''}`;
            after.hidden = false;
        });
        choices.append(choice);
    });

    const keep = element('a', 'btn-primary', words.keepGoing);
    keep.href = round.start;
    keep.dataset.lessonTryApp = '';
    const again = button(words.again, 'btn-ghost');
    again.addEventListener('click', () => {
        for (const choice of choices.children) {
            unmark(choice);
            choice.disabled = false;
        }
        delete box.dataset.answered;
        status.textContent = '';
        after.hidden = true;
        plays.querySelector('button')?.focus();
    });
    after.append(keep, again);

    panel.append(plays, choices, status, after);
    box.append(panel);
    const waiting = [...plays.children, ...choices.children];
    for (const control of waiting) control.disabled = true;
    try {
        await engine();
    } catch (error) {
        console.warn('The round could not load', error);
        status.textContent = words.failed;
        again.hidden = true;
        after.hidden = false; // the way on: the lesson in the app
        return;
    }
    for (const control of waiting) control.disabled = false;
    plays.querySelector('button')?.focus();
}
