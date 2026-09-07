/*
|------------------------------------------------------------------
| Native (Capacitor) pre-boot
|------------------------------------------------------------------
| Owns everything that must happen BEFORE the mobile SPA entry module
| evaluates, then hands over with a dynamic import().
|
| The ordering is not stylistic. application.js has a top-level await, and
| resources/js/spa/lang/index.js reads BackendEmbeds.locale inside an exported
| object literal -- so the import graph itself needs window.BackendEmbeds to
| already exist, and the axios module needs the token. A static import here
| would evaluate that graph too early.
|
| On the web this file is never loaded; Blade inlines BackendEmbeds instead.
*/

import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { SecureStorage } from '@aparajita/capacitor-secure-storage';

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API_PREFIX = import.meta.env.VITE_APP_API_PREFIX;

const TOKEN_KEY = 'auth_token';
const EMBEDS_CACHE_KEY = 'rebit.embeds';

window.__NATIVE__ = true;
window.__NATIVE_PLATFORM__ = Capacitor.getPlatform();

/*
 * kernel/helpers/helpers.js decides this from navigator.standalone and the
 * display-mode media query, neither of which matches inside a Capacitor
 * WebView. Six mobile components rely on it for home-indicator padding, so
 * without the shim the bottom navbar sits under the gesture bar.
 */
window.isStandalone = () => true;

// --- token ----------------------------------------------------------------

/*
 * Keychain / Android Keystore, not @capacitor/preferences -- that is plain
 * SharedPreferences and the wrong place for a 60-day bearer token.
 *
 * There is deliberately no plaintext fallback. Some devices throw here after a
 * biometric re-enrolment; treating that as "no token" costs one extra sign-in,
 * which is strictly better than writing a credential in the clear.
 */
async function readToken() {
    try {
        return await SecureStorage.get(TOKEN_KEY);
    } catch (error) {
        console.warn('Secure storage unavailable; treating as signed out.', error);

        return null;
    }
}

// --- embeds ---------------------------------------------------------------

/*
 * Blade normally emits this blob inline. A bundled shell has no Blade, so it
 * comes from the API instead -- which also means admin config changes reach
 * installed apps without a store release.
 */
async function loadEmbeds(token) {
    try {
        const response = await fetch(`${API_BASE}/${API_PREFIX}/system/embeds`, {
            headers: {
                Accept: 'application/json',
                // Optional: the payload carries per-user theme and locale.
                ...(token ? { Authorization: `Bearer ${token}` } : {})
            }
        });

        if (! response.ok) {
            throw new Error(`embeds request failed: ${response.status}`);
        }

        const embeds = (await response.json()).data;

        localStorage.setItem(EMBEDS_CACHE_KEY, JSON.stringify(embeds));

        return embeds;
    } catch (error) {
        const cached = localStorage.getItem(EMBEDS_CACHE_KEY);

        if (cached) {
            console.warn('Using cached embeds.', error);

            return JSON.parse(cached);
        }

        return null;
    }
}

/*
 * Theme is decided server-side on the web, by picking one stylesheet at render
 * time. A bundled shell is rendered once, so the choice has to move to runtime
 * or dark mode silently does nothing after ThemeSettings reloads the page.
 */
function applyTheme(theme) {
    if (theme !== 'dark') {
        return;
    }

    const link = document.createElement('link');

    link.rel = 'stylesheet';
    link.href = '/build/assets/mobile-main-dark.css';

    document.head.appendChild(link);

    // The Blade shell serves light OR dark, never both. Leaving the light sheet
    // active would leak every rule the dark sheet does not happen to override.
    const light = document.getElementById('theme-light');

    if (light) {
        light.disabled = true;
    }
}

// --- sign in --------------------------------------------------------------

const base64url = (bytes) => btoa(String.fromCharCode(...new Uint8Array(bytes)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');

const randomToken = () => base64url(crypto.getRandomValues(new Uint8Array(32)));

async function challengeFor(verifier) {
    // Requires a secure context. androidScheme is pinned to 'https' in
    // capacitor.config.ts precisely so crypto.subtle exists here.
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier));

    return base64url(digest);
}

/*
 * The app never renders a login form. It opens the existing web auth pages in
 * the system browser -- which also means Google sign-in works unchanged, and
 * Chrome Custom Tabs share the phone browser's cookie jar for one-tap sign-in.
 *
 * PKCE: what crosses the custom URL scheme is a single-use code bound to the
 * SHA-256 of a verifier that never leaves the device. The token itself only
 * travels over HTTPS in the exchange response.
 */
async function signIn() {
    const verifier = randomToken();
    const state = randomToken();
    const challenge = await challengeFor(verifier);

    const listener = await App.addListener('appUrlOpen', async ({ url }) => {
        const params = new URL(url).searchParams;

        // Not our redirect, or a forged one.
        if (params.get('state') !== state) {
            return;
        }

        await listener.remove();
        await Browser.close().catch(() => {});

        try {
            const response = await fetch(`${API_BASE}/${API_PREFIX}/auth/native/exchange`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    code: params.get('code'),
                    verifier,
                    device_name: window.__NATIVE_PLATFORM__ || 'device'
                })
            });

            if (! response.ok) {
                throw new Error(`exchange failed: ${response.status}`);
            }

            await SecureStorage.set(TOKEN_KEY, (await response.json()).data.token);

            // replace(), never reload(): Capacitor's index.html fallback only
            // fires for paths whose last segment has no dot, and usernames may
            // contain dots.
            window.location.replace('/');
        } catch (error) {
            console.error(error);

            showGate('Sign-in did not complete. Please try again.');
        }
    });

    await Browser.open({
        url: `${API_BASE}/auth/native/start?challenge=${challenge}&state=${state}`
    });
}

window.nativeSignOut = async () => {
    // Drop the push token first: otherwise the next person to use this device
    // keeps receiving the previous account's notifications.
    await import('@M/core/services/native/push.js')
        .then((push) => push.unregisterPush())
        .catch(() => {});

    await SecureStorage.remove(TOKEN_KEY).catch(() => {});

    window.location.replace('/');
};

// --- gate -----------------------------------------------------------------

function showGate(message, { retry = false } = {}) {
    const gate = document.getElementById('native-gate');

    if (! gate) {
        return;
    }

    gate.hidden = false;
    gate.querySelector('[data-gate-message]').textContent = message;

    const action = gate.querySelector('[data-gate-action]');

    action.hidden = false;
    action.textContent = retry ? 'Try again' : 'Sign in';
    action.onclick = retry ? () => window.location.replace('/') : signIn;
}

// --- boot -----------------------------------------------------------------

window.__NATIVE_TOKEN = await readToken();

const embeds = await loadEmbeds(window.__NATIVE_TOKEN);

if (! embeds) {
    showGate('We could not reach REBIT. Check your connection and try again.', { retry: true });
} else {
    window.BackendEmbeds = embeds;

    // ponytail: monetization is baked off in the binary, not just hidden by the
    // server-sent flag, so a flag flip after review cannot surface a purchase
    // path. Remove when in-app purchases are actually implemented.
    if (window.BackendEmbeds.config?.features?.wallet) {
        window.BackendEmbeds.config.features.wallet.enabled = false;
    }

    applyTheme(window.BackendEmbeds.theme);

    if (! window.__NATIVE_TOKEN) {
        showGate('Welcome to REBIT.');
    } else {
        await import('./application.js');
    }
}
