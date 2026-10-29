// The lyrics in the studio (the desktop's lyrics lane) and the joining of sung syllables into words.
// Practising a part lives in Sing (views/sing.js), which replaced the studio's practice bar.

/** "A-", "maz-", "ing" reads as "Amazing". */
export function joinSyllables(syllables) {
    return syllables.reduce((text, s) => text + (s.text.endsWith('-') ? s.text.slice(0, -1) : `${s.text} `), '').trim();
}

/** Under the studio's ruler (81.png): each line of the words where it's sung, the one now filled; a tap goes there. */
export const LyricsLane = {
    props: { lines: Array, lengthSeconds: Number, position: Number }, // lines: [{ startSeconds, endSeconds, text }]
    emits: ['seek'],
    template: `
        <div class="relative h-7" translate="no" data-lyrics-lane>
            <button v-for="(line, i) in lines" :key="i" type="button" class="absolute inset-y-0.5 truncate rounded-md px-2 text-start text-[11px] transition"
                :class="i === now ? 'bg-raised font-bold text-cream' : 'text-muted hover:text-cream'" :style="place(line)" :aria-current="i === now ? 'true' : null" @click="$emit('seek', line.startSeconds)">{{ line.text }}</button>
        </div>`,
    computed: {
        now() {
            return this.lines.findIndex((line) => this.position >= line.startSeconds - 0.02 && this.position < line.endSeconds);
        },
    },
    methods: {
        place(line) {
            const at = (seconds) => Math.max(0, Math.min(100, (seconds / this.lengthSeconds) * 100));
            return { left: at(line.startSeconds) + '%', width: Math.max(1, at(line.endSeconds) - at(line.startSeconds)) + '%' };
        },
    },
};
