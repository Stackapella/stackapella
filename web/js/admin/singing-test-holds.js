// Safety > Singing tests (docs/singing-test-v1.md, "The boards"): the tests held off the global board for a look, each
// with why (a jump far past the singer's own history, notes too flat to be a voice, the same items as another test).
// Looks fine clears the hold; Hide (in sudo, with a reason for the audit log) keeps it off the boards for good. A held
// test leaves the board after 48 hours anyway, and the singer and their friends still see it meanwhile.

import { api } from '../api.js';
import { formatDate, t, tk } from '../i18n.js';
import { EmptyState, Field } from '../components/form.js';
import { toast } from '../components/ui.js';
import { LoadFailed } from './common.js';

const REASONS = { jump: tk('admin.singingTests.reason.jump'), flat: tk('admin.singingTests.reason.flat'), duplicate: tk('admin.singingTests.reason.duplicate') };

export const SingingTestHolds = {
    components: { EmptyState, Field, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ items: null, failed: false, busy: null, hiding: null, reason: '' }),
    setup: () => ({ REASONS, formatDate }),
    computed: {
        sudo() {
            return !!this.me.sudoUntil && new Date(this.me.sudoUntil) > new Date();
        },
    },
    created() {
        this.load();
    },
    methods: {
        async load() {
            this.failed = false;
            try {
                this.items = (await api.admin.singingTests.flags.list()).data;
            } catch (error) {
                console.warn('The held singing tests were not loaded', error);
                this.failed = true;
            }
        },
        async review(item) {
            this.busy = item.id;
            try {
                await api.admin.singingTests.review({ params: { id: item.id } });
                this.items = this.items.filter((x) => x.id !== item.id);
                toast(t('admin.singingTests.cleared'));
            } catch (error) {
                console.warn('The hold was not cleared', error);
                toast(t('common.tryAgain'), { tone: 'error' });
            } finally {
                this.busy = null;
            }
        },
        async hide(item) {
            if (!this.reason.trim()) return;
            this.busy = item.id;
            try {
                await api.admin.singingTests.hide({ params: { id: item.id }, body: { reason: this.reason.trim() } });
                this.items = this.items.filter((x) => x.id !== item.id);
                this.hiding = null;
                this.reason = '';
                toast(t('admin.singingTests.hidden'));
            } catch (error) {
                console.warn('The test was not hidden', error);
                toast(t('common.tryAgain'), { tone: 'error' });
            } finally {
                this.busy = null;
            }
        },
    },
    template: `
        <div class="space-y-4 pt-4" data-singing-test-holds>
            <p class="t-hint">{{ t('admin.singingTests.lead') }}</p>
            <LoadFailed v-if="failed" :message="t('admin.singingTests.failed')" @retry="load" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="check" :title="t('admin.singingTests.none')" />
            <ul v-else class="card divide-y divide-line">
                <li v-for="item in items" :key="item.id" class="space-y-2 px-5 py-4" :data-held-test="item.id">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <span class="font-semibold text-cream">{{ item.person?.name ?? t('admin.singingTests.someone') }} · {{ item.total }}</span>
                        <span class="t-hint">{{ t('admin.singingTests.until', { date: formatDate(item.holdEndsAt, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) }) }}</span>
                    </div>
                    <ul class="flex flex-wrap gap-2"><li v-for="r in item.reasons" :key="r" class="chip-tag">{{ t(REASONS[r] ?? REASONS.jump) }}</li></ul>
                    <div v-if="hiding === item.id" class="space-y-2">
                        <Field :label="t('admin.singingTests.reasonLabel')" v-slot="f"><input :id="f.id" v-model="reason" class="input" maxlength="200" /></Field>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-primary btn-sm" :disabled="busy === item.id || !reason.trim()" @click="hide(item)">{{ t('admin.singingTests.hideConfirm') }}</button>
                            <button type="button" class="btn-secondary btn-sm" @click="hiding = null">{{ t('common.cancel') }}</button>
                        </div>
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-secondary btn-sm" :disabled="busy === item.id" data-held-review @click="review(item)">{{ t('admin.singingTests.looksFine') }}</button>
                        <button type="button" class="btn-secondary btn-sm" :disabled="!sudo" @click="hiding = item.id; reason = ''">{{ t('admin.singingTests.hide') }}</button>
                        <span v-if="!sudo" class="t-hint">{{ t('admin.home.sudoNeeded') }}</span>
                    </div>
                </li>
            </ul>
        </div>`,
};
