// A person's own settings for singing (feature 14, "remembered per person"): how notes are named,
// the keyboard down the lane, and the other parts on it. Kept on this device; with M4's sync they
// travel with the account.

import { reactive, watch } from 'vue';
import { local } from '../storage.js';

const KEY = 'stackapella.singPrefs';
// hearPolished and room: Make it sound good's (feature 1), in the studio and on export. sounds: the
// app's few sounds (js/sounds.js). singWay: How you sing (Sing's chooser, "Use this every time"):
// null asks each time, else 'parts' or 'whole'. singLast: the way chosen last ('parts', 'whole' or
// 'studio'), marked in the chooser. guideLevel: your guide in Sing (What you hear), Medium.
// chordLabels: the song builder's chord cards, named in 'letters', 'numbers' or 'numerals'.
// hearWithGuide: a lesson's Listen back plays your take with the guide singing along under it.
const DEFAULTS = { noteNames: 'letters', keyboard: true, showOthers: true, hearPolished: false, room: 'small', polish: true, shortcuts: true, sounds: true, singWay: null, singLast: null, guideLevel: 0.35, chordLabels: 'letters', hearWithGuide: null };
const OLD_SING_WAY = 'stackapella.singWay'; // where the chooser kept it before it was a setting

function read() {
    let kept = {};
    try {
        kept = JSON.parse(local.get(KEY) || '{}');
    } catch (error) {
        console.warn('Singing settings unreadable; the defaults apply', error);
    }
    const old = local.get(OLD_SING_WAY);
    if (!('singWay' in kept) && (old === 'parts' || old === 'whole')) kept.singWay = old;
    local.remove(OLD_SING_WAY);
    return { ...DEFAULTS, ...kept };
}

export const prefs = reactive(read());
watch(prefs, () => local.set(KEY, JSON.stringify(prefs)), { deep: true });
