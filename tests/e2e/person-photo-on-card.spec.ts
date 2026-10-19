// Risk: "a photo added in the side panel shows on the tree card; after removing
// it, the card falls back to the generic portrait" (context/changes/person-photo-on-card,
// phase 4). Pest does not see this: it joins the upload, the version in the photo
// URL, the authorised serving and the card render.
// Data is prepared through the app's routes (tests/e2e/support/api.ts); the photo
// goes through the panel's menu, as a user does.
import { expect, test } from './support/fixtures';
import { createParent, createPerson } from './support/api';
import { gotoTree } from './support/navigation';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';

test('person-photo-on-card: a photo added in the panel shows on the tree card, and removing it brings back the generic portrait', async ({
    page,
    suffix,
}) => {
    const parentName = `Ojciec ${suffix}`;

    const childId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    await createParent(
        page,
        childId,
        { first_name: 'Ojciec', last_name: suffix, gender: 'male' },
        'father',
    );

    await gotoTree(page, childId);

    // The parent's card is a link named by its gender and name; the eye button
    // opens the panel of that person beside the tree.
    const card = page.getByRole('link', {
        name: `mężczyzna ${parentName}`,
        exact: true,
    });
    await page.getByRole('link', { name: `Podgląd: ${parentName}` }).click();
    const parentPanel = page.getByRole('dialog', { name: parentName });
    await expect(parentPanel).toBeVisible();

    // Without a photo the card shows the generic portrait.
    await expect(card.getByTestId('card-avatar-placeholder')).toBeVisible();

    // 1. Add the photo from the panel's photo menu. The menu item opens the
    // file chooser of the hidden file input.
    await parentPanel.getByRole('button', { name: 'Zdjęcie osoby' }).click();
    const fileChooser = page.waitForEvent('filechooser');
    await page.getByRole('menuitem', { name: 'Dodaj zdjęcie' }).click();
    await (await fileChooser).setFiles(FIXTURE);

    // The upload is done once the menu offers to replace the photo.
    await parentPanel.getByRole('button', { name: 'Zdjęcie osoby' }).click();
    await expect(
        page.getByRole('menuitem', { name: 'Zmień zdjęcie' }),
    ).toBeVisible();
    await page.keyboard.press('Escape');

    // 2. The card shows the photo, loaded, and no longer the generic portrait.
    // The photo's <img> has alt="" (decorative), so it has no role and no name:
    // it is found by its empty alt text inside the card, not by a test id.
    const cardPhoto = card.getByAltText('', { exact: true });
    await expect(cardPhoto).toBeVisible();
    await expect
        .poll(() =>
            cardPhoto.evaluate((image: HTMLImageElement) => image.naturalWidth),
        )
        .toBeGreaterThan(0);
    await expect(card.getByTestId('card-avatar-placeholder')).toBeHidden();

    // 3. Remove the photo: the confirm dialog, then the card falls back to the generic portrait.
    await parentPanel.getByRole('button', { name: 'Zdjęcie osoby' }).click();
    await page.getByRole('menuitem', { name: 'Usuń zdjęcie' }).click();
    const confirm = page.getByRole('dialog', {
        name: 'Usunąć zdjęcie osoby?',
    });
    await expect(confirm).toBeVisible();
    await confirm.getByRole('button', { name: 'Usuń', exact: true }).click();
    await expect(confirm).toBeHidden();

    await expect(cardPhoto).toBeHidden();
    await expect(card.getByTestId('card-avatar-placeholder')).toBeVisible();

    // No cleanup here: the `suffix` fixture deletes the person and its records
    // through the API after the test, also after a red assertion.
});
