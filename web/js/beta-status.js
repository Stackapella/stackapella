// A beta part's status, as My parts and the home show it: the words and the chip's tone (components/beta.js re-exports
// it). Its own module so Home, which shows the statuses, doesn't load the beta singers' recording screens with them.
import { tk } from './i18n.js';

export const BETA_STATUS = {
    to_record: { label: tk('beta.status.to_record'), tone: 'muted' },
    submitted: { label: tk('beta.status.submitted'), tone: 'info' },
    in_review: { label: tk('beta.status.in_review'), tone: 'info' },
    retake: { label: tk('beta.status.retake'), tone: 'warn' },
    guide: { label: tk('beta.status.guide'), tone: 'good' },
    accepted: { label: tk('beta.status.accepted'), tone: 'good' },
    rejected: { label: tk('beta.status.rejected'), tone: 'muted' },
};
