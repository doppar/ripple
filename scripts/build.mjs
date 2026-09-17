import * as esbuild from 'esbuild';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const outfile = resolve(root, 'resources/dist/ripple.min.js');
const watch = process.argv.includes('--watch');

mkdirSync(dirname(outfile), { recursive: true });

const options = {
  entryPoints: [resolve(root, 'resources/js/src/index.js')],
  bundle: true,
  minify: true,
  format: 'iife',
  target: ['es2018'],
  outfile,
  logLevel: 'info',
};

if (watch) {
  const ctx = await esbuild.context(options);
  await ctx.watch();
  console.log('watching resources/js/src -> resources/dist/ripple.min.js');
} else {
  await esbuild.build(options);
  console.log('built resources/dist/ripple.min.js');
}
