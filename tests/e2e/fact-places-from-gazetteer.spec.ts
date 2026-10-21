// Risk: S-07 fact-places-from-gazetteer — a fact's place is a locality of the
// tree's register, not free text: a new locality added in place from the birth
// field becomes the birth place, the burial place (a cemetery of the register,
// "cmentarz {locality}") chosen in the Zgon section is kept, and saving another section (Dane osobowe) does not lose either of them.
// Only the browser sees the picker's "Dodaj miejscowość" option, the hidden
// locality id of a section form and the sections saving one after another. Data
// through the app's routes (tests/e2e/support/api.ts). Localities have no delete
// route until S-11, so they stay in the `e2e` database (unique per run by the
// suffix) — see context/foundation/test-stack.md. People go with the suffix sweep.
import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import {
    createCemetery,
    createLocality,
    createPerson,
    personPreview,
} from './support/api';
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

test('fact-places-from-gazetteer: a locality added at birth and a burial cemetery chosen at death survive saving another section', async ({
    page,
    suffix,
}) => {
    const birthPlace = `Zawichost ${suffix}`;
    const burialLocality = `Sandomierz ${suffix}`;
    const burialPlace = `cmentarz ${burialLocality}`;

    // Setup: a person without a birth place, and a cemetery already in the register.
    const personId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
    });
    await createCemetery(page, {
        locality: await createLocality(page, burialLocality),
    });
    const displayName = (await personPreview(page, personId)).person
        .display_name;
    const panel = await openPerson(page, personId, displayName);

    // 1. Birth: a name that is not in the register offers adding it; the new
    // locality becomes the birth place.
    const birth = panel.getByRole('region', { name: 'Urodzenie', exact: true });
    await panel.getByRole('button', { name: 'Uzupełnij urodzenie' }).click();
    const birthPlaceField = birth.getByRole('combobox', { name: 'Miejsce' });
    await birthPlaceField.fill(birthPlace);
    await page
        .getByRole('option', { name: `Dodaj miejscowość „${birthPlace}”` })
        .click();
    await expect(birthPlaceField).toHaveValue(birthPlace);
    await saveSection(page, birth);
    await expect(birth.getByText(birthPlace)).toBeVisible();

    // 2. Zgon: mark the death and choose the burial cemetery from the register.
    const death = panel.getByRole('region', { name: 'Zgon', exact: true });
    await panel.getByRole('button', { name: 'Oznacz zgon' }).click();
    const burialField = death.getByRole('combobox', {
        name: 'Miejsce pochowania',
    });
    await burialField.fill(burialLocality);
    await page.getByRole('option', { name: burialPlace, exact: true }).click();
    await expect(burialField).toHaveValue(burialPlace);
    await saveSection(page, death);
    await expect(death.getByText('Miejsce pochowania')).toBeVisible();
    await expect(death.getByText(burialPlace)).toBeVisible();

    // 3. Saving another section must not lose the places of the facts.
    const personal = panel.getByRole('region', {
        name: 'Dane osobowe',
        exact: true,
    });
    await panel.getByRole('button', { name: 'Edytuj Dane osobowe' }).click();
    await personal
        .getByRole('textbox', { name: 'Nazwisko rodowe' })
        .fill(`${suffix}x`);
    await saveSection(page, personal);
    await expect(personal.getByText(`${suffix}x`)).toBeVisible();

    // The burial place and the birth place are still there, in this panel and
    // after a fresh load of the page.
    await expect(death.getByText(burialPlace)).toBeVisible();
    await expect(birth.getByText(birthPlace)).toBeVisible();
    const reloaded = await openPerson(page, personId, displayName);
    await expect(reloaded.getByText(burialPlace)).toBeVisible();
    await expect(reloaded.getByText(birthPlace)).toBeVisible();
});
