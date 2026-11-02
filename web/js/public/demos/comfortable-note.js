// Lesson 1's first step as a slotted demo (the Course feature page and Beginners; docs/demo-pages-plan.md):
// find your comfortable note. The mic opens only on Listen (listen.js, with the site's mic picker,
// mic-pick.js); hum any easy note and hold it, and once about two seconds of steady readings are in, the
// note they sit on is your comfortable note, marked on a ladder from C3 to C5. Nothing is recorded or
// sent: the pitch is worked out on this device and the mic closes when it's found or stopped. The
// words are the view's (the frame's data-*).

import { listen } from '../listen.js';
import { onPicked, preferredMic, savedMic, showPicker } from '../mic-pick.js';
import { unlock } from '../demo-player.js';

const NAMES = ['C', 'C♯', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'A♭', 'A', 'B♭', 'B'];
const nameOf = (midi) => `${NAMES[((midi % 12) + 12) % 12]}${Math.floor(midi / 12) - 1}`;
const STEADY = 2; // seconds of steady, voiced readings that make a note
const SPREAD = 0.6; // semitones a reading may sit from the running middle and still count as steady
const LOW = 48; // C3
const HIGH = 72; // C5

export async function mount(stage) {
    const root = stage.querySelector('[data-note]');
    const words = root.dataset;
    const button = root.querySelector('[data-note-listen]');
    const say = root.querySelector('[data-note-say]');
    const big = root.querySelector('[data-note-name]');
    const marker = root.querySelector('[data-note-marker]');
    const picker = root.querySelector('[data-note-pick]');
    let mic = null;
    let readings = [];

    const label = (on) => {
        button.setAttribute('aria-pressed', String(on));
        button.querySelector('[data-label]').textContent = on ? words.stop : words.listen;
    };

    function close() {
        mic?.stop();
        mic = null;
        label(false);
    }

    function found(midi) {
        close();
        big.textContent = nameOf(midi);
        root.dataset.done = '';
        const at = Math.max(0, Math.min(1, (midi - LOW) / (HIGH - LOW)));
        marker.style.setProperty('--at', String(at));
        marker.hidden = false;
        say.textContent = words.found.replace(':note', nameOf(midi));
    }

    // A reading counts when it's voiced and near the running middle of the last ones; enough of them in
    // a row, and their middle is the note.
    function hear(frame) {
        if (!frame.clear || frame.midi === null) {
            readings = [];
            return;
        }
        const middle = readings.length ? readings[Math.floor(readings.length / 2)].midi : frame.midi;
        if (Math.abs(frame.midi - middle) > SPREAD) readings = [];
        readings.push(frame);
        readings.sort((a, b) => a.midi - b.midi);
        const span = Math.max(...readings.map((r) => r.at)) - Math.min(...readings.map((r) => r.at));
        if (span >= STEADY) found(Math.round(readings[Math.floor(readings.length / 2)].midi));
    }

    if (picker) onPicked(picker, (id) => mic?.use(id).catch((error) => console.warn('That mic could not be opened', error)));

    button.addEventListener('click', async () => {
        if (mic) {
            close();
            say.textContent = words.ready;
            return;
        }
        unlock('play-and-record');
        readings = [];
        delete root.dataset.done;
        say.textContent = words.asking;
        try {
            mic = await listen({ onFrame: hear, deviceId: savedMic() });
        } catch (error) {
            unlock('playback');
            const why = { denied: words.whyDenied, 'no-mic': words.whyNoMic };
            say.textContent = why[error?.reason] ?? words.whyUnsupported;
            return;
        }
        const named = await preferredMic(mic.deviceId()).catch(() => null);
        if (named) await mic.use(named).catch((error) => console.warn('The remembered mic could not be opened', error));
        if (picker) await showPicker(picker, mic.deviceId()).catch((error) => console.warn('The mic picker could not be filled', error));
        label(true);
        say.textContent = words.hearing;
    });

    say.textContent = words.ready;
}
