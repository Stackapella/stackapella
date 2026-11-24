// An AI link opened while AI features are off (docs/ai-optional-plan.md, the hide list): a choir page, a choir invite.
// One plain line, "This uses AI voices, and your AI features are off.", and one button that turns them on (the account's
// choice, or this device's for a guest), then the page carries on where it was. Never a browser dialog.

import { api } from '../api.js';
import { t } from '../i18n.js';
import { toast } from './ui.js';
import { account } from '../stores/account.js';
import { AI_KEY } from '../ai-choice.js';
import { local } from '../storage.js';

export const AiOffNotice = {
    emits: ['on'],
    data: () => ({ busy: false }),
    template: `
        <div class="card space-y-3 p-5" data-ai-off>
            <p class="text-cream">{{ t('settings.ai.linkOff') }}</p>
            <button type="button" class="btn-secondary self-start" :disabled="busy" data-ai-off-on @click="turnOn">{{ t('settings.ai.turnOn') }}</button>
        </div>`,
    methods: {
        async turnOn() {
            this.busy = true;
            try {
                if (account.me) {
                    await api.account.update({ aiFeatures: true });
                    account.me = { ...account.me, aiFeatures: true };
                } else {
                    local.set(AI_KEY, 'on');
                }
                this.$emit('on');
            } catch (error) {
                console.warn('AI features were not turned on', error);
                toast(t('settings.failed'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
    },
};
