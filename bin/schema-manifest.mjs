import { readFileSync, writeFileSync } from 'node:fs';
const root = new URL('../', import.meta.url);
const phase2 = readFileSync(new URL('docs/specification/phase-2-v1-specification.md', root), 'utf8');
const phase3 = readFileSync(new URL('docs/specification/phase-3-specification-freeze.md', root), 'utf8');
const tables = {};
for (const [source, expected] of [[phase2, 26], [phase3, 2]]) {
  const matches = [...source.matchAll(/CREATE TABLE \{p\}(\w+) \(([\s\S]*?)\n\);/g)];
  if (matches.length !== expected) throw new Error('Specification table count changed');
  for (const [, name, definition] of matches) {
    if (tables[name]) throw new Error('Duplicate table');
    let lines = definition.trim().split(/\n/).map(x => x.trim());
    const joined = [];
    for (let i = 0; i < lines.length; i++) {
      let line = lines[i];
      if (/^(UNIQUE KEY|KEY|PRIMARY KEY)/.test(line) && !line.includes(')')) {
        while (!line.includes(')')) line += ' ' + lines[++i];
      }
      joined.push(line.replace(/\s+/g, ' ').replace(/\( /g, '(').replace(/ \)/g, ')'));
    }
    if (name === 'email_messages') {
      const index = joined.findIndex(x => x.startsWith('PRIMARY KEY'));
      joined.splice(index, 0, 'template_revision INT UNSIGNED NOT NULL,', 'template_hash BINARY(32) NOT NULL,', 'locale VARCHAR(20) NOT NULL,');
    }
    tables[name] = joined;
  }
}
const manifest = JSON.stringify({ schema_version: 1, tables }, null, 2) + '\n';
const path = new URL('schema/manifest.json', root);
if (process.argv.includes('--check')) {
  if (readFileSync(path, 'utf8') !== manifest) throw new Error('Schema manifest drift from frozen specifications');
} else writeFileSync(path, manifest);
