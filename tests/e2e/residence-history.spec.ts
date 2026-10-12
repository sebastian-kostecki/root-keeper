// Risk: S-08 residence-history — a person has several residences from the
// register with optional years: two rows added in the "Zamieszkania" card (one
// locality picked, one added in place) are saved in order of the year, and
// saving another section (Dane osobowe) replaces the list with itself instead of
// clearing it. Only the browser sees the multi-row editor, the pickers sharing
// one list and the card saving together with the other sections. Data through
// the app's routes (tests/e2e/support/api.ts). Localities have no delete route
// until S-11, so they stay in the `e2e` database (unique per run by the suffix)
// — see context/foundation/test-stack.md. People go with the suffix sweep.
import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { createLocality, createPerson, personPreview } from './support/api';
import { openPerson } from './support/navigation';

/**
 * Saves the section, then takes the pointer off the spot of the button: the
 * "Zapisano zmiany" toast appears over the panel's lower edge, and a toast the
 * pointer rests on does not time out, so it would cover the next section's button.
 */
async function saveSection(page: Page, section: Locator): Promise<void> {
    await section.getByRole('button', { name: 'Zapisz' }).click();
    await page.mouse.move(0, 0);
}

test('residence-history: two residences saved in order of the year survive saving another section', async ({
    page,
    suffix,
}) => {
    const registered = `Wadowice ${suffix}`;
    const added = `Skawina ${suffix}`;

    // Setup: a person without residences and a locality already in the register.
    const personId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
    });
    await createLocality(page, registered);
    const displayName = (await personPreview(page, personId)).person
        .display_name;
    const panel = await openPerson(page, personId, displayName);

    // 1. Open the empty card and fill the first row: the registered locality, the later year.
    const card = panel.getByRole('region', {
        name: 'Zamieszkania',
        exact: true,
    });
    await panel.getByRole('button', { name: 'Dodaj zamieszkanie' }).click();
    await card.getByRole('button', { name: 'Dodaj zamieszkanie' }).click();
    const firstPlace = card.getByRole('combobox', { name: 'Miejscowość' });
    await firstPlace.fill(registered);
    await page.getByRole('option', { name: registered }).click();
    await expect(firstPlace).toHaveValue(registered);
    await card.getByRole('spinbutton', { name: 'Od roku' }).fill('1950');

    // 2. Second row: a name not in the register is added in place, with the earlier year.
    await card.getByRole('button', { name: 'Dodaj zamieszkanie' }).click();
    const secondPlace = card
        .getByRole('combobox', { name: 'Miejscowość' })
        .last();
    await secondPlace.fill(added);
    await page
        .getByRole('option', { name: `Dodaj miejscowość „${added}”` })
        .click();
    await expect(secondPlace).toHaveValue(added);
    await card.getByRole('spinbutton', { name: 'Od roku' }).last().fill('1920');
    await saveSection(page, card);

    // The view lists both, the earlier year first although it was added second.
    const items = card.getByRole('listitem');
    await expect(items).toHaveCount(2);
    await expect(items.nth(0)).toContainText(added);
    await expect(items.nth(0)).toContainText('od 1920');
    await expect(items.nth(1)).toContainText(registered);
    await expect(items.nth(1)).toContainText('od 1950');

    // 3. Saving another section must not clear or reorder the list.
    const personal = panel.getByRole('region', {
        name: 'Dane osobowe',
        exact: true,
    });
    await panel.getByRole('button', { name: 'Edytuj Dane osobowe' }).click();
    await personal
        .getByRole('textbox', { name: 'Nazwisko rodowe' })
        .fill('Kowalska');
    await saveSection(page, personal);
    await expect(personal.getByText('Kowalska')).toBeVisible();

    await expect(card.getByRole('listitem')).toHaveCount(2);
    const reloaded = await openPerson(page, personId, displayName);
    const reloadedItems = reloaded
        .getByRole('region', { name: 'Zamieszkania', exact: true })
        .getByRole('listitem');
    await expect(reloadedItems).toHaveCount(2);
    await expect(reloadedItems.nth(0)).toContainText(added);
    await expect(reloadedItems.nth(1)).toContainText(registered);
});
