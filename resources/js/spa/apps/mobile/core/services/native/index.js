/*
|------------------------------------------------------------------
| Native platform behaviour
|------------------------------------------------------------------
| Everything that makes the WebView behave like an app rather than a page.
| Loaded only when window.__NATIVE__ is set, so none of it reaches the web
| bundle's execution path.
*/

import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Keyboard } from '@capacitor/keyboard';
import { Network } from '@capacitor/network';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';

import { colibriEventBus } from '@/kernel/events/bus/index.js';

import { attachRouter } from '@M/core/services/native/deeplinks.js';
import { initPush } from '@M/core/services/native/push.js';
import { initAppLock } from '@M/core/services/native/lock.js';

/**
 * The WebView cannot style the native status bar, and the theme is chosen at
 * runtime from BackendEmbeds rather than server-side as on the web.
 */
async function applyStatusBar() {
    const dark = window.BackendEmbeds?.theme === 'dark';

    try {
        await StatusBar.setStyle({ style: dark ? Style.Dark : Style.Light });
        await StatusBar.setBackgroundColor({ color: dark ? '#000000' : '#ffffff' });
    } catch {
        // Not fatal, and unsupported on some devices.
    }
}

/**
 * Android's hardware back button has no web equivalent, and its default
 * behaviour -- close the app -- is wrong everywhere except the root route.
 *
 * Modal state is read from window.ACTIVE_MODALS, the counter freezeScroll() and
 * unfreezeScroll() already maintain in kernel/helpers/embeds. Reusing it avoids
 * a second, competing registry of "is something open".
 */
function handleBackButton() {
    let lastBackAt = 0;

    App.addListener('backButton', ({ canGoBack }) => {
        if (window.ACTIVE_MODALS > 0) {
            colibriEventBus.emit('native:back');

            /*
             * ponytail: leans on ACTIVE_MODALS, so a component that forgets to
             * call unfreezeScroll() would trap the back button. The double-tap
             * below is the escape hatch; replace with a real modal stack if
             * that ever stops being enough.
             */
            const now = Date.now();

            if (now - lastBackAt < 2000) {
                App.exitApp();
            }

            lastBackAt = now;

            return;
        }

        if (canGoBack) {
            window.history.back();

            return;
        }

        App.exitApp();
    });
}

/**
 * resize: 'native' in capacitor.config.json is what actually keeps the fixed
 * bottom navbar and the composers above the keyboard -- the WebView itself
 * resizes. These listeners exist so the layout can be *tuned* on-device if that
 * turns out not to be enough: --keyboard-height and .keyboard-open are hooks,
 * deliberately not consumed yet. Applying an offset here as well would
 * double-shift everything.
 *
 * ponytail: verify on a physical device before adding any CSS that reads them.
 */
function handleKeyboard() {
    Keyboard.addListener('keyboardWillShow', (info) => {
        document.documentElement.style.setProperty('--keyboard-height', `${info.keyboardHeight}px`);
        document.body.classList.add('keyboard-open');
    });

    Keyboard.addListener('keyboardWillHide', () => {
        document.documentElement.style.setProperty('--keyboard-height', '0px');
        document.body.classList.remove('keyboard-open');
    });
}

/**
 * Off-origin links -- the /document/* legal pages, the help centre, social share
 * targets -- are plain <a href> or target="_blank". In a bundled WebView those
 * either do nothing or navigate the app out of its own bundle with no way back.
 * Hand them to the system browser instead.
 */
function handleExternalLinks() {
    document.addEventListener('click', (event) => {
        const anchor = event.target.closest?.('a[href]');

        if (! anchor) {
            return;
        }

        const href = anchor.getAttribute('href');

        if (! href || href.startsWith('#')) {
            return;
        }

        const url = new URL(href, window.location.href);

        // Same-origin links belong to the Vue router.
        if (url.origin === window.location.origin && anchor.target !== '_blank') {
            return;
        }

        event.preventDefault();

        Browser.open({ url: url.href });
    });
}

async function handleNetwork() {
    // Seed from the current state, not just future changes: the app can be
    // launched already offline.
    const status = await Network.getStatus().catch(() => null);

    if (status) {
        colibriEventBus.emit('native:network', status);
    }

    Network.addListener('networkStatusChange', (nextStatus) => {
        colibriEventBus.emit('native:network', nextStatus);
    });
}

/**
 * Websockets do not survive a long backgrounding, so anything that arrived
 * while the app was away is invisible until something asks. Re-checking on
 * resume is the cheapest way to stop the badge going stale.
 */
function handleLifecycle() {
    App.addListener('appStateChange', ({ isActive }) => {
        if (isActive) {
            colibriEventBus.emit('native:resumed');
        }
    });
}

/**
 * Called once the SPA has actually rendered. Until then the native splash covers
 * application.js's top-level await and the /api/bootstrap round trip, which is
 * otherwise 1-3 seconds of white screen.
 */
export async function initNativePlatform(router) {
    await applyStatusBar();

    // Deep links and push share one routing function, so this must come before
    // anything can deliver a URL.
    await attachRouter(router);
    await initPush().catch((error) => console.warn('Push unavailable.', error));

    // Opt-in and off by default; the listener is cheap either way.
    initAppLock();

    handleBackButton();
    handleKeyboard();
    handleExternalLinks();
    handleLifecycle();

    await handleNetwork();

    try {
        await SplashScreen.hide();
    } catch {
        // Already hidden, or launchAutoHide left it to the system.
    }
}
