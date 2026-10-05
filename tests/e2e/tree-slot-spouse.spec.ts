// Risk: FR-026 tree-slot-spouse — a spouse added from the empty partner slot
// (a new person) and from the union node's menu (an existing person) is married
// to the centre person, and the centre person is listed as spouse by each of
// them. Guarded before the S-04/S-05/S-06 rebuilds (prd.md:209, roadmap.md
// F-01). Data is prepared through the app's routes (tests/e2e/support/api.ts);
// only the protected path runs in the UI, and the relation is read back from
// both sides.
import { expect, test } from '@playwright/test';
import {
    cleanupBySuffix,
    createPerson,
    findPersons,
    uniqueSuffix,
} from './support/api';
import { familySection, gotoTree, openPerson } from './support/navigation';

test('tree-slot-spouse: spouses added from the slot and the union menu are married from both sides', async ({
    page,
}, testInfo) => {
    const suffix = uniqueSuffix(testInfo);
    const centreName = `Jan ${suffix}`;
    const newSpouseName = `Nowa ${suffix}`;
    const candidateName = `Kandydatka ${suffix}`;

    try {
        // Setup through the app's routes: a person without unions and a candidate.
        const centreId = await createPerson(page, {
            first_name: 'Jan',
            last_name: suffix,
            gender: 'male',
        });
        const candidateId = await createPerson(page, {
            first_name: 'Kandydatka',
            last_name: suffix,
            gender: 'female',
        });

        // 1. A new person fills the empty partner slot.
        await gotoTree(page, centreId);
        await page
            .getByRole('button', { name: `Dodaj małżonka: ${centreName}` })
            .click();
        const addSpouse = page.getByRole('dialog', {
            name: `Dodaj małżonka — ${centreName}`,
        });
        await addSpouse.getByRole('button', { name: 'Nowa osoba' }).click();
        await addSpouse.getByLabel('Imię').fill('Nowa');
        await addSpouse.getByLabel('Nazwisko').fill(suffix);
        await addSpouse.getByRole('button', { name: 'Zapisz' }).click();
        await expect(addSpouse).toBeHidden();

        // 2. An existing person through the union node's menu.
        await page.getByRole('button', { name: /^Związek \d z \d/ }).click();
        await page.getByRole('menuitem', { name: 'Dodaj małżonka' }).click();
        const pickSpouse = page.getByRole('dialog', {
            name: `Dodaj małżonka — ${centreName}`,
        });
        await pickSpouse
            .getByRole('searchbox', { name: 'Szukaj osoby' })
            .fill(candidateName);
        await pickSpouse
            .getByRole('button', { name: new RegExp(`^${candidateName}`) })
            .click();
        await expect(pickSpouse).toBeHidden();

        const newSpouse = (await findPersons(page, 'Nowa')).find(
            (person) => person.display_name === newSpouseName,
        );
        if (!newSpouse) {
            throw new Error(`The new spouse ${newSpouseName} was not created`);
        }

        // The centre person lists both spouses.
        const centre = await openPerson(page, centreId, centreName);
        const marriages = await familySection(centre, 'Małżeństwa');
        await expect(
            marriages.getByText(newSpouseName, { exact: true }),
        ).toBeVisible();
        await expect(
            marriages.getByText(candidateName, { exact: true }),
        ).toBeVisible();

        // Each spouse lists the centre person.
        for (const [id, name] of [
            [newSpouse.id, newSpouseName],
            [candidateId, candidateName],
        ] as const) {
            const spouse = await openPerson(page, id, name);
            const theirMarriages = await familySection(spouse, 'Małżeństwa');
            await expect(
                theirMarriages.getByText(centreName, { exact: true }),
            ).toBeVisible();
        }
    } finally {
        await cleanupBySuffix(page, suffix);
    }
});
