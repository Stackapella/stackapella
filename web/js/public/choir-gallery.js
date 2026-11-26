// The choir gallery's players (server/resources/views/site/choir-gallery.blade.php; docs/choir-gallery-plan.md, 2;
// Hillel's gallery design, 2026-11-26): each choir's own recording, one at a time. With the script, the round play
// button and the progress bar take over from the native controls. The button is named for its song ("Play <song>", then
// "Stop", which goes back to the start, as the home's player does). The bar is a slider: a press or a drag on it, or the
// arrow keys (5 s), Page Up and Down (15 s), Home and End, move the choir's place, which the time beside it says. A card
// never changes size. Without the script each choir is its own <audio> player. Every word comes from the page.

const clock = (seconds) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;

/** Every choir's audio on the page, so one starting stops the rest. */
const all = [...document.querySelectorAll('[data-gallery-audio]')];

for (const item of document.querySelectorAll('[data-gallery-item]')) {
    const audio = item.querySelector('[data-gallery-audio]');
    const button = item.querySelector('[data-gallery-play]');
    const track = item.querySelector('[data-gallery-track]');
    const bar = item.querySelector('[data-gallery-seek]');
    const fill = item.querySelector('[data-gallery-fill]');
    const now = item.querySelector('[data-gallery-now]');
    if (!audio || !button || !track || !bar) continue;
    audio.controls = false;
    audio.classList.add('hidden');
    button.classList.remove('hidden');
    track.classList.remove('hidden');
    // The length the page knows, until the file says its own.
    const length = () => (Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : Number(audio.dataset.seconds) || 0);

    const draw = () => {
        const total = length();
        const at = Math.min(audio.currentTime, total);
        fill.style.width = `${total ? (at / total) * 100 : 0}%`;
        now.textContent = clock(at);
        bar.setAttribute('aria-valuemax', String(Math.round(total)));
        bar.setAttribute('aria-valuenow', String(Math.round(at)));
        bar.setAttribute('aria-valuetext', `${clock(at)} / ${clock(total)}`);
    };
    const show = (playing) => {
        button.setAttribute('aria-pressed', String(playing));
        button.setAttribute('aria-label', playing ? button.dataset.labelStop : button.dataset.labelPlay);
        item.toggleAttribute('data-playing', playing);
    };
    const play = () => {
        for (const other of all) {
            if (other !== audio && !other.paused) {
                other.pause();
                other.currentTime = 0;
            }
        }
        audio.play().catch((error) => {
            console.warn('The choir could not play', error);
            show(false);
        });
    };
    const seek = (seconds) => {
        audio.currentTime = Math.max(0, Math.min(length(), seconds));
        draw();
    };

    button.addEventListener('click', () => {
        if (!audio.paused) {
            audio.pause();
            audio.currentTime = 0;
            draw();
            return;
        }
        play();
    });
    audio.addEventListener('play', () => show(true));
    audio.addEventListener('pause', () => show(false));
    audio.addEventListener('timeupdate', draw);
    audio.addEventListener('loadedmetadata', draw);
    audio.addEventListener('ended', () => {
        audio.currentTime = 0;
        show(false);
        draw();
    });

    // A press or a drag on the bar puts the choir there.
    const place = (event) => {
        const box = bar.getBoundingClientRect();
        seek(((event.clientX - box.left) / Math.max(1, box.width)) * length());
    };
    bar.addEventListener('pointerdown', (event) => {
        bar.setPointerCapture(event.pointerId);
        place(event);
        const move = (e) => place(e);
        const up = () => {
            bar.removeEventListener('pointermove', move);
            bar.removeEventListener('pointerup', up);
            bar.removeEventListener('pointercancel', up);
        };
        bar.addEventListener('pointermove', move);
        bar.addEventListener('pointerup', up);
        bar.addEventListener('pointercancel', up);
    });
    bar.addEventListener('keydown', (event) => {
        const steps = { ArrowRight: 5, ArrowUp: 5, ArrowLeft: -5, ArrowDown: -5, PageUp: 15, PageDown: -15 };
        if (event.key in steps) seek(audio.currentTime + steps[event.key]);
        else if (event.key === 'Home') seek(0);
        else if (event.key === 'End') seek(length());
        else return;
        event.preventDefault();
    });
}
