import { expect, Page } from '@playwright/test';

export const PASSWORD = 'password123';

export async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).not.toHaveURL(/\/login/);
}

export async function logout(page: Page) {
    await page.goto('/portal');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page).toHaveURL(/\/$/);
}

/** Book a Manhattan → Brooklyn shipment through the UI and return its tracking number. */
export async function bookShipment(page: Page, payOutcome: 'success' | 'failed' = 'success'): Promise<string> {
    await page.goto('/send');
    await page.getByLabel('Sender name').fill('E2E Sender');
    await page.getByLabel('Sender phone').fill('(212) 555-0147');
    await page.getByLabel('Sender email').fill('e2e.sender@example.com');
    await page.locator('#pickup_country').selectOption('US');
    await page.locator('#pickup_address').fill('450 W 33rd St');
    await page.locator('#pickup_city').fill('New York');
    await page.locator('#pickup_region').selectOption('NY');
    await page.locator('#pickup_postal').fill('10001');
    await page.getByLabel('Recipient name').fill('E2E Recipient');
    await page.getByLabel('Recipient phone').fill('(718) 555-0148');
    await page.locator('#delivery_country').selectOption('US');
    await page.locator('#delivery_address').fill('9 Example Ave');
    await page.locator('#delivery_city').fill('Brooklyn');
    await page.locator('#delivery_region').selectOption('NY');
    await page.locator('#delivery_postal').fill('11201');
    await page.getByLabel('What are you sending?').fill('Documents');
    await page.getByLabel('Category').selectOption('documents');
    await page.locator('#w0').fill('2.5');
    await page.getByLabel('Delivery service').selectOption({ index: 2 });
    await page.getByRole('button', { name: /See price/ }).click();

    await expect(page.getByRole('heading', { name: 'Review & confirm' })).toBeVisible();
    await page.getByLabel(/I confirm the details are correct/).check();
    await page.getByRole('button', { name: 'Confirm booking' }).click();

    await expect(page.getByRole('heading', { name: 'Complete payment' })).toBeVisible();
    const tracking = (await page.locator('span.font-mono').first().textContent())!.trim();
    await page.getByRole('button', { name: 'Pay securely' }).click();
    await expect(page.getByText('Sandbox payment — development only')).toBeVisible();
    await page.getByRole('button', { name: payOutcome === 'success' ? 'Simulate successful payment' : 'Simulate declined payment' }).click();
    return tracking;
}

/** Select the first <option> whose visible text contains `text`. */
export async function selectByText(page: Page, label: string, text: string) {
    const select = page.getByLabel(label);
    const value = await select.locator('option', { hasText: text }).first().getAttribute('value');
    await select.selectOption(value!);
}
