/*
 * Verifies Android App Links end to end.
 *
 * autoVerify fails SILENTLY: if the fingerprint in assetlinks.json does not
 * match the key the APK was signed with, links simply open in the browser and
 * nothing anywhere says why. This makes that failure loud and early.
 *
 * Usage:
 *   node node/capacitor/verify-applinks.mjs
 *   node node/capacitor/verify-applinks.mjs --keystore android/upload.jks --alias upload
 *   node node/capacitor/verify-applinks.mjs --url https://app.rebit.in
 *
 * On a device, the authoritative check is:
 *   adb shell pm get-app-links com.rebit.app     # expect: verified
 */

import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

const arg = (name) => {
    const i = process.argv.indexOf(`--${name}`);

    return i === -1 ? null : process.argv[i + 1];
};

const LOCAL = 'public/.well-known/assetlinks.json';
const APP_ID = JSON.parse(fs.readFileSync('capacitor.config.json', 'utf8')).appId;

const problems = [];
const notes = [];

// --- the committed file ---------------------------------------------------

if (! fs.existsSync(LOCAL)) {
    console.error(`  ${LOCAL} is missing.`);
    process.exit(1);
}

const statements = JSON.parse(fs.readFileSync(LOCAL, 'utf8'));
const target = statements.find((s) => s.target?.namespace === 'android_app');

if (! target) {
    problems.push('no android_app statement in assetlinks.json');
} else {
    if (target.target.package_name !== APP_ID) {
        problems.push(`package_name is ${target.target.package_name}, but capacitor.config.json appId is ${APP_ID}`);
    }

    const fingerprints = target.target.sha256_cert_fingerprints ?? [];

    notes.push(`${fingerprints.length} fingerprint(s) declared for ${target.target.package_name}`);

    if (fingerprints.length < 2) {
        notes.push('With Play App Signing you need TWO: the upload key AND the Play-managed key');
        notes.push('  (Play Console -> Setup -> App integrity -> App signing key certificate)');
    }

    for (const fp of fingerprints) {
        if (! /^([0-9A-F]{2}:){31}[0-9A-F]{2}$/i.test(fp)) {
            problems.push(`fingerprint is not 32 colon-separated hex bytes: ${fp.slice(0, 24)}...`);
        }
    }

    // --- against a real keystore, when one is given -----------------------

    const keystore = arg('keystore');
    const alias = arg('alias');

    if (keystore && alias) {
        if (! fs.existsSync(keystore)) {
            problems.push(`keystore not found: ${keystore}`);
        } else {
            const password = process.env.KEYSTORE_PASSWORD;

            if (! password) {
                problems.push('set KEYSTORE_PASSWORD to compare against the keystore');
            } else {
                try {
                    const out = execFileSync('keytool', [
                        '-list', '-v', '-keystore', keystore, '-alias', alias, '-storepass', password,
                    ], { encoding: 'utf8' });

                    const match = out.match(/SHA256:\s*([0-9A-F:]{95})/i);

                    if (! match) {
                        problems.push('could not read a SHA-256 fingerprint from keytool output');
                    } else {
                        const actual = match[1].toUpperCase();
                        const declared = fingerprints.map((f) => f.toUpperCase());

                        declared.includes(actual)
                            ? notes.push(`keystore fingerprint IS declared: ${actual.slice(0, 17)}...`)
                            : problems.push(
                                `keystore fingerprint is NOT in assetlinks.json.\n`
                              + `      keystore  : ${actual}\n`
                              + `      declared  : ${declared.join('\n                  ') || '(none)'}`);
                    }
                } catch (error) {
                    problems.push(`keytool failed: ${error.message.split('\n')[0]}`);
                }
            }
        }
    } else {
        notes.push('pass --keystore and --alias (plus KEYSTORE_PASSWORD) to check the signing key');
    }
}

// --- the live file --------------------------------------------------------

const url = arg('url');

if (url) {
    const endpoint = `${url.replace(/\/$/, '')}/.well-known/assetlinks.json`;

    try {
        const response = await fetch(endpoint, { redirect: 'manual' });

        if (response.status !== 200) {
            problems.push(`${endpoint} returned ${response.status} (must be 200, with no redirect)`);
        }

        const type = response.headers.get('content-type') ?? '';

        if (! type.includes('json')) {
            problems.push(`${endpoint} served as "${type}", must be application/json`);
        }

        if (response.status === 200) {
            const live = await response.json();

            JSON.stringify(live) === JSON.stringify(statements)
                ? notes.push('the served file matches the committed one')
                : problems.push('the served file DIFFERS from the committed one (stale deploy?)');
        }
    } catch (error) {
        problems.push(`could not fetch ${endpoint}: ${error.message}`);
    }
} else {
    notes.push('pass --url https://app.rebit.in to check what is actually served');
}

// --- report ---------------------------------------------------------------

notes.forEach((n) => console.log(`  note  ${n}`));

if (problems.length) {
    console.log('\nPROBLEMS:');
    problems.forEach((p) => console.log(`  !!  ${p}`));
    process.exit(1);
}

console.log('\nassetlinks.json is internally consistent');
