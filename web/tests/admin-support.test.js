// Admin > Support's "Sent with it" (js/admin/support.js attached, docs/feedback-plan.md): what came with a message, worded.
import { attached } from '/js/admin/support.js';
import { t } from '/js/i18n.js';
import { assert, test } from './harness.js';

const by = (rows) => Object.fromEntries(rows.map((r) => [r.key, r.value]));

test('admin support: the moment, the device and browser, the language and the look, worded', () => {
    const rows = by(attached({ moment: 'mic', context: { device: 'phone', browser: 'safari', language: 'es', skin: 'game', appVersion: '2026.11.27', timingCheck: { latencyMs: 112 }, audioErrors: [] } }));
    assert(rows.moment === t('admin.support.moment.mic'), rows.moment);
    assert(rows.device === `${t('admin.support.device.phone')}, Safari`, rows.device);
    assert(rows.language === 'Spanish', rows.language);
    assert(rows.skin === t('admin.support.skin.game') && rows.appVersion === '2026.11.27', JSON.stringify(rows));
    assert(rows.timingCheck.includes('112') && !('audioErrors' in rows), 'no sound errors: no line');
});

test('admin support: a beta note says its song, part and beat; a choir ask its issue; an older app what it sent', () => {
    const beta = by(attached({ moment: null, context: { templateId: 'amazing-grace', partKey: 'alto', beat: 33.5, betaSinger: 'Bs1' } }));
    assert(beta.song === t('admin.support.with.atBeat', { song: 'amazing-grace, alto', beat: '33.5' }) && !('moment' in beta), JSON.stringify(beta));
    const choir = by(attached({ moment: 'choir_ask', context: { choirRequestId: 'Cr8tYp', choirIssue: 'timing', errorCode: 'a1b2' } }));
    assert(choir.choirIssue === t('admin.support.choirIssue.timing') && choir.choirRequestId === 'Cr8tYp' && choir.errorCode === 'a1b2', JSON.stringify(choir));
    const old = by(attached({ moment: null, context: { device: 'iPhone', browser: 'Safari 19', extra: { a: 1 } } }));
    assert(old.device === 'iPhone, Safari 19' && old.extra === '{"a":1}', JSON.stringify(old));
    assert(attached({ moment: null, context: null }).length === 0, 'nothing sent: nothing shown');
    assert(by(attached({ moment: null, context: { browser: 'other' } })).device === 'Another browser');
});
