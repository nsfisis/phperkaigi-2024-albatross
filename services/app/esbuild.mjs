import { build } from 'esbuild'

let ALBATROSS_BASE_PATH = process.env.ALBATROSS_BASE_PATH;
if (ALBATROSS_BASE_PATH == null) {
  throw new Error('$ALBATROSS_BASE_PATH is not set');
}
// WORKAROUND:
// Unquote the value as work-around for Docker and Docker Compose.
// How Docker parses .env files is different from how Docker Compose does for some reason.
// Docker treats the value as is, while Docker Compose strips the outermost quotes.
ALBATROSS_BASE_PATH = ALBATROSS_BASE_PATH.replace(/^"(.*)"$/, '$1');

await build({
  entryPoints: ['assets/index.js', 'assets/chart.js', 'assets/loading.js'],
  outdir: 'public/assets',
  bundle: true,
  minify: true,
  sourcemap: true,
  define: {
    'process.env.ALBATROSS_BASE_PATH': JSON.stringify(ALBATROSS_BASE_PATH),
  },
});
