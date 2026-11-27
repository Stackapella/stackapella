// The way to Contact us beside a message that says to write to us, or an error message (docs/feedback-plan.md, section
// 2; behind the feedback2 switch, nothing shows while it's off): a link to the form with where it came from (`moment`,
// `code`: the error's own code), a topic to start on and back to this page. For a toast, contactActions gives the same
// as its action. The form checks everything again (js/feedback.js).

import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { contactPath } from '../feedback.js';

const here = () => location.pathname + location.search;
const on = () => !!account.config?.feedback2;

/** The form's address from here. */
export function contactHere({ moment = 'error', code = null, topic = 'broken', from = here(), ...rest } = {}) {
    return contactPath({ from, moment, topic, errorCode: code, ...rest });
}

/** A toast's actions: Tell us what happened (or the words given), while feedback2 is on; none otherwise. */
export function contactActions(options = {}, label = tk('contact.tellUs')) {
    return on() ? [{ label: t(label), run: () => navigate(contactHere(options)) }] : [];
}

export const ContactLink = {
    props: {
        moment: { type: String, default: 'error' },
        code: { type: String, default: null },
        topic: { type: String, default: 'broken' },
        label: { type: String, default: tk('contact.tellUs') },
        templateId: { type: String, default: null },
    },
    setup: () => ({ on }),
    computed: {
        href() {
            return contactHere({ moment: this.moment, code: this.code, topic: this.topic, templateId: this.templateId });
        },
    },
    template: `<a v-if="on()" :href="href" class="link inline-flex min-h-11 items-center self-start" data-contact-link @click.prevent="navigate(href)">{{ t(label) }}</a>`,
    methods: { navigate },
};
