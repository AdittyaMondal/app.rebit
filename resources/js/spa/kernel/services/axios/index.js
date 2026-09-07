import Axios from 'axios';


const baseURL = import.meta.env.VITE_API_BASE_URL;
const appApiPrefix = import.meta.env.VITE_APP_API_PREFIX;

const AxiosAuthHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
};

if(window.ColibriBRD) {
    AxiosAuthHeaders['X-Socket-ID'] = window.ColibriBRD.connector.pusher.connection.socket_id;
}

// Create an Axios instance
const AxiosAuth = Axios.create({
    baseURL: `${baseURL}/${appApiPrefix}/`,
    headers: AxiosAuthHeaders
});

AxiosAuth.defaults.withCredentials = true;

/*
 * Native only. Every branch below is dead code on the web, where this file
 * behaves exactly as it did before: cookie auth, and no interceptors at all.
 */
if (window.__NATIVE__) {
    // Cookies play no part under bearer auth, and a capacitor:// origin cannot
    // carry the session cookie anyway.
    AxiosAuth.defaults.withCredentials = false;

    // Read at request time rather than captured once, so the token that arrives
    // after sign-in is picked up without a reload.
    AxiosAuth.interceptors.request.use((config) => {
        if (window.__NATIVE_TOKEN) {
            config.headers.Authorization = `Bearer ${window.__NATIVE_TOKEN}`;
        }

        return config;
    });

    let signingOut = false;

    AxiosAuth.interceptors.response.use(null, (error) => {
        const status = error.response?.status;

        if (status === 401 && ! signingOut) {
            signingOut = true;

            window.nativeSignOut?.();
        }

        /*
         * A 419 means Sanctum treated this as a *stateful* request and enforced
         * CSRF, which only happens when the WebView origin matches
         * SANCTUM_STATEFUL_DOMAINS -- i.e. a server misconfiguration, not an
         * expired token. Signing out here would bury that behind a login loop.
         */
        if (status === 419) {
            console.error(
                '419 from the API: the native origin is in SANCTUM_STATEFUL_DOMAINS. '
                + 'Remove "localhost" from it; the bearer token is being ignored.'
            );
        }

        return Promise.reject(error);
    });
}

export { AxiosAuth, Axios };