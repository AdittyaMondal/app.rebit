/*
|------------------------------------------------------------------
| Biometric app lock
|------------------------------------------------------------------
| Opt-in, and off by default. A social app that demands a fingerprint on every
| resume without being asked is an app people uninstall, so this only engages
| when the user turns it on in Settings.
|
| There is no web equivalent: WebAuthn platform authenticators are unusable on a
| custom-scheme origin, so this genuinely needs the native plugin.
*/

import { App } from '@capacitor/app';
import { BiometricAuth } from '@aparajita/capacitor-biometric-auth';

const PREFERENCE_KEY = 'rebit.app_lock';
const LOCKED_CLASS = 'app-locked';

// Below this, resuming is treated as continuing the same session -- otherwise
// every glance at a notification would demand a fingerprint.
const GRACE_MS = 60_000;

let backgroundedAt = null;
let unlocking = false;

export function isLockEnabled() {
    return localStorage.getItem(PREFERENCE_KEY) === 'on';
}

export function setLockEnabled(enabled) {
    localStorage.setItem(PREFERENCE_KEY, enabled ? 'on' : 'off');
}

/**
 * Whether the device can actually do this. A toggle that turns on and then
 * silently never locks is worse than no toggle.
 */
export async function isLockAvailable() {
    try {
        const info = await BiometricAuth.checkBiometry();

        return info.isAvailable === true;
    } catch {
        return false;
    }
}

function cover() {
    document.body.classList.add(LOCKED_CLASS);
}

function uncover() {
    document.body.classList.remove(LOCKED_CLASS);
}

async function unlock() {
    if (unlocking) {
        return;
    }

    unlocking = true;

    // Cover first, authenticate second: the prompt is not instantaneous, and
    // the feed must not be readable in the gap.
    cover();

    try {
        await BiometricAuth.authenticate({
            reason: 'Unlock REBIT',
            cancelTitle: 'Cancel',
            allowDeviceCredential: true,
            androidTitle: 'Unlock REBIT',
            androidSubtitle: 'Confirm it is you',
        });

        uncover();
    } catch {
        // Cancelled or failed. Stay covered and leave the app backgrounded
        // rather than exposing content; the next resume tries again.
        backgroundedAt = 0;

        await App.minimizeApp().catch(() => {});
    } finally {
        unlocking = false;
    }
}

export function initAppLock() {
    App.addListener('appStateChange', async ({ isActive }) => {
        if (! isActive) {
            backgroundedAt = Date.now();

            // Cover immediately: the OS screenshots the app for the task
            // switcher at exactly this moment.
            if (isLockEnabled()) {
                cover();
            }

            return;
        }

        if (! isLockEnabled()) {
            uncover();

            return;
        }

        if (backgroundedAt !== null && Date.now() - backgroundedAt < GRACE_MS) {
            uncover();

            return;
        }

        await unlock();
    });
}

/** Used by the settings toggle to confirm the user can actually authenticate. */
export async function verifyOnce() {
    try {
        await BiometricAuth.authenticate({
            reason: 'Confirm it is you',
            allowDeviceCredential: true,
            androidTitle: 'Enable app lock',
        });

        return true;
    } catch {
        return false;
    }
}
