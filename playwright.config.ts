import { defineConfig } from '@playwright/test';

/**
 * End-to-end tests drive the real app in Chromium against a disposable database
 * seeded with DEMO data and the SANDBOX payment gateway (no real money, no real
 * credentials). Run: npm run test:e2e   (see README "Testing").
 */
const port = Number(process.env.E2E_PORT ?? 8123);
const db = process.env.E2E_DB_DATABASE ?? 'courier_test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    workers: 1,
    fullyParallel: false,
    reporter: [['list']],
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        launchOptions: process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {},
        trace: 'retain-on-failure',
    },
    webServer: {
        command: `php artisan migrate:fresh --force --seed --seeder=DemoSeeder && php artisan serve --port=${port}`,
        url: `http://127.0.0.1:${port}/health`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: { DB_DATABASE: db, APP_ENV: 'local', PAYMENT_PROVIDER: 'sandbox', MAIL_MAILER: 'log', SMS_DRIVER: 'log', CACHE_STORE: 'array', APP_URL: `http://127.0.0.1:${port}` },
    },
});
