// Risk: places-merge-and-delete — merging a duplicate locality from its panel
// must move its references to the kept locality and leave the duplicate off the
// register: the dialog's direction decides which place the panel opens on after
// the merge, and a fresh read of a parish shows the kept place as its seat. Pest
// sees the transaction; only the browser sees the dialog, the redirect and the
// register together. Data through the app's routes; both names carry the suffix,
// so the sweep removes the parish and the localities (see support/api.ts).
import { expect, test } from './support/fixtures';
import { createLocality, createParish } from './support/api';

test('places-merge-and-delete: a duplicate merged from its panel opens the kept locality and moves the parish seat to it', async ({
    page,
    suffix,
}) => {
    const duplicateName = `Lubomla ${suffix}`;
    const keptName = `Luboml ${suffix}`;
    const parishName = `Parafia ${suffix}`;

    // 1. Setup: a duplicate that seats a parish, and the locality to keep.
    const duplicateId = await createLocality(page, duplicateName);
    const keptId = await createLocality(page, keptName);
    const parishId = await createParish(page, {
        name: parishName,
        localityId: duplicateId,
    });

    // 2. Open the duplicate's panel from the register and choose the kept place.
    await page.goto(`/places?locality=${duplicateId}`);
    const panel = page.getByRole('dialog', {
        name: duplicateName,
        exact: true,
    });
    await expect(panel).toBeVisible();
    await panel.getByRole('button', { name: 'Scal z…' }).click();

    const merge = page.getByRole('dialog', {
        name: `Scal „${duplicateName}” z…`,
    });
    await expect(merge).toBeVisible();
    await merge
        .getByRole('combobox', { name: 'Właściwa miejscowość' })
        .fill(keptName);
    await page.getByRole('option', { name: keptName, exact: true }).click();
    await merge
        .getByRole('button', { name: 'Scal miejscowości', exact: true })
        .click();

    // 3. The risk: the panel that opens is the kept locality's, and the
    // duplicate has left the register.
    await expect(page).toHaveURL(new RegExp(`locality=${keptId}$`));
    await expect(
        page.getByRole('dialog', { name: keptName, exact: true }),
    ).toBeVisible();
    await expect(merge).toBeHidden();
    await expect(
        page.getByRole('link', { name: keptName, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: duplicateName, exact: true }),
    ).toBeHidden();

    // 4. Oracle on data: a fresh visit to the parish shows the kept place as its seat.
    await page.goto(`/places?kind=parafie&parish=${parishId}`);
    const parish = page.getByRole('dialog', { name: parishName, exact: true });
    await expect(parish).toBeVisible();
    await expect(
        parish.getByRole('combobox', { name: 'Miejscowość siedziby' }),
    ).toHaveValue(keptName);
});
