// Risk: S-10 sources-grid-with-filters — the filter pills of the /sources bar
// write the address and the grid follows them: the place pill (a locality, which
// takes the acts of its parish) and the years pill narrow the cards, the
// address rebuilds the same pills and cards after a reload, and "Wyczyść
// filtry" brings every card back. Vitest sees the filter rules; only the
// browser sees the pills, the popovers, the address and the grid together.
// Data through support/api.ts; every record name carries the test suffix, so
// the fixture's cleanup sweep removes the sources, the parish and the locality.
import { expect, test } from './support/fixtures';
import { createLocality, createParish, createSource } from './support/api';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';

/** Escapes a name for use inside a RegExp, so a name with metacharacters matches literally. */
function escapeRegExp(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

test('sources-grid-with-filters: place and year range narrow the grid, survive a reload and clear together', async ({
    page,
    suffix,
}) => {
    const localityName = `Miejscowość ${suffix}`;
    const inRangeName = `Akt 1892 ${suffix}`;
    const outOfRangeName = `Akt 1900 ${suffix}`;
    const withoutParishName = `Akt bez parafii ${suffix}`;

    // 1. Setup through the API: a seat locality, a parish seated there, three sources.
    const localityId = await createLocality(page, localityName);
    const parishId = await createParish(page, { localityId });
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

    // 3. Place pill → the locality option: the address takes it, the grid keeps only the parish's acts.
    await page.getByRole('button', { name: 'Miejsce', exact: true }).click();
    await page
        .getByRole('option', {
            // The list now also offers the parish seated there, labelled "<seat> (rzym.-kat.)"; the locality option is the bare name.
            name: new RegExp('^' + escapeRegExp(localityName) + '\\s*,'),
        })
        .click();
    await expect(page).toHaveURL(/[?&]place=miejscowosc-\d+/);
    await expect(
        page.getByRole('link', { name: withoutParishName }),
    ).toBeHidden();

    // 4. Years pill → the dialog "Lata": range 1890–1895 (inclusive), only the 1892 act of the parish remains.
    await page.getByRole('button', { name: 'Lata', exact: true }).click();
    const yearsDialog = page.getByRole('dialog', { name: 'Lata' });
    await yearsDialog.getByLabel('Rok od').fill('1890');
    await yearsDialog.getByLabel('Rok do').fill('1895');
    await expect(page).toHaveURL(/[?&]year_from=1890/);
    await expect(page).toHaveURL(/[?&]year_to=1895/);
    await expect(page.getByRole('link', { name: inRangeName })).toBeVisible();
    await expect(page.getByRole('link', { name: outOfRangeName })).toBeHidden();
    await expect(
        page.getByRole('status').filter({ hasText: /^1 z \d+ źród(ła|eł)$/ }),
    ).toBeVisible();

    // 5. Reload: the address rebuilds the pills and the same single card.
    await page.reload();
    await expect(
        page.getByRole('button', { name: `Miejsce: ${localityName}` }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Lata: 1890 – 1895' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Lata: 1890 – 1895' }).click();
    await expect(
        page.getByRole('dialog', { name: 'Lata' }).getByLabel('Rok od'),
    ).toHaveValue('1890');
    await expect(
        page.getByRole('dialog', { name: 'Lata' }).getByLabel('Rok do'),
    ).toHaveValue('1895');
    await expect(page.getByRole('link', { name: inRangeName })).toBeVisible();
    await expect(page.getByRole('link', { name: outOfRangeName })).toBeHidden();
    await expect(
        page.getByRole('link', { name: withoutParishName }),
    ).toBeHidden();
    await expect(
        page.getByRole('status').filter({ hasText: /^1 z \d+ źród(ła|eł)$/ }),
    ).toBeVisible();

    // 6. "Wyczyść filtry": the pills are back to "Miejsce" and "Lata", and all three cards are back.
    await page.getByRole('button', { name: 'Wyczyść filtry' }).click();
    await expect(page).not.toHaveURL(/[?&](place|year_from|year_to)=/);
    await expect(
        page.getByRole('button', { name: 'Miejsce', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Lata', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: inRangeName })).toBeVisible();
    await expect(
        page.getByRole('link', { name: outOfRangeName }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: withoutParishName }),
    ).toBeVisible();
});
