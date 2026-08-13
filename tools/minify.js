#!/usr/bin/env node
/* Folio — build the minified asset twins.
 *
 * Run this before packaging a release, from the Folio root:
 *
 *     npm install --no-save terser clean-css
 *     node tools/minify.js
 *
 * It writes a `.min.css` or `.min.js` beside each source. Folio serves the
 * minified file when it is not older than its source, so editing a source
 * immediately takes precedence again with nothing to rebuild. Deleting the
 * minified files is always safe and reverts to readable sources.
 *
 * This is a maintainer's tool. Nobody installing Folio needs Node, npm, or
 * this script: the minified files ship in the package already built.
 */

'use strict';

const fs = require('fs');
const crypto = require('crypto');
const path = require('path');
const CleanCSS = require('clean-css');
const { minify } = require('terser');

const ROOT = path.resolve(__dirname, '..');

const TARGETS = [
    'assets/css/style.css',
    'assets/css/flipbook.css',
    'assets/js/app.js',
    'assets/js/view.js',
    'assets/js/media.js',
    'assets/js/admin.js',
    'assets/js/flipbook.js',
];

function kib(n) {
    return (n / 1024).toFixed(1) + ' KiB';
}

async function run() {
    let before = 0;
    let after = 0;
    let failed = 0;
    const manifest = {};

    for (const rel of TARGETS) {
        const src = path.join(ROOT, rel);
        if (!fs.existsSync(src)) {
            console.error(`  MISSING  ${rel}`);
            failed++;
            continue;
        }
        const code = fs.readFileSync(src, 'utf8');
        const ext = path.extname(rel).slice(1);
        const out = src.slice(0, -ext.length) + 'min.' + ext;

        let min;
        if (ext === 'css') {
            const res = new CleanCSS({ level: 2, returnPromise: false }).minify(code);
            if (res.errors.length) {
                console.error(`  FAILED   ${rel}: ${res.errors.join('; ')}`);
                failed++;
                continue;
            }
            min = res.styles;
        } else {
            /* `module: false` keeps the top-level IIFE semantics these files
               rely on. Names are not mangled at the top level for the same
               reason: flipbook.js is loaded as a module and its imports must
               keep their shape. */
            const res = await minify(code, {
                ecma: 2018,
                module: false,
                compress: { drop_console: false },
                mangle: { toplevel: false },
                format: { comments: false },
            });
            if (!res || typeof res.code !== 'string') {
                console.error(`  FAILED   ${rel}`);
                failed++;
                continue;
            }
            min = res.code;
        }

        fs.writeFileSync(out, min);

        /* Record the source's byte length so the application can tell whether
           this minified file was built from the source now on disk. Modification
           times cannot answer that after an FTP upload; a byte length can. */
        manifest[rel] = {
            min: path.relative(ROOT, out).split(path.sep).join('/'),
            size: Buffer.byteLength(code),
        };
        /* The base64 SHA-256 of the minified bytes. A stylesheet small enough
           to inline is served inside the document to remove a render-blocking
           round trip, and this lets the Content-Security-Policy allow exactly
           that one block by hash — no 'unsafe-inline', and no per-request
           nonce, which would make the page uncacheable. */
        if (ext === 'css') {
            manifest[rel].sha256 = crypto.createHash('sha256').update(min, 'utf8').digest('base64');
            manifest[rel].min_size = Buffer.byteLength(min);
        }

        before += code.length;
        after += min.length;
        const pct = Math.round((1 - min.length / code.length) * 100);
        console.log(`  ${rel.padEnd(28)} ${kib(code.length).padStart(9)} -> ${kib(min.length).padStart(9)}  (-${pct}%)`);
    }

    const manifestPath = path.join(ROOT, 'assets', 'manifest.json');
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 1) + '\n');
    console.log(`  ${'total'.padEnd(28)} ${kib(before).padStart(9)} -> ${kib(after).padStart(9)}`);
    console.log(`  wrote assets/manifest.json (${Object.keys(manifest).length} entries)`);
    if (failed) {
        console.error(`\n${failed} file(s) failed. No release should ship with this unresolved.`);
        process.exit(1);
    }
}

run().catch((err) => {
    console.error(err);
    process.exit(1);
});
