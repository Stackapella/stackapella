// Practice reminders (docs/operations-plan.md; contract/api.md reminders.*): opt-in web push, on the
// days and at the time the learner picks, at most one a day, off with one tap. This device's
// subscription goes to the account with a token the service worker keeps (it changes or removes the
// device by that token alone). The push carries no words: the worker shows the notification in the
// learner's language from the words kept here (meta 'reminderWords').

import { api } from './api.js';
import * as db from './db.js';
import { t } from './i18n.js';

const DEVICE = 'reminderDevice'; // meta: { id, token, endpoint }

/** Whether this browser can do it at all (iOS only in the installed app). */
export function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && typeof Notification !== 'undefined';
}

/** On an iPhone or iPad outside the installed app: push needs the app on the home screen. */
export function needsInstall() {
    return /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.matchMedia?.('(display-mode: standalone)').matches;
}

/** The base64url VAPID key as the bytes pushManager.subscribe wants. */
function keyBytes(base64url) {
    const padded = base64url.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(base64url.length / 4) * 4, '=');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

/** This device's subscription, or null; never waits for a service worker that isn't there. */
async function subscription() {
    const registration = await navigator.serviceWorker.getRegistration();
    return registration?.active ? registration.pushManager.getSubscription() : null;
}

/** The reminder as the account has it, and whether this device gets it: `{ enabled, days, time, vapidPublicKey, thisDevice }`. */
export async function loadReminder() {
    const current = pushSupported() ? await subscription().catch(() => null) : null;
    const { data } = await api.reminders.show({ endpoint: current?.endpoint });
    return data;
}

/** Keeps the notification's words in the learner's language, for the service worker. */
export async function keepWords() {
    await db.put('meta', { title: t('reminders.push.title'), body: t('reminders.push.body') }, 'reminderWords');
}

/**
 * Turns reminders on for this device (from a tap: the browser asks for permission) with the days
 * and time. Throws `{ code: 'denied' }` when notifications are blocked.
 */
export async function turnOn({ days, time }, vapidPublicKey) {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') throw Object.assign(new Error('Notifications are blocked'), { code: 'denied' });
    const registration = await navigator.serviceWorker.getRegistration();
    if (!registration?.active) throw Object.assign(new Error('No service worker'), { code: 'no_worker' });
    const sub = (await registration.pushManager.getSubscription()) ?? (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(vapidPublicKey) }));
    const { data } = await api.reminders.devices.create(sub.toJSON());
    await db.put('meta', { id: data.id, token: data.token, endpoint: sub.endpoint }, DEVICE);
    await keepWords();
    return (await api.reminders.update({ enabled: true, days, time, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone })).data;
}

/** New days or time (reminders already on). */
export async function changeSchedule({ enabled, days, time }) {
    return (await api.reminders.update({ enabled, days, time, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone })).data;
}

/** Stops reminders on this device (Settings, and before signing out). */
export async function turnOffHere() {
    const device = await db.get('meta', DEVICE);
    if (device) await api.reminders.devices.delete(device.id, device.token).catch((error) => console.warn('The device was not removed', error));
    await db.remove('meta', DEVICE);
    if (!device) return; // nothing kept here: nothing to unsubscribe
    const sub = pushSupported() ? await subscription().catch(() => null) : null;
    await sub?.unsubscribe().catch((error) => console.warn('The old push subscription stayed', error));
}
