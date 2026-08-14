import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const root = dirname(fileURLToPath(import.meta.url));

// Build contract:
// - Stable output names (main.css / main.js, no content hashes): the Drupal
//   library in pe_theme.libraries.yml references fixed paths.
// - Fonts are copied into dist/fonts/ and referenced with relative url()
//   so the bundle works from /themes/custom/pe_theme/dist/ under any base
//   path. Self-hosted only: the production CSP blocks external font hosts.
// - Output must be deterministic: CI rebuilds and diffs dist/ against git.
export default defineConfig({
  base: './',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    assetsInlineLimit: 0,
    rollupOptions: {
      input: {
        main: resolve(root, 'src/js/main.js'),
        style: resolve(root, 'src/css/main.css'),
      },
      output: {
        entryFileNames: '[name].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.names?.[0] ?? '';
          if (name.endsWith('.css')) {
            return 'main.css';
          }
          if (/\.(woff2?|ttf|otf|eot)$/.test(name)) {
            return 'fonts/[name][extname]';
          }
          return '[name][extname]';
        },
      },
    },
  },
});
