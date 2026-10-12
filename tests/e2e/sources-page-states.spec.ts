// Risks on `/sources` (the filter bar and its states): (a) a reversed or
// malformed year gives an empty or unfiltered list without an explanation;
// (b) a failed filter visit looks like nothing happened; (c) on a phone the
// filters are out of reach or the "Filtry (N)" count lies. Data is prepared
// through the app's routes (tests/e2e/support/api.ts) and every record carries
// the test's unique suffix, which the fixture cleans up.
import { expect, test } from './support/fixtures';
import { createSource } from './support/api';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';

const YEAR_ORDER_MESSAGE = '„Rok od” nie może być późniejszy niż „Rok do”.';
const VISIT_ERROR = 'Nie udało się zastosować filtrów.';

test('sources-page-states: a reversed year range explains itself beside both fields and clears when fixed', async ({
    page,
    suffix,
}) => {
    await createSource(page, {
        kind: 'metrical_record',
        name: `Akt starszy ${suffix}`,
        fixture: FIXTURE,
        year: 1880,
    });
    await createSource(page, {
        kind: 'metrical_record',
        name: `Akt młodszy ${suffix}`,
        fixture: FIXTURE,
        year: 1920,
    });

    await page.goto('/sources');
    const yearFrom = page.getByLabel('Rok od');
    const yearTo = page.getByLabel('Rok do');
    await expect(yearFrom).toBeVisible();

    // 1. A reversed range (1900 > 1850) is named by the message, both fields are marked, the values stay.
    await yearFrom.fill('1900');
    await yearTo.fill('1850');
    await expect(page.getByText(YEAR_ORDER_MESSAGE)).toBeVisible();
    await expect(yearFrom).toHaveAttribute('aria-invalid', 'true');
    await expect(yearTo).toHaveAttribute('aria-invalid', 'true');
    await expect(yearFrom).toHaveValue('1900');
    await expect(yearTo).toHaveValue('1850');

    // 2. Fixing the "Rok do" field removes the message and the marks.
    await yearTo.fill('1950');
    await expect(page.getByText(YEAR_ORDER_MESSAGE)).toBeHidden();
    await expect(yearFrom).toHaveAttribute('aria-invalid', 'false');
    await expect(yearTo).toHaveAttribute('aria-invalid', 'false');
});

test('sources-page-states: a failed kind visit shows the error and retries to the chosen kind', async ({
    page,
    suffix,
}) => {
    await createSource(page, {
        kind: 'metrical_record',
        name: `Akt wizyty ${suffix}`,
        fixture: FIXTURE,
        year: 1892,
    });

    await page.goto('/sources');
    const aktyKind = page.getByRole('radio', { name: /^Akty/ });
    await expect(aktyKind).toBeEnabled();

    // 1. The kind visit is cut: the page stays, the error says so.
    let cutOff = true;
    await page.route(
        (url) => url.pathname === '/sources' && url.searchParams.has('kind'),
        (route) => {
            if (cutOff && route.request().headers()['x-inertia']) {
                return route.abort();
            }

            return route.continue();
        },
    );

    await aktyKind.click();
    await expect(page.getByRole('alert')).toContainText(VISIT_ERROR);

    // 2. The network comes back: "Spróbuj ponownie" runs the visit again and applies the kind.
    cutOff = false;
    await page.getByRole('button', { name: 'Spróbuj ponownie' }).click();
    await page.waitForURL(/[?&]kind=akty/);
    await expect(page.getByRole('alert')).toBeHidden();
    await expect(aktyKind).toBeChecked();
});

test.describe('phone width', () => {
    test.use({ viewport: { width: 375, height: 812 } });

    test('sources-page-states: on a phone the year fields sit behind "Filtry" and the count follows the applied year', async ({
        page,
        suffix,
    }) => {
        await createSource(page, {
            kind: 'metrical_record',
            name: `Akt telefonu ${suffix}`,
            fixture: FIXTURE,
            year: 1892,
        });

        await page.goto('/sources');
        await expect(page.getByRole('searchbox')).toBeVisible();

        // 1. Collapsed by default: the year field is out of reach.
        await expect(page.getByLabel('Rok od')).toBeHidden();

        // 2. "Filtry" opens the block with the year fields.
        await page.getByRole('button', { name: 'Filtry', exact: true }).click();
        await expect(page.getByLabel('Rok od')).toBeVisible();

        // 3. A typed year is counted on the trigger once the visit applies it.
        await page.getByLabel('Rok od').fill('1890');
        await expect(
            page.getByRole('button', { name: 'Filtry (1)', exact: true }),
        ).toBeVisible();
    });
});
