import { test, expect } from '@playwright/test';
import { bookShipment, login, logout, selectByText } from './helpers';

test.describe.serial('courier end-to-end', () => {
    let tracking = '';

    test('customer books and pays for a shipment (sandbox), then tracks it', async ({ page }) => {
        await login(page, 'customer@example.com');
        tracking = await bookShipment(page, 'success');
        await expect(page.getByText('Payment confirmed. Your shipment is booked.')).toBeVisible();
        await expect(page.getByText(tracking)).toBeVisible();

        await page.goto('/track');
        await page.getByLabel('Tracking number').fill(tracking.toLowerCase());
        await page.getByRole('button', { name: 'Track', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Shipment history' })).toBeVisible();
        await expect(page.getByRole('list', { name: 'Shipment timeline' }).getByText('Payment received. Shipment confirmed and booked.')).toBeVisible();
        await expect(page.getByText('9 Example Ave')).toHaveCount(0); // private address never public
    });

    test('dispatcher assigns a rider', async ({ page }) => {
        await login(page, 'dispatcher@example.com');
        await page.goto(`/ops/shipments/${tracking}`);
        await selectByText(page, 'Assign to', 'Demo Courier One');
        await page.getByLabel('Leg').selectOption('delivery');
        await page.getByRole('button', { name: 'Assign', exact: true }).click();
        await expect(page.getByText('Assigned to Demo Courier One.')).toBeVisible();
    });

    test('rider updates status and submits proof of delivery', async ({ page }) => {
        await login(page, 'rider@example.com');
        await page.getByRole('link', { name: new RegExp(tracking) }).click();
        await page.getByRole('button', { name: 'Accept job' }).click();
        await expect(page.getByText('Job accepted.')).toBeVisible();

        for (const label of ['Picked up', 'Out for delivery']) {
            await page.getByLabel(label).check();
            await page.getByRole('button', { name: 'Update', exact: true }).click();
            await expect(page.getByText(`Status updated to “${label}”.`)).toBeVisible();
        }

        await page.getByLabel('Received by (full name)').fill('E2E Recipient');
        const canvas = page.locator('canvas'); await canvas.scrollIntoViewIfNeeded();
        const box = (await canvas.boundingBox())!;
        await page.mouse.move(box.x + 20, box.y + 30);
        await page.mouse.down();
        await page.mouse.move(box.x + 120, box.y + 90, { steps: 8 });
        await page.mouse.move(box.x + 200, box.y + 40, { steps: 8 });
        await page.mouse.up();
        await page.getByRole('button', { name: 'Mark delivered' }).click();
        await expect(page.getByText(`Delivered — proof saved for ${tracking}.`)).toBeVisible();
    });

    test('customer sees the resulting timeline and proof', async ({ page }) => {
        await login(page, 'customer@example.com');
        await page.goto(`/account/shipments/${tracking}`);
        for (const s of ['Rider assigned', 'Picked up', 'Out for delivery']) {
            await expect(page.getByRole('list', { name: 'Shipment timeline' }).getByText(s, { exact: true })).toBeVisible();
        }
        await expect(page.getByRole('heading', { name: 'Proof of delivery' })).toBeVisible();
        await expect(page.getByAltText('Recipient signature')).toBeVisible();
    });

    test('unauthorized users cannot access restricted pages or data', async ({ page }) => {
        await login(page, 'customer@example.com');
        for (const url of ['/ops', '/admin/settings', '/rider']) {
            const res = await page.goto(url);
            expect(res?.status()).toBe(403);
        }
        const api = await page.request.get('/api/admin/dashboard', { headers: { Accept: 'application/json' } });
        expect(api.status()).toBe(403);
        await page.goto('/account');
        await logout(page);
        const res = await page.goto('/ops');
        await expect(page).toHaveURL(/\/login/);
        expect(res?.ok()).toBeTruthy();
    });

    test('payment failure keeps the shipment awaiting payment', async ({ page }) => {
        await login(page, 'customer@example.com');
        await bookShipment(page, 'failed');
        await expect(page.getByText(/Payment was not completed/)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Pay securely' })).toBeVisible();
    });

    test('failed delivery is recorded and resolved by operations', async ({ page }) => {
        await login(page, 'customer@example.com');
        const tn = await bookShipment(page, 'success');
        await logout(page);

        await login(page, 'dispatcher@example.com');
        await page.goto(`/ops/shipments/${tn}`);
        await selectByText(page, 'Assign to', 'Demo Courier One');
        await page.getByLabel('Leg').selectOption('delivery');
        await page.getByRole('button', { name: 'Assign', exact: true }).click();
        await logout(page);

        await login(page, 'rider@example.com');
        await page.getByRole('link', { name: new RegExp(tn) }).click();
        await page.getByLabel('Out for delivery').check();
        await page.getByRole('button', { name: 'Update', exact: true }).click();
        await page.getByRole('button', { name: /Couldn't deliver/ }).click();
        await page.getByLabel('Reason').selectOption('recipient_unavailable');
        await page.getByRole('button', { name: 'Record failed attempt' }).click();
        await expect(page.getByText('Failed attempt recorded.')).toBeVisible();
        await logout(page);

        await login(page, 'dispatcher@example.com');
        await page.goto('/ops/exceptions');
        const row = page.locator('li', { hasText: tn });
        await row.getByLabel('Next step').selectOption('hold');
        await row.getByRole('button', { name: 'Save' }).click();
        await expect(page.getByText('Next step recorded.')).toBeVisible();

        await page.goto(`/track/${tn}`);
        await expect(page.getByRole('list', { name: 'Shipment timeline' }).getByText(/being held at our hub/)).toBeVisible();
    });
});
