// Cloudflare Turnstile, only in shield mode (docs/trust-and-safety-plan.md, "Shield mode"): when
// auth.config gives a site key, the sign-in screens load Cloudflare's script and ask for a token.
// Never loaded otherwise. Cloudflare requires its script from its own address, unpinned, so it has
// no integrity hash (docs/decisions.md).

const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
let loading = null;

function load() {
    loading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT;
        script.async = true;
        script.onload = () => resolve(window.turnstile);
        script.onerror = () => {
            loading = null;
            reject(new Error('Turnstile did not load'));
        };
        document.head.append(script);
    });
    return loading;
}

/** The check in a plain page (the shared harmony's Report): `onToken(token or null)`. Returns `reset` (a token works once). */
export async function renderTurnstile(element, siteKey, onToken) {
    const turnstile = await load();
    const widget = turnstile.render(element, { sitekey: siteKey, theme: 'dark', callback: onToken, 'expired-callback': () => onToken(null), 'error-callback': () => onToken(null) });
    return () => {
        onToken(null);
        turnstile.reset(widget);
    };
}

/** The check in a form: emits each token Cloudflare gives (and null when one expires). */
export const TurnstileBox = {
    props: { siteKey: { type: String, required: true } },
    emits: ['token', 'failed'],
    template: `<div ref="box" class="min-h-16"></div>`,
    async mounted() {
        try {
            const turnstile = await load();
            if (!this.$refs.box) return;
            this.widget = turnstile.render(this.$refs.box, {
                sitekey: this.siteKey,
                theme: 'dark',
                callback: (token) => this.$emit('token', token),
                'expired-callback': () => this.$emit('token', null),
                'error-callback': () => this.$emit('failed'),
            });
        } catch (error) {
            console.error('Turnstile failed', error);
            this.$emit('failed');
        }
    },
    unmounted() {
        if (this.widget !== undefined) window.turnstile?.remove(this.widget);
    },
};
