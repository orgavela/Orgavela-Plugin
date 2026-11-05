import js from '@eslint/js';
import globals from 'globals';
export default [
  { ignores: ['vendor/**', 'build/**', 'dist/**', 'node_modules/**', 'test-results/**'] },
  js.configs.recommended,
  { languageOptions: { globals: globals.node } },
];
