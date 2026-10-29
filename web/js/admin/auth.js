// Where the admin sends someone the API refuses: the server's sign-in or second-factor pages,
// which bring them back to the page they were on (contract/api.md, admin.me and "in sudo").

import { ApiError } from '../api.js';
import { formatWait, t } from '../i18n.js';

/** Navigates to sign in, the second factor or a sudo check for `error`; false if it isn't one of those. */
export function sendToSignIn(error) {
    if (!(error instanceof ApiError)) return false;
    const intended = encodeURIComponent(location.pathname + location.search);
    if (error.status === 401 || error.code === 'not_admin') {
        location.assign(`/admin/sign-in?intended=${intended}`);
    } else if (error.code === 'second_factor_required') {
        location.assign(`/admin/second-factor?intended=${intended}`);
    } else if (error.code === 'sudo_required') {
        location.assign(`/admin/second-factor?sudo=1&intended=${intended}`);
    } else {
        return false;
    }
    return true;
}

/** Whether the last second-factor check still counts for sensitive changes. */
export function inSudo(me) {
    return !!me?.sudoUntil && new Date(me.sudoUntil) > new Date();
}

/**
 * What to tell an admin when a request is refused (after sendToSignIn), in their words: offline,
 * a wait the server asked for, a paused service, or a general failure. An app too old for the API
 * is told once, app-wide (main.js), so it's null here.
 */
export function explain(error) {
    if (!(error instanceof ApiError)) return t('admin.requestFailed');
    if (error.code === 'app_outdated') return null;
    if (error.code === 'offline') return t('common.offline');
    if (error.code === 'rate_limited') return error.retryAfter ? t('admin.rateLimitedFor', { wait: formatWait(error.retryAfter) }) : t('admin.rateLimited');
    if (['paused', 'read_only', 'unavailable'].includes(error.code)) return t('admin.unavailable');
    return t('admin.requestFailed');
}
