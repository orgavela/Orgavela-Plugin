import { mkdirSync, writeFileSync } from 'node:fs';
// Foundation has no frontend bundles. This deterministic manifest is the asset pipeline entry.
mkdirSync('build', { recursive: true });
writeFileSync('build/manifest.json', JSON.stringify({ schema_version: 1, assets: [] }, null, 2) + '\n');
