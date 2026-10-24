// Bundles src/ into ../public/app (index.html + app.js + app.css). Run: npm install && npm run build
import * as esbuild from 'esbuild';
import { mkdirSync, writeFileSync, copyFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const out = join(here, '..', 'public', 'panel');
mkdirSync(out, { recursive: true });

const opts = {
  entryPoints: [join(here, 'src', 'main.jsx')],
  bundle: true, minify: true, sourcemap: false, target: ['es2020'],
  outfile: join(out, 'app.js'), jsx: 'automatic', loader: { '.css': 'css' },
  define: { 'process.env.NODE_ENV': '"production"' }, legalComments: 'none', logLevel: 'info',
};
const css = { entryPoints: [join(here, 'src', 'styles.css')], bundle: true, minify: true, outfile: join(out, 'app.css'), logLevel: 'info' };

const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>HOTELGO Console</title>
<link rel="icon" href="../favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Sora:wght@600;700&display=swap">
<link rel="stylesheet" href="app.css">
</head>
<body>
<div id="root"></div>
<noscript>HOTELGO Console needs JavaScript.</noscript>
<script src="app.js" defer></script>
</body>
</html>
`;
writeFileSync(join(out, 'index.html'), html);

if (process.argv.includes('--watch')) {
  const a = await esbuild.context(opts); const b = await esbuild.context(css);
  await a.watch(); await b.watch(); console.log('watching…');
} else {
  await esbuild.build(opts); await esbuild.build(css);
}
