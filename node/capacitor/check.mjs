/*
 * Post-pack sanity check for public/native.
 *
 * Every failure here is something that produces a blank screen on a device with
 * no useful error, which is expensive to debug through a store build. Cheap to
 * assert at build time instead.
 */

import fs from 'node:fs';
import path from 'node:path';

const OUT = 'public/native';
const html = fs.readFileSync(path.join(OUT, 'index.html'), 'utf8');
const fail = [];
const ok = [];

// 1. every asset index.html references must exist on disk
const refs = [...html.matchAll(/(?:href|src)="([^"]+)"/g)].map(m => m[1]);
for (const ref of refs) {
    const p = path.join(OUT, ref.replace(/^\//, ''));
    (fs.existsSync(p) ? ok : fail).push(`${ref} ${fs.existsSync(p) ? '' : 'MISSING'}`);
}

// 2. the entry chunk's dynamic import must resolve to a real chunk
const entryRef = refs.find(r => r.includes('native-'));
const entryPath = path.join(OUT, entryRef.replace(/^\//, ''));
const entrySrc = fs.readFileSync(entryPath, 'utf8');
// Vite emits dynamic imports with backticks, not double quotes.
const dyn = [...entrySrc.matchAll(/import\([`'"]\.\/([^`'"]+)[`'"]\)/g)].map(m => m[1]);
if (!dyn.length) fail.push('entry chunk has no dynamic import of application.js');
for (const d of dyn) {
    const p = path.join(path.dirname(entryPath), d);
    fs.existsSync(p) ? ok.push(`dynamic import ./${d}`) : fail.push(`dynamic import ./${d} MISSING`);
}

// 3. the runtime-appended dark stylesheet must exist at the fixed path
const dark = path.join(OUT, 'build/assets/mobile-main-dark.css');
fs.existsSync(dark) ? ok.push('build/assets/mobile-main-dark.css') : fail.push('mobile-main-dark.css MISSING');

// 4. the shell must NOT register the service worker or the device switcher
// strip comments first: the shell explains in prose what it deliberately omits
const markup = html.replace(/<!--[\s\S]*?-->/g, '');
if (/service-worker/.test(markup)) fail.push('service worker present in native shell');
if (/device-switcher/.test(markup)) fail.push('device switcher present in native shell');
if (/BackendEmbeds/.test(markup)) fail.push('embeds inlined into the native shell; they must be fetched');
if (!/viewport-fit=cover/.test(html)) fail.push('viewport-fit=cover missing');
if (!/id="colibriplus-mobile-app"/.test(html)) fail.push('Vue mount point missing');
if (!/id="native-gate"/.test(html)) fail.push('sign-in gate missing');
// native.js disables this sheet to switch to dark; without the id the app
// renders dark text on a light background.
if (!/id="theme-light"/.test(html)) fail.push('light theme sheet has no id; dark mode cannot disable it');

// The API origin is NOT checked here: pack.mjs already refuses to package
// unless VITE_API_BASE_URL is set and actually appears in the built entry,
// which is a stronger guarantee than pattern-matching minified output.
const allJs = fs.readdirSync(path.join(OUT, 'build/assets')).filter(f => f.endsWith('.js'));

// 5. Vite's base must still be /build/, or runtime-resolved preload deps break.
// The helper is the chunk that prefixes every lazily-loaded dep with the base.
const helperPath = allJs.map(f => path.join(OUT, 'build/assets', f))
    .find(p => /modulepreload/.test(fs.readFileSync(p, 'utf8')));
if (!helperPath) fail.push('could not find the vite preload helper chunk');
else {
    const src = fs.readFileSync(helperPath, 'utf8');
    // emitted with backticks by this build, so match either quoting
    /return\s*[`'"]\/build\/[`'"]\s*\+/.test(src)
        ? ok.push('vite base is /build/ -- preload deps resolve against the mirrored layout')
        : fail.push('vite base is not /build/; lazily-loaded chunks will 404 in the shell');
}

// 6. every path claimed as an App Link must exist in the mobile router.
// Claiming a path the router cannot resolve sends the user into the app and
// then nowhere -- worse than letting the browser keep the link.
const manifestPath = 'android/app/src/main/AndroidManifest.xml';

if (fs.existsSync(manifestPath)) {
    const manifest = fs.readFileSync(manifestPath, 'utf8');
    const routerSrc = fs.readFileSync('resources/js/spa/apps/mobile/router/index.js', 'utf8');

    // First path segment of every top-level route, with :params and inline
    // regex stripped: '/@:id([a-zA-Z0-9._]+)' -> '@', '/publication/:hash_id'
    // -> 'publication'. Nested children ('c/:chat_id') live under their parent,
    // so the top segment is the right granularity.
    const topSegments = new Set(
        [...routerSrc.matchAll(/path:\s*'(\/[^']*)'/g)]
            .map(m => m[1].split('/')[1] ?? '')
            .map(seg => seg.split('(')[0].replace(/:.*$/, ''))
            .filter(Boolean)
    );

    for (const prefix of [...manifest.matchAll(/android:pathPrefix="([^"]+)"/g)].map(m => m[1])) {
        const segment = prefix.split('/')[1] ?? '';

        topSegments.has(segment)
            ? ok.push(`app link ${prefix} resolves in the mobile router`)
            : fail.push(`AndroidManifest claims ${prefix}, but no mobile route starts with "/${segment}"`);
    }

    if (/android:pathPattern="\.\*"/.test(manifest)) {
        fail.push('manifest uses a catch-all pathPattern, which would hijack /auth/*, /document/* and the admin prefix');
    }
}

console.log('checks passed:');
ok.forEach(o => console.log('  ok  ' + o));
if (fail.length) { console.log('\nFAILURES:'); fail.forEach(f => console.log('  !!  ' + f)); process.exit(1); }
console.log('\nshell structurally sound');
