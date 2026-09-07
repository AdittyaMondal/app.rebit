/*
 * Builds the static shell the Capacitor WebView loads.
 *
 * The SPA has no index.html: on the web it is bootstrapped by a Blade layout.
 * This reproduces that layout's <head> from Vite's own manifest, so asset
 * hashes are never hand-maintained.
 *
 * public/build is copied to public/native/build verbatim, preserving the
 * /build/assets/... paths the bundle already emits. That is deliberate --
 * mirroring the production layout means not a single built URL needs rewriting.
 *
 * Run via `npm run build:native`, never on its own: it consumes whatever
 * `npm run build` last produced, including the five dark-theme Tailwind passes
 * that sit outside the Vite pipeline.
 */

import fs from 'node:fs';
import path from 'node:path';

const ROOT = process.cwd();
const BUILD_DIR = path.join(ROOT, 'public/build');
const OUT_DIR = path.join(ROOT, 'public/native');
const MANIFEST = path.join(BUILD_DIR, 'manifest.json');

const ENTRY = 'resources/js/spa/apps/mobile/bootstrap/native.js';
// The mobile theme sheet is tagged so native.js can disable it when the user's
// theme is dark: the Blade shell picks light OR dark, never both, and loading
// both would leak light rules the dark sheet does not happen to override.
const THEME_STYLE = 'resources/css/spa/apps/mobile/main.css';

const STYLES = [
    THEME_STYLE,
    'resources/fonts/stylesheet.css',
];

const die = (message) => {
    console.error(`\n  pack.mjs: ${message}\n`);
    process.exit(1);
};

// --- guards ---------------------------------------------------------------

// VITE_API_BASE_URL is compiled into the bundle. A native build that inherits an
// empty .env ships a binary permanently pointing at "undefined/api/", and the
// only fix is a store update. Refuse to package that.
const apiBase = process.env.VITE_API_BASE_URL;

if (! apiBase) {
    die('VITE_API_BASE_URL is not set. The API origin is baked into the bundle at\n'
      + '  build time, so packaging without it produces an app that can never reach\n'
      + '  the backend. Set it for the whole build, e.g.\n'
      + '    VITE_API_BASE_URL=https://app.rebit.in npm run build:native');
}

if (! fs.existsSync(MANIFEST)) {
    die('public/build/manifest.json is missing. Run `npm run build` first.');
}

const manifest = JSON.parse(fs.readFileSync(MANIFEST, 'utf8'));

const entry = manifest[ENTRY];

if (! entry) {
    die(`${ENTRY} is not in the Vite manifest. Is it listed in vite.config.js inputs?`);
}

// Catch the mismatch where the build ran with a different origin than the pack.
const entryJs = fs.readFileSync(path.join(BUILD_DIR, entry.file), 'utf8');

if (! entryJs.includes(apiBase)) {
    die(`the built bundle does not contain ${apiBase}.\n`
      + '  The build and the pack ran with different VITE_API_BASE_URL values.\n'
      + '  Run `npm run build:native`, which does both in one shot.');
}

const styles = STYLES.map((key) => {
    if (! manifest[key]) {
        die(`${key} is not in the Vite manifest.`);
    }

    return manifest[key].file;
});

// --- emit -----------------------------------------------------------------

fs.rmSync(OUT_DIR, { recursive: true, force: true });
fs.mkdirSync(OUT_DIR, { recursive: true });
fs.cpSync(BUILD_DIR, path.join(OUT_DIR, 'build'), { recursive: true });

const appName = process.env.VITE_APP_NAME || 'REBIT';

const html = `<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <!-- viewport-fit=cover is what makes env(safe-area-inset-*) resolve to a
             real value. It lives here and NOT in the Blade shell: adding it there
             would change safe-area spacing on mobile web. -->
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">
        <meta name="color-scheme" content="light only">

        <title>${appName}</title>

${STYLES.map((key, i) => {
    const id = key === THEME_STYLE ? ' id="theme-light"' : '';

    return `        <link rel="stylesheet"${id} href="/build/${styles[i]}">`;
}).join('\n')}

        <!-- No PWA partial: the service worker's cache-first fetch handler would
             hijack the bundled assets. No device-switcher either -- the mobile
             bundle is shipped directly, so there is nothing to switch. -->
        <script type="module" src="/build/${entry.file}"></script>
    </head>
    <body>
        <!-- Sign-in / no-connection screen. Plain markup rather than a Vue
             route: without a token the SPA is never imported at all, so there
             is no router to own this. -->
        <div id="native-gate" hidden style="position:fixed;inset:0;z-index:9999;display:flex;flex-direction:column;gap:1.5rem;align-items:center;justify-content:center;padding:2rem;text-align:center;font:16px/1.5 system-ui,-apple-system,sans-serif;background:#000;color:#fff;">
            <p data-gate-message style="margin:0;max-width:24rem;"></p>
            <button data-gate-action hidden type="button" style="appearance:none;border:0;border-radius:9999px;padding:0.9rem 2.5rem;font:inherit;font-weight:600;background:#fff;color:#000;cursor:pointer;"></button>
        </div>

        <div id="colibriplus-mobile-app"></div>
    </body>
</html>
`;

fs.writeFileSync(path.join(OUT_DIR, 'index.html'), html);

const bytes = fs.readdirSync(path.join(OUT_DIR, 'build/assets')).length;

console.log(`  pack.mjs: public/native ready`);
console.log(`    api     ${apiBase}`);
console.log(`    entry   /build/${entry.file}`);
console.log(`    styles  ${styles.map((f) => '/build/' + f).join(', ')}`);
console.log(`    assets  ${bytes} files copied`);
