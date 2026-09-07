import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.ColibriBRConnected = false;
window.Pusher = Pusher;
window.Echo = Echo;
Pusher.logToConsole = import.meta.env.PUSHER_DEBUG_CONSOLE;
const REVERB_CONNECTION_STATUS = import.meta.env.VITE_REVERB_CONNECTION_STATUS;

try {
    if (REVERB_CONNECTION_STATUS == 'on') {
        window.ColibriBRD = new Echo({
            namespace: 'null',
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            cluster: false,

            /*
             * laravel-echo defaults authEndpoint to a relative
             * '/broadcasting/auth', which under Capacitor resolves against the
             * local server. Point it at the absolute API twin of that route,
             * which accepts a bearer token instead of a session cookie.
             *
             * Safe to read the token here: native.js only imports the app once
             * it has one, and this module is reached through that import.
             */
            ...(window.__NATIVE__ ? {
                authEndpoint: `${import.meta.env.VITE_API_BASE_URL}/${import.meta.env.VITE_APP_API_PREFIX}/broadcasting/auth`,
                auth: {
                    headers: {
                        Authorization: `Bearer ${window.__NATIVE_TOKEN}`
                    }
                }
            } : {})
        });

        window.ColibriBRD.connector.pusher.connection.bind('connected', function() {
            console.log('📶 Websockets connection is established.');

            window.ColibriBRConnected = true;
        });
    }

    else {
        console.info("📶 Websockets connection is disabled. Please configure your broadcaster server and enable Reverb connection in your app settings. (ColibriPlus)");
    }
}

catch (error) {
    console.log(error);
}