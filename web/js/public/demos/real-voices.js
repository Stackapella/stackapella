// Real voices' live demo (docs/demo-pages-plan.md, feature pages): one part of a song's first line sung by the
// app's practice voice and by a real singer, the other parts underneath, switched while it plays without a
// restart (js/public/ab-voice.js, the voice Sing plays for that part). The still frame is the server's
// (site/_demo-section, kind `voices`); this wires it once the slot's start is pressed, and plays at once.
// A part with no real voice yet says so, and the practice voice sings it. Everything sounds on the
// AudioContext clock (demo-player.js).

import { abVoice } from '../ab-voice.js';
import { catalogueGuides } from '../app-voices.js';
import { play, sounding } from '../demo-player.js';

export async function mount(stage) {
    const root = stage.querySelector('[data-ab]');
    const song = JSON.parse(root.querySelector('[data-ab-song]').textContent);
    const part = root.dataset.part;
    const say = root.querySelector('[data-ab-say]');
    const credit = root.querySelector('[data-ab-credit]');
    const button = root.querySelector('[data-ab-play]');
    const choices = [...root.querySelectorAll('[data-ab-use]')];
    const [ab, guides] = await Promise.all([abVoice({ song, part }), catalogueGuides(song.song, { version: song.version })]);
    const guide = guides?.[part] ?? null;

    const choose = (kind) => {
        ab.use(kind);
        for (const el of choices) el.setAttribute('aria-pressed', String(el.dataset.abUse === ab.kind));
        say.textContent = root.dataset[ab.kind === 'real' ? 'sayReal' : 'saySynth'];
        // The credit names who sings: shown only while the real singer does.
        if (credit) credit.hidden = !(ab.kind === 'real' && credit.textContent);
    };

    if (ab.hasReal) {
        // The singer licensed their recording for the site when they recorded it; the app credits them on the part.
        if (guide?.credit && credit) {
            credit.textContent = root.dataset.credit.replace(':name', guide.credit);
        }
        for (const el of choices) {
            el.disabled = false;
            el.addEventListener('click', () => choose(el.dataset.abUse));
        }
        choose('real');
    } else {
        // A real voice that didn't arrive (the network) isn't a part without one: say which it is.
        say.textContent = guide ? root.dataset.failed : root.dataset.missing;
    }
    button.disabled = false;
    button.addEventListener('click', () => play(button, () => ab.start()));
    if (!sounding(button)) button.click();
}
