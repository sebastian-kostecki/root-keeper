// Risk: S-10 sources-grid-with-filters — the filter fields of the /sources bar
// write the address and the grid follows them: the place (a locality, which
// takes the acts of its parish) and the year range narrow the cards, the
// address rebuilds the same fields and cards after a reload, and "Wyczyść
// filtry" brings every card back. Vitest sees the filter rules; only the
// browser sees the fields, the address and the grid together. Data through
// support/api.ts; every record name carries the test suffix, so the fixture's
// cleanup sweep removes the sources, the parish and the locality.
import { expect, test } from './support/fixtures';
import { createLocality, createParish, createSource } from './support/api';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';

test('sources-grid-with-filters: place and year range narrow the grid, survive a reload and clear together', async ({
    page,
    suffix,
}) => {
    const localityName = `Miejscowość ${suffix}`;
    const parishName = `Parafia ${suffix}`;
    const inRangeName = `Akt 1892 ${suffix}`;
    const outOfRangeName = `Akt 1900 ${suffix}`;
    const withoutParishName = `Akt bez parafii ${suffix}`;

    // 1. Setup through the API: a seat locality, a parish seated there, three sources.
    const localityId = await createLocality(page, localityName);
    const parishId = await createParish(page, {
        name: parishName,
        localityId,
    });
    await createSource(page, {
        kind: 'metrical_record',
        name: inRangeName,
        fixture: FIXTURE,
        year: 1892,
        parish_id: parishId,
    });
    await createSource(page, {
        kind: 'metrical_record',
        name: outOfRangeName,
        fixture: FIXTURE,
        year: 1900,
        parish_id: parishId,
    });
    await createSource(page, {
        kind: 'metrical_record',
        name: withoutParishName,
        fixture: FIXTURE,
        year: 1892,
    });

    // 2. Before any filter all three cards are in the grid.
    await page.goto('/sources');
    await expect(page.getByRole('link', { name: inRangeName })).toBeVisible();
    await expect(
        page.getByRole('link', { name: outOfRangeName }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: withoutParishName }),
    ).toBeVisible();

    // 3. Place = the locality: the address takes it, the grid keeps only the parish's acts.
    await page.getByLabel('Parafia / miejsce').click();
    await page.getByRole('option', { name: localityName, exact: true }).click();
    await expect(page).toHaveURL(/[?&]place=miejscowosc-\d+/);
    await expect(
        page.getByRole('link', { name: withoutParishName }),
    ).toBeHidden();

    // 4. Year range 1890–1895 (inclusive): only the 1892 act of the parish remains.
    await page.getByLabel('Rok od').fill('1890');
    await page.getByLabel('Rok do').fill('1895');
    await expect(page).toHaveURL(/[?&]year_from=1890/);
    await expect(page).toHaveURL(/[?&]year_to=1895/);
    await expect(page.getByRole('link', { name: inRangeName })).toBeVisible();
    await expect(page.getByRole('link', { name: outOfRangeName })).toBeHidden();
    await expect(page.getByRole('status')).toHaveText('Znaleziono: 1 źródło');

    // 5. Reload: the address rebuilds the fields and the same single card.
    await page.reload();
    await expect(page.getByLabel('Parafia / miejsce')).toHaveText(localityName);
    await expect(page.getByLabel('Rok od')).toHaveValue('1890');
    await expect(page.getByLabel('Rok do')).toHaveValue('1895');
    await expect(page.getByRole('link', { name: inRangeName })).toBeVisible();
    await expect(page.getByRole('link', { name: outOfRangeName })).toBeHidden();
    await expect(
        page.getByRole('link', { name: withoutParishName }),
    ).toBeHidden();
    await expect(page.getByRole('status')).toHaveText('Znaleziono: 1 źródło');

    // 6. "Wyczyść filtry": the fields are empty again and all three cards are back.
    await page.getByRole('button', { name: 'Wyczyść filtry' }).click();
    await expect(page).not.toHaveURL(/[?&](place|year_from|year_to)=/);
    await expect(page.getByLabel('Rok od')).toHaveValue('');
    await expect(page.getByLabel('Rok do')).toHaveValue('');
    await expect(page.getByLabel('Parafia / miejsce')).toHaveText('Wszystkie');
    await expect(page.getByRole('link', { name: inRangeName })).toBeVisible();
    await expect(
        page.getByRole('link', { name: outOfRangeName }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: withoutParishName }),
    ).toBeVisible();
});
