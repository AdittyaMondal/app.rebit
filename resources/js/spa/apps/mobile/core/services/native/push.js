/*
|------------------------------------------------------------------
| Native push notifications
|------------------------------------------------------------------
| Web Push is unavailable here: it needs a service worker on an http(s) origin,
| and the bundled shell deliberately registers none -- its cache-first fetch
| handler would hijack the app's own assets.
|
| Uses @capacitor/push-notifications rather than @capacitor-firebase/messaging.
| The latter would give FCM tokens on iOS too (one server protocol for both
| platforms), but it carries an optional peer dependency on the Firebase *web*
| SDK, which Vite must resolve even though the web implementation is never
| executed here. That is a large dependency to ship for a code path that cannot
| run. On Android the official plugin returns an FCM token anyway, which is
| exactly what FcmClient expects.
|
| ponytail: when iOS is picked up (see the deferred section of the plan), this
| returns an APNs token there, so iOS needs either the Firebase plugin or an
| APNs path added to FcmClient. Android is unaffected either way.
*/

import { PushNotifications } from '@capacitor/push-notifications';
import { Capacitor } from '@capacitor/core';

import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

import { routeUrl } from '@M/core/services/native/deeplinks.js';

let registeredToken = null;

async function sendToken(token) {
    registeredToken = token;

    try {
        await colibriAPI().userSettings().with({
            token,
            platform: Capacitor.getPlatform()
        }).sendTo('notifications/push/token');
    } catch (error) {
        // Not fatal: the next launch retries. Never block boot on this.
        console.warn('Could not register the push token.', error);
    }
}

/**
 * Attaches listeners only. Permission is requested later, in context --
 * see requestPushPermission().
 */
export async function initPush() {
    PushNotifications.addListener('registration', ({ value }) => sendToken(value));

    PushNotifications.addListener('registrationError', (error) => {
        console.warn('Push registration failed.', error);
    });

    // Tapping a notification routes through exactly the same function deep
    // links use: one code path, three entry points.
    PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
        routeUrl(notification?.data?.link);
    });

    const { receive } = await PushNotifications.checkPermissions();

    // Already granted on a previous launch: re-register silently so a rotated
    // token reaches the server.
    if (receive === 'granted') {
        await PushNotifications.register();
    }
}

/**
 * Android 13+ needs a runtime grant. Deliberately NOT requested at launch: a
 * cold prompt before the user has seen anything is the fastest route to a
 * permanent denial. Call this from a moment that explains itself -- the push
 * settings screen, or after a first post or follow.
 */
export async function requestPushPermission() {
    const { receive } = await PushNotifications.requestPermissions();

    if (receive !== 'granted') {
        return false;
    }

    await PushNotifications.register();

    return true;
}

/**
 * On sign-out. Without this the next person to use the device keeps receiving
 * the previous account's notifications.
 */
export async function unregisterPush() {
    if (registeredToken) {
        try {
            await colibriAPI().userSettings()
                .with({ token: registeredToken })
                .delete('notifications/push/token');
        } catch {
            // Best effort: the server also drops tokens FCM reports as dead.
        }
    }

    registeredToken = null;

    await PushNotifications.unregister().catch(() => {});
}
