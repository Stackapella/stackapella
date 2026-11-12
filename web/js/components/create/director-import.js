// A director's "Make tracks from a score" (docs/score-import-plan.md, section 4): the group page's second way beside
// "Send us your song". A MusicXML or MIDI file is read on the device and opens in Bring your music with the director's
// additions (the tune, the proposals, whose music it is, the group); Make the tracks saves the song private, as an
// import with its rights record, and shares it into the group in one step, then opens it to listen through. A PDF or
// photos go to Send us your song instead. Shown only where sheet_import allows directors (the group page decides).

import { api } from '../../api.js';
import { mockWanted } from '../../api-mode.js';
import { t } from '../../i18n.js';
import { navigate } from '../../router.js';
import { readSheetFile } from '../../sheet-import.js';
import { saveTemplate } from '../../stores/templates.js';
import { count } from '../../usage.js';
import { toast } from '../ui.js';
import { SheetImport } from './sheet-import.js';

const SCORE_FILES = '.musicxml,.mxl,.xml,.mid,.midi,application/vnd.recordare.musicxml+xml,application/vnd.recordare.musicxml,audio/midi';

export const DirectorImport = {
    components: { SheetImport },
    // group: the group page's own, { id, name }: preset as the one the song is for, among all the groups this person directs.
    props: { group: { type: Object, required: true } },
    emits: ['send', 'made'],
    data: () => ({ score: null, saving: false, directed: null, SCORE_FILES }),
    template: `
        <span class="contents">
            <input ref="file" type="file" class="sr-only" :accept="SCORE_FILES" tabindex="-1" aria-hidden="true" data-director-score @change="opened" />
            <SheetImport v-if="score" :score="score" :busy="saving" :director="{ groups: groups, groupId: group.id }" @close="score = null" @make="make" />
        </span>`,
    computed: {
        /** The groups to choose from: every one this person directs, this page's first. */
        groups() {
            const others = (this.directed ?? []).filter((g) => g.id !== this.group.id);
            return [{ id: this.group.id, name: this.group.name }, ...others.map((g) => ({ id: g.id, name: g.name }))];
        },
    },
    methods: {
        /** The file picker, from the group page's link. */
        open() {
            this.$refs.file.value = '';
            this.$refs.file.click();
        },
        async opened(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            // A PDF or photos of the pages aren't read here: Send us your song takes them, and we make the tracks.
            if (/\.(pdf|png|jpe?g|heic|webp)$/i.test(file.name) || /^(image\/|application\/pdf)/.test(file.type)) {
                toast(t('sheet.director.pdf'), { actions: [{ label: t('groups.tracks.askLink'), run: () => this.$emit('send') }] });
                return;
            }
            const score = await readSheetFile(file);
            if (score.unreadable || score.unsupported === 'timewise') toast(t('sheet.error.unreadable'), { tone: 'error' });
            else if (score.unsupported === 'metre') toast(t('sheet.error.metre', { metre: score.metre ?? '?' }), { tone: 'error' });
            else if (score.unsupported === 'metreChange') toast(t('sheet.error.metreChange', { metre: score.metre ?? '?' }), { tone: 'error' });
            else if (score.unsupported) toast(t('sheet.error.empty'), { tone: 'error' });
            else {
                if (!this.directed) {
                    try {
                        this.directed = (await api.groups.list()).data.filter((g) => g.role === 'director');
                    } catch (error) {
                        console.warn('The groups you direct were not loaded: this one is offered', error);
                        this.directed = [];
                    }
                }
                this.score = score;
            }
        },
        /** Saved private as an import with whose music it is, then shared into the group: one step for the director. */
        async make(template, { rights, groupId }) {
            this.saving = true;
            try {
                const record = await saveTemplate(template, { visibility: 'private', source: 'sheet-music', rights });
                // The group can only have it once the account does (the mock keeps templates on the device).
                if (record.state !== 'saved' && !mockWanted()) throw new Error(`the song was not saved to the account (${record.state})`);
                count('sheet_import');
                try {
                    await api.groups.songs.create(groupId, { templateId: record.id });
                } catch (error) {
                    console.error('The song was saved but not shared into the group', error);
                    const name = this.groups.find((g) => g.id === groupId)?.name ?? '';
                    toast(t('sheet.director.notShared', { group: name }), { tone: 'error', actions: [{ label: t('common.tryAgain'), run: () => this.share(record, groupId) }] });
                    return;
                }
                this.done(record, groupId);
            } catch (error) {
                console.error('The song from the score was not saved', error);
                toast(t('create.notSaved'), { tone: 'error' });
            } finally {
                this.saving = false;
            }
        },
        async share(record, groupId) {
            try {
                await api.groups.songs.create(groupId, { templateId: record.id });
                this.done(record, groupId);
            } catch (error) {
                console.error('The song was not shared into the group', error);
                toast(t('create.notSaved'), { tone: 'error' });
            }
        },
        done(record, groupId) {
            this.score = null;
            this.$emit('made', { id: record.id, groupId });
            toast(t('sheet.director.made', { title: record.template.title }));
            navigate(`/t/${record.id}`);
        },
    },
};
