// The wall a guest meets where an account is the feature itself (docs/account-nudges-plan.md, the
// walls W1 to W12, pattern C): one way everywhere. Why this needs an account, in plain words, that
// their songs and progress come with them, then Make a free account (sign-up, titled with the same
// reason and counted by it) and, quietly, I have an account. Never a dead end.

import { tk } from '../i18n.js';
import { navigate } from '../router.js';

/** Why each place needs an account. */
const WHY = {
    boards: tk('wall.boards'),
    daily: tk('wall.daily'),
    reminders: tk('wall.reminders'),
    friends: tk('wall.friends'),
    groups: tk('wall.groups'),
    voice: tk('wall.voice'),
    plus: tk('wall.plus'),
    template: tk('wall.template'),
    songs: tk('wall.songs'),
    school: tk('wall.school'),
};

/** Sign-up for `reason`, back to `next` once done; or sign-in, back to `next`. */
export function signUpFor(reason, next = location.pathname + location.search) {
    return `/signin?mode=signup&for=${encodeURIComponent(reason)}&next=${encodeURIComponent(next)}`;
}
export function signInTo(next = location.pathname + location.search) {
    return `/signin?next=${encodeURIComponent(next)}`;
}

export const AccountWall = {
    // `bare`: no card of its own, for a wall inside a card or a sheet.
    props: { reason: { type: String, required: true }, next: { type: String, default: null }, bare: Boolean },
    setup: () => ({ WHY }),
    template: `
        <div class="space-y-3" :class="bare ? '' : 'card p-5'" data-account-wall :data-reason="reason">
            <p class="text-cream">{{ t(WHY[reason]) }} <span class="text-muted">{{ t('wall.comes') }}</span></p>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                <a :href="signUp" class="btn-primary" data-wall-sign-up @click.prevent="navigate(signUp)">{{ t('wrap.makeAccount') }}</a>
                <a :href="signIn" class="link tap-44 text-sm" data-wall-sign-in @click.prevent="navigate(signIn)">{{ t('wall.haveAccount') }}</a>
            </div>
        </div>`,
    computed: {
        signUp() {
            return signUpFor(this.reason, this.next ?? undefined);
        },
        signIn() {
            return signInTo(this.next ?? undefined);
        },
    },
    methods: { navigate },
};
