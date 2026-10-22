// Risk: the data typed into the "add a person" panel (`?add=`) is lost, or a
// person is created by mistake, when the user clicks a card or another empty
// slot on the tree beside the panel. The panel does not cover the tree, so
// every such click must ask first: `Porzuć` opens the clicked target without
// saving, `Zapisz` saves the new person and then opens it. Data is prepared
// through the app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createPerson, createSpouse, findPersons } from './support/api';
import { gotoTree, openSlot, slotDialog, treeSlot } from './support/navigation';

const DIRTY_ALERT = 'Masz niezapisane zmiany w nowej osobie';

test('tree-add-person-guard: a card click with an unsaved new person asks first; Porzuć opens the card and creates nobody', async ({
    page,
    suffix,
}) => {
    const centreName = `Jan ${suffix}`;
    const spouseName = `Anna ${suffix}`;
    const newFatherName = `Ojciec ${suffix}`;

    const centreId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
        gender: 'male',
    });
    const spouseId = await createSpouse(page, centreId, {
        first_name: 'Anna',
        last_name: suffix,
        gender: 'female',
    });

    // 1. A new father is typed in the father slot's panel, not saved.
    await gotoTree(page, centreId);
    const addFather = await openSlot(page, 'ojca', centreName);
    await addFather.getByRole('button', { name: 'Nowa osoba' }).click();
    await addFather.getByLabel('Imię').fill('Ojciec');

    // 2. The spouse's card on the tree asks first; the panel stays open.
    await page.getByRole('link', { name: `Podgląd: ${spouseName}` }).click();
    const unsaved = addFather.getByRole('alert');
    await expect(unsaved).toContainText(DIRTY_ALERT);
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('add') === `father:${centreId}`,
    );

    // 3. `Porzuć` drops the typed data and opens the clicked spouse.
    await unsaved.getByRole('button', { name: 'Porzuć' }).click();
    const spousePanel = page.getByRole('dialog', { name: spouseName });
    await expect(spousePanel).toBeVisible();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('person') === String(spouseId),
    );
    await expect(addFather).toBeHidden();

    // Nobody was created from the discarded form.
    const created = (await findPersons(page, suffix)).some(
        (person) => person.display_name === newFatherName,
    );
    expect(created).toBe(false);
});

test('tree-add-person-guard: Zapisz in the guard saves the new person, then opens the clicked card', async ({
    page,
    suffix,
}) => {
    const centreName = `Jan ${suffix}`;
    const spouseName = `Anna ${suffix}`;
    const newFatherName = `Ojciec ${suffix}`;

    const centreId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
        gender: 'male',
    });
    const spouseId = await createSpouse(page, centreId, {
        first_name: 'Anna',
        last_name: suffix,
        gender: 'female',
    });

    // 1. A new father is typed in the father slot's panel, not saved.
    await gotoTree(page, centreId);
    const addFather = await openSlot(page, 'ojca', centreName);
    await addFather.getByRole('button', { name: 'Nowa osoba' }).click();
    await addFather.getByLabel('Imię').fill('Ojciec');
    await addFather.getByLabel('Nazwisko rodowe', { exact: true }).fill(suffix);

    // 2. The spouse's card on the tree asks first.
    await page.getByRole('link', { name: `Podgląd: ${spouseName}` }).click();
    const unsaved = addFather.getByRole('alert');
    await expect(unsaved).toContainText(DIRTY_ALERT);

    // 3. `Zapisz` saves the father, then opens the clicked spouse.
    await unsaved.getByRole('button', { name: 'Zapisz' }).click();
    const spousePanel = page.getByRole('dialog', { name: spouseName });
    await expect(spousePanel).toBeVisible();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('person') === String(spouseId),
    );
    await expect(addFather).toBeHidden();

    // The father was saved before the spouse opened.
    const created = (await findPersons(page, suffix)).some(
        (person) => person.display_name === newFatherName,
    );
    expect(created).toBe(true);
});

test('tree-add-person-guard: another empty slot with an unsaved new person asks first; Porzuć opens that slot', async ({
    page,
    suffix,
}) => {
    const centreName = `Jan ${suffix}`;

    const centreId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
        gender: 'male',
    });

    // 1. A new father is typed in the father slot's panel, not saved.
    await gotoTree(page, centreId);
    const addFather = await openSlot(page, 'ojca', centreName);
    await addFather.getByRole('button', { name: 'Nowa osoba' }).click();
    await addFather.getByLabel('Imię').fill('Ojciec');

    // 2. The empty mother slot asks first; the panel stays on the father.
    await treeSlot(page, 'matkę', centreName).click();
    const unsaved = addFather.getByRole('alert');
    await expect(unsaved).toContainText(DIRTY_ALERT);
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('add') === `father:${centreId}`,
    );

    // 3. `Porzuć` opens the mother slot's panel.
    await unsaved.getByRole('button', { name: 'Porzuć' }).click();
    const addMother = slotDialog(page, 'matkę', centreName);
    await expect(addMother).toBeVisible();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('add') === `mother:${centreId}`,
    );
    await expect(addFather).toBeHidden();
});
