const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/E2E',
  timeout: 60 * 1000,
  use: {
    baseURL: 'http://localhost:8888',
    headless: true,
  },
});
