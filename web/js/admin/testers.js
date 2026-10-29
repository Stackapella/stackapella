// The store testers (contract: admin.storeTesters.list, admin.people.storeTester.update): the
// accounts whose sandbox App Store and Google Play purchases give Plus, App Review's demo account and
// the TestFlight testers; everyone else's sandbox purchases give nothing. Find someone by name or
// email to add them, with an optional note; remove them in one tap. Both are in the audit log.

import { api } from '../api.js';
import { t } from '../i18n.js';
import { StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { LoadFailed, refused, when } from './common.js';
import { explain, sendToSignIn } from './auth.js';

export const StoreTestersCard = {
    components: { LoadFailed, StatusChip },
    data: () => ({ testers: null, failed: null, busy: null, q: '', note: '', found: null, searching: false }),
    setup: () => ({ when }),
    template: `
        <section class="card space-y-4 p-6" aria-labelledby="testers-heading">
            <div>
                <h2 id="testers-heading" class="text-lg font-bold text-cream">{{ t('admin.testers.title') }}</h2>
                <p class="mt-1 text-sm text-muted">{{ t('admin.testers.hint') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="testers === null" class="text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <p v-if="!testers.length" class="text-sm text-muted">{{ t('admin.testers.none') }}</p>
                <ul v-else class="divide-y divide-line rounded-2xl border border-line">
                    <li v-for="person in testers" :key="person.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-cream" translate="no">{{ person.name }}</span><span class="block truncate text-sm text-muted" translate="no">{{ person.email }}</span></span>
                        <span class="text-xs text-muted">{{ t('admin.testers.since', { when: when(person.since) }) }}</span>
                        <button type="button" class="btn-ghost btn-sm text-bad" :disabled="busy === person.id" @click="set(person, false)">{{ t('admin.testers.remove') }}</button>
                    </li>
                </ul>
            </template>
            <form class="space-y-3 rounded-2xl bg-raised p-4" @submit.prevent="search">
                <h3 class="font-bold text-cream">{{ t('admin.testers.add') }}</h3>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block min-w-0 flex-1 text-sm text-muted">{{ t('admin.testers.find') }}
                        <input v-model.trim="q" type="search" class="input mt-1 w-full" autocomplete="off" :placeholder="t('admin.testers.findHint')" />
                    </label>
                    <button type="submit" class="btn-secondary btn-sm" :disabled="searching || q.length < 2">{{ t('admin.testers.search') }}</button>
                </div>
                <label class="block text-sm text-muted">{{ t('admin.testers.note') }}
                    <input v-model.trim="note" type="text" maxlength="200" class="input mt-1 w-full" :placeholder="t('admin.testers.noteHint')" />
                </label>
                <p v-if="found && !found.length" class="text-sm text-muted" role="status">{{ t('admin.testers.noMatch') }}</p>
                <ul v-else-if="found" class="space-y-2">
                    <li v-for="person in found" :key="person.id" class="flex flex-wrap items-center gap-3 rounded-xl bg-panel px-3 py-2">
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-cream" translate="no">{{ person.name }}</span><span class="block truncate text-sm text-muted" translate="no">{{ person.email }}</span></span>
                        <StatusChip v-if="person.storeTesterSince" tone="good" :label="t('admin.testers.already')" />
                        <button v-else type="button" class="btn-secondary btn-sm" :disabled="busy === person.id" @click="set(person, true)">{{ t('admin.testers.make', { name: person.name }) }}</button>
                    </li>
                </ul>
            </form>
        </section>`,
    created() {
        this.load();
    },
    methods: {
        async load() {
            this.failed = null;
            try {
                this.testers = (await api.admin.storeTesters.list()).data;
            } catch (error) {
                if (!sendToSignIn(error)) this.failed = explain(error) ?? t('admin.requestFailed');
            }
        },
        async search() {
            this.searching = true;
            try {
                this.found = (await api.admin.people.list({ q: this.q })).data.slice(0, 8);
            } catch (error) {
                refused(error);
            } finally {
                this.searching = false;
            }
        },
        async set(person, tester) {
            this.busy = person.id;
            try {
                const updated = (await api.admin.people.storeTester.update(person.id, { tester, note: tester && this.note ? this.note : undefined })).data;
                toast(tester ? t('admin.testers.added', { name: person.name }) : t('admin.testers.removed', { name: person.name }));
                if (this.found) this.found = this.found.map((p) => (p.id === person.id ? { ...p, storeTesterSince: updated.storeTesterSince } : p));
                if (tester) this.note = '';
                await this.load();
            } catch (error) {
                refused(error);
            } finally {
                this.busy = null;
            }
        },
    },
};
