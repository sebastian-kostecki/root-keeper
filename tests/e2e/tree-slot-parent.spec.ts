// Risk: FR-026 tree-slot-parent — a parent added from an empty slot of the tree,
// an existing person or a new one, becomes the parent of the person under the
// slot, and that person becomes the parent's child. Guarded before the
// S-04/S-05/S-06 rebuilds (prd.md:209, roadmap.md F-01). Data is prepared
// through the app's routes (tests/e2e/support/api.ts); only the protected path
// runs in the UI, and the relation is read back from both sides.
import { expect, test } from './support/fixtures';
import { createPerson, findPersons } from './support/api';
import {
    familySection,
    gotoTree,
    openPerson,
    openSlot,
    treeSlot,
} from './support/navigation';

test('tree-slot-parent: a parent added from an empty slot is linked from both sides', async ({
    page,
    suffix,
}) => {
    const childName = `Dziecko ${suffix}`;
    const fatherName = `Ojciec ${suffix}`;
    const motherName = `Matka ${suffix}`;

    // Setup through the app's routes: a child and a candidate for the father.
    const childId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    const fatherId = await createPerson(page, {
        first_name: 'Ojciec',
        last_name: suffix,
        gender: 'male',
    });

    // 1. An existing person fills the father slot.
    await gotoTree(page, childId);
    const pickFather = await openSlot(page, 'ojca', childName);
    await pickFather
        .getByRole('searchbox', { name: 'Szukaj osoby' })
        .fill(suffix);
    await pickFather
        .getByRole('button', { name: new RegExp(`^${fatherName}`) })
        .click();
    // The dialog closes on success and the filled slot is gone. (Not the
    // toast: flash data lives in the one session all parallel tests share.)
    await expect(pickFather).toBeHidden();
    await expect(treeSlot(page, 'ojca', childName)).toHaveCount(0);

    // 2. A new person fills the mother slot.
    const addMother = await openSlot(page, 'matkę', childName);
    await addMother.getByRole('button', { name: 'Nowa osoba' }).click();
    await addMother.getByLabel('Imię').fill('Matka');
    await addMother.getByLabel('Nazwisko', { exact: true }).fill(suffix);
    await addMother.getByRole('radio', { name: 'Kobieta' }).click();
    await addMother.getByRole('button', { name: 'Zapisz' }).click();
    await expect(addMother).toBeHidden();
    await expect(treeSlot(page, 'matkę', childName)).toHaveCount(0);

    const mother = (await findPersons(page, motherName)).find(
        (person) => person.display_name === motherName,
    );
    if (!mother) {
        throw new Error(`The new mother ${motherName} was not created`);
    }

    // The child lists both parents.
    const child = await openPerson(page, childId, childName);
    const parents = await familySection(child, 'Rodzice');
    await expect(parents.getByText(fatherName, { exact: true })).toBeVisible();
    await expect(parents.getByText(motherName, { exact: true })).toBeVisible();

    // Each parent lists the child.
    for (const [id, name] of [
        [fatherId, fatherName],
        [mother.id, motherName],
    ] as const) {
        const parent = await openPerson(page, id, name);
        const children = await familySection(parent, 'Dzieci');
        await expect(
            children.getByText(childName, { exact: true }),
        ).toBeVisible();
    }
});
