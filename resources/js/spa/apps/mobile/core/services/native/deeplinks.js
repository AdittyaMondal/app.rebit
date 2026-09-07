/*
|------------------------------------------------------------------
| Deep link routing
|------------------------------------------------------------------
| One entry point for all three ways a URL can arrive: a cold start
| (App.getLaunchUrl), a warm start (appUrlOpen), and a push notification tap.
*/

import { App } from '@capacitor/app';

const API_BASE = import.meta.env.VITE_API_BASE_URL;

let router = null;
let pending = null;

/**
 * Deliberately no URL-to-route mapping table: router.resolve() already is one.
 * Every pattern the app claims -- /@handle, /publication/*, /stories/*,
 * /messenger/c/*, /explore -- is already declared in the mobile router, so new
 * routes extend deep linking for free.
 */
export function routeUrl(url) {
    if (! url) {
        return;
    }

    if (! router) {
        // Arrived before the app mounted. The whole tree is gated on
        // appLoading, so pushing now would land in an unmounted app.
        pending = url;

        return;
    }

    let parsed;

    try {
        parsed = new URL(url, API_BASE);
    } catch {
        return;
    }

    // The rebit:// auth callback also arrives as appUrlOpen, but native.js owns
    // it. Routing on its (empty) pathname would push the user somewhere random
    // mid-sign-in.
    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return;
    }

    const path = parsed.pathname;

    const resolved = router.resolve(path);

    // An unrecognised link should leave the user where they are rather than
    // dropping them on a 404 they did not ask for.
    if (resolved.name === 'error_404') {
        return;
    }

    router.push(resolved);
}

export async function attachRouter(vueRouter) {
    router = vueRouter;

    if (pending) {
        const url = pending;
        pending = null;

        routeUrl(url);
    }

    // Warm start: the app is already running when the link is tapped.
    App.addListener('appUrlOpen', ({ url }) => routeUrl(url));

    // Cold start: the link is what launched the app.
    const launch = await App.getLaunchUrl().catch(() => null);

    if (launch?.url) {
        routeUrl(launch.url);
    }
}
