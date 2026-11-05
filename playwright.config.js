import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/Browser', workers: 1,
  use: { baseURL: process.env.WP_URL || 'http://127.0.0.1:8080', trace: 'retain-on-failure' },
});
