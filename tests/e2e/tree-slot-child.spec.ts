// Risk: FR-026 tree-slot-child — a child added from the empty children slot
// after switching to the second union has the centre person and the partner of
// *that* union as parents, not the partner of the first one; an existing person
// and a new one alike. Guarded before the S-04/S-05/S-06 rebuilds (prd.md:209,
// roadmap.md F-01). Data is prepared through the app's routes
// (tests/e2e/support/api.ts); only the protected path runs in the UI, and the
// relation is read back from both sides.
import { expect, test } from './support/fixtures';
import { createPerson, createSpouse, findPersons } from './support/api';
import {
    familySection,
    gotoTree,
    openPerson,
    slotDialog,
    switchUnion,
    treeSlot,
} from './support/navigation';

test("tree-slot-child: a child added in the second union has that union's partner as parent", async ({
    page,
    suffix,
}) => {
    const centreName = `Jan ${suffix}`;
    const firstSpouseName = `Anna ${suffix}`;
    const secondSpouseName = `Maria ${suffix}`;
    const candidateName = `Kandydat ${suffix}`;
    const newChildName = `Nowe ${suffix}`;
    const secondPair = `${centreName} i ${secondSpouseName}`;

    // Setup through the app's routes: a person with two spouses and a
    // candidate for the child.
    const centreId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
        gender: 'male',
    });
    await createSpouse(page, centreId, {
        first_name: 'Anna',
        last_name: suffix,
        gender: 'female',
    });
    const secondSpouseId = await createSpouse(page, centreId, {
        first_name: 'Maria',
        last_name: suffix,
        gender: 'female',
    });
    const candidateId = await createPerson(page, {
        first_name: 'Kandydat',
        last_name: suffix,
    });

    // 1. Switch to the second union and wait until the tree shows it: the
    // child slot is named after the pair, so it is the readiness signal
    // (not only the URL).
    await gotoTree(page, centreId);
    await switchUnion(page, secondSpouseName);
    const childSlot = treeSlot(page, 'dziecko', secondPair);
    await expect(childSlot).toBeVisible();

    // 2. An existing person becomes the child of that pair.
    await childSlot.click();
    const pickChild = slotDialog(page, 'dziecko', secondPair);
    await pickChild
        .getByRole('searchbox', { name: 'Szukaj osoby' })
        .fill(candidateName);
    await pickChild
        .getByRole('button', { name: new RegExp(`^${candidateName}`) })
        .click();
    await expect(pickChild).toBeHidden();

    // 3. A new person through the same slot.
    await expect(childSlot).toBeVisible();
    await childSlot.click();
    const addChild = slotDialog(page, 'dziecko', secondPair);
    await addChild.getByRole('button', { name: 'Nowa osoba' }).click();
    await addChild.getByLabel('Imię').fill('Nowe');
    await addChild.getByLabel('Nazwisko', { exact: true }).fill(suffix);
    await addChild.getByRole('button', { name: 'Zapisz' }).click();
    await expect(addChild).toBeHidden();

    const newChild = (await findPersons(page, newChildName)).find(
        (person) => person.display_name === newChildName,
    );
    if (!newChild) {
        throw new Error(`The new child ${newChildName} was not created`);
    }

    // Both children list the centre person and the second spouse, and not the first.
    for (const [id, name] of [
        [candidateId, candidateName],
        [newChild.id, newChildName],
    ] as const) {
        const child = await openPerson(page, id, name);
        const parents = await familySection(child, 'Rodzice');
        await expect(
            parents.getByText(centreName, { exact: true }),
        ).toBeVisible();
        await expect(
            parents.getByText(secondSpouseName, { exact: true }),
        ).toBeVisible();
        await expect(
            parents.getByText(firstSpouseName, { exact: true }),
        ).toHaveCount(0);
    }

    // The second spouse lists both children.
    const secondSpouse = await openPerson(
        page,
        secondSpouseId,
        secondSpouseName,
    );
    const children = await familySection(secondSpouse, 'Dzieci');
    await expect(
        children.getByText(candidateName, { exact: true }),
    ).toBeVisible();
    await expect(
        children.getByText(newChildName, { exact: true }),
    ).toBeVisible();
});
