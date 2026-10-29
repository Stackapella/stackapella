// Getting ready to record, shared by the studio and the Sing screens: the mic opens at once (during
// setup, so iOS's audio switch is over before the count-in), then the headphones question, the
// timing check when there's no timing for this mic (always a measured one for a guide everyone
// hears), and a beta take's sound check, then the recording starts. A component using it has
// `dialog`, `project`, `recording`, `playing`, `busy`, `rerecordId` and `leaving`, and its own
// finishRecording(); `recordsFromStart` (the studio's) records from 0:00 whatever the position.

import { audio, chooseRoute, openMic, unlock, useEstimate } from './audio.js';
import { micNeedsCheck, showMicCheck } from './mic-check.js';
import { t, tk } from './i18n.js';
import { local, session as tabStorage } from './storage.js';
import * as sessionStore from './stores/session.js';
import { micError } from './components/dialogs.js';
import { soundChecked } from './components/beta.js';
import { toast } from './components/ui.js';
import { lockReason } from './stores/plan.js';

const LOCKED_TOAST = { limit: tk('locked.limit'), plusSong: tk('locked.plusSong'), offline: tk('locked.offline') };

export const RecordSetup = {
    methods: {
        async recordOrStop() {
            if (this.busy) return;
            if (this.recording) {
                await this.finishRecording();
                return;
            }
            // A read-only project plays and exports, but takes no new recordings.
            const locked = lockReason(sessionStore.session.project);
            if (locked) {
                toast(t(LOCKED_TOAST[locked]), { seconds: 8 });
                this.rerecordId = null;
                return;
            }
            // A take is recorded at full speed, in time with everything else.
            if ((sessionStore.session.speed ?? 1) !== 1) {
                toast(t('sing.recordFullSpeed'), { seconds: 10, actions: [{ label: t('studio.fullSpeed'), run: () => sessionStore.setSpeed(1) }] });
                this.rerecordId = null;
                return;
            }
            try {
                this.busy = true;
                await unlock(); // first, while this is still the user's click
                // The mic opens now, during setup, so iOS's audio switch is over before the count-in.
                this.micOpening = openMic().then((mic) => (mic ? null : 'closed'), (error) => error);
                if (this.playing) await sessionStore.stopPlayback();
                if (!tabStorage.get('stackapella.headphones') && !local.get('stackapella.headphones.never')) {
                    this.dialog = 'headphones';
                    return;
                }
                await this.prepareMic();
            } finally {
                this.busy = false;
            }
        },
        async headphonesOn(never) {
            tabStorage.set('stackapella.headphones', '1');
            if (never) local.set('stackapella.headphones.never', '1');
            this.dialog = null;
            await this.prepareMic();
        },
        /** No headphones: recording goes on, the others softer (What you hear says so). */
        async headphonesNone() {
            tabStorage.set('stackapella.headphones', '1');
            chooseRoute('speakers');
            this.dialog = null;
            await this.prepareMic();
        },
        async prepareMic() {
            const failed = await (this.micOpening ?? openMic().then((mic) => (mic ? null : 'closed'), (error) => error));
            this.micOpening = null;
            if (this.leaving || failed === 'closed') {
                this.rerecordId = null;
                return; // the studio was left while the browser asked for the mic
            }
            if (failed) {
                micError(failed);
                this.rerecordId = null;
                return;
            }
            // The first recording on this device (or with new mics): the mic check, after the headphones.
            if (micNeedsCheck() && !(await showMicCheck())) {
                this.rerecordId = null;
                return;
            }
            // A guide for everyone needs a measured delay, and a sound check on this device and mic.
            if (audio.latencyFrames === null || (this.project.beta && audio.latencyEstimated)) {
                this.dialog = 'timing-record';
                return;
            }
            if (this.project.beta && !soundChecked()) {
                this.dialog = 'soundcheck';
                return;
            }
            await sessionStore.startRecording({ fromStart: !!this.recordsFromStart });
        },
        async timingDone() {
            const recordNext = this.dialog === 'timing-record';
            this.dialog = null;
            if (!recordNext) return;
            if (this.project.beta && !soundChecked()) {
                this.dialog = 'soundcheck';
                return;
            }
            await sessionStore.startRecording({ fromStart: !!this.recordsFromStart });
        },
        async soundCheckDone() {
            this.dialog = null;
            if (this.rerecordId) await sessionStore.startRecording({ fromStart: !!this.recordsFromStart });
        },
        async timingSkipped() {
            useEstimate();
            this.dialog = null;
            toast(t('studio.usingEstimate'));
            await sessionStore.startRecording({ fromStart: !!this.recordsFromStart });
        },
    },
};
