// Risk: "the UI sends an action to the wrong person" (test-plan.md:86). "Pokaż
// na drzewie" in the panel of a parent with two unions must centre the tree on
// the union with the child the author came from, not on the parent's first
// union, and keep the panel open (S-06 phase 2). On the wrong union the child
// is not on the tree, and a child added from its slot joins the wrong pair.
// Data is prepared through the app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createParent, createPerson, createSpouse } from './support/api';
import { gotoTree } from './support/navigation';

test('person-panel-show-on-tree: centring on a parent from the panel keeps the union with the child and the panel', async ({
    page,
    suffix,
}) => {
    const childName = `Dziecko ${suffix}`;
    const fatherName = `Ojciec ${suffix}`;
    const motherName = `Matka ${suffix}`;

    const childId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    const fatherId = await createParent(
        page,
        childId,
        { first_name: 'Ojciec', last_name: suffix, gender: 'male' },
        'father',
    );
    await createParent(
        page,
        childId,
        { first_name: 'Matka', last_name: suffix, gender: 'female' },
        'mother',
    );
    // A marriage comes first among the father's unions, so centring on him
    // without the union of the card shows this childless marriage.
    await createSpouse(page, fatherId, {
        first_name: 'Druga',
        last_name: suffix,
        gender: 'female',
    });

    await gotoTree(page, childId);

    // 1. The father's card opens his panel.
    await page
        .getByRole('link', { name: `mężczyzna ${fatherName}`, exact: true })
        .click();
    const fatherPanel = page.getByRole('dialog', { name: fatherName });
    await expect(fatherPanel).toBeVisible();

    // 2. "Pokaż na drzewie" centres on the father and keeps his panel open.
    await fatherPanel.getByRole('link', { name: 'Pokaż na drzewie' }).click();
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${fatherId}` &&
            url.searchParams.get('person') === String(fatherId),
    );
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        fatherName,
    );
    await expect(fatherPanel).toBeVisible();

    // 3. The tree shows the union with the mother: her card and the child's.
    await expect(
        page.getByRole('link', { name: `kobieta ${motherName}`, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: childName, exact: true }),
    ).toBeVisible();
    // The father is the centre now, so the panel no longer offers it.
    await expect(
        fatherPanel.getByRole('link', { name: 'Pokaż na drzewie' }),
    ).toBeHidden();
});
