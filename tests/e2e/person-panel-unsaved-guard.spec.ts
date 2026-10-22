// Risk: "the UI sends an action to the wrong person" (test-plan.md:86) on the
// S-06 panel beside the tree. The tree stays clickable while a section of the
// panel is being edited: opening another card's panel must ask first, and `Zapisz`
// must save the edit to the person it was typed for before the clicked person
// opens (impl-review F7 of person-side-panel). Data is prepared through the
// app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createPerson, createSpouse } from './support/api';
import { gotoTree } from './support/navigation';

test('person-panel-unsaved-guard: opening another card with an unsaved section asks first and saves the edit to the person it was typed for', async ({
    page,
    suffix,
}) => {
    const centreName = `Dziecko ${suffix}`;
    const spouseName = `Żona ${suffix}`;

    const centreId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    const spouseId = await createSpouse(page, centreId, {
        first_name: 'Żona',
        last_name: suffix,
        gender: 'female',
    });

    await gotoTree(page, centreId);
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toHaveText(centreName);

    // 1. The centre's panel, with the personal data edited but not saved.
    await page.getByRole('link', { name: `Podgląd: ${centreName}` }).click();
    const centrePanel = page.getByRole('dialog', { name: centreName });
    await expect(centrePanel).toBeVisible();
    await centrePanel
        .getByRole('button', { name: 'Edytuj Dane osobowe' })
        .click();
    await centrePanel
        .getByRole('textbox', { name: 'Nazwisko rodowe' })
        .fill('Kowalska');

    // 2. The eye button of the spouse's card asks first; the panel stays on the centre.
    await page.getByRole('link', { name: `Podgląd: ${spouseName}` }).click();
    const unsaved = centrePanel.getByRole('alert');
    await expect(unsaved).toContainText('Masz niezapisane zmiany');
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('person') === String(centreId),
    );

    // 3. `Zapisz` saves the edit to the centre, then opens the spouse.
    await unsaved.getByRole('button', { name: 'Zapisz' }).click();
    const spousePanel = page.getByRole('dialog', { name: spouseName });
    await expect(spousePanel).toBeVisible();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('person') === String(spouseId),
    );
    await expect(heading).toHaveText('Dziecko Kowalska');
    await expect(
        spousePanel.getByRole('region', { name: 'Dane osobowe' }),
    ).not.toContainText('Kowalska');
});
