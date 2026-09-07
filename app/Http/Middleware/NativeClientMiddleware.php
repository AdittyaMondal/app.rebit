<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns off monetization for native clients.
 *
 * The only digital-goods surface is POST /api/wallet/deposit, which hands back a
 * hosted Stripe/PayPal checkout URL for buying in-app currency. Google Play's
 * Payments policy forbids that, so it has to be both invisible and unreachable
 * from the app.
 *
 * No new mechanism is introduced here. SettingsServiceProvider already sets
 * features.wallet.enabled from the database on every boot, so overriding it
 * later in the request is the established pattern -- and because middleware runs
 * after providers boot, this wins.
 *
 * Pairing it with the existing `features.status:wallet` route middleware is what
 * makes the endpoint 404 rather than merely hidden. That also closes a real gap
 * on the web: until now an admin could switch the wallet off in WalletSettings
 * and the entire wallet API stayed open.
 *
 * The app is identified by the user agent Capacitor appends (appendUserAgent in
 * capacitor.config.json). A user agent is spoofable, but the threat model here
 * is a store reviewer finding a purchase path, not an attacker -- and anyone
 * spoofing it only succeeds in disabling their own wallet.
 */
class NativeClientMiddleware
{
    public const USER_AGENT_MARKER = 'RebitApp/';

    public function handle(Request $request, Closure $next): Response
    {
        if (str_contains((string) $request->userAgent(), self::USER_AGENT_MARKER)) {
            config(['features.wallet.enabled' => false]);
        }

        return $next($request);
    }
}
