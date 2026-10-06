// Risk: "the UI sends an action to the wrong person" (test-plan.md:86). A click
// on a tree card moves the tree: it must centre on *that* person and on the
// union the card was shown in, not on the person's first union, and leave an
// open panel where it was. On the wrong union the child is not on the tree,
// and a child added from its slot joins the wrong pair. Data is prepared
// through the app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createParent, createPerson, createSpouse } from './support/api';
import { gotoTree } from './support/navigation';

test('tree-card-recentre: a click on a parent card centres on that parent with the union of the child and keeps the open panel', async ({
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
    await createParent(
        page,
        childId,
        { first_name: 'Ojciec', last_name: suffix, gender: 'male' },
        'father',
    );
    const motherId = await createParent(
        page,
        childId,
        { first_name: 'Matka', last_name: suffix, gender: 'female' },
        'mother',
    );
    // A marriage comes first among the mother's unions, so centring on her
    // without the union of the card shows this childless marriage.
    await createSpouse(page, motherId, {
        first_name: 'Drugi',
        last_name: suffix,
        gender: 'male',
    });

    await gotoTree(page, childId);
    const heading = page.getByRole('heading', { level: 1 });

    // 1. The child's panel is open beside the tree.
    await page.getByRole('link', { name: `Podgląd: ${childName}` }).click();
    const childPanel = page.getByRole('dialog', { name: childName });
    await expect(childPanel).toBeVisible();

    // 2. A click on the mother's card centres the tree on her.
    await page
        .getByRole('link', { name: `kobieta ${motherName}`, exact: true })
        .click();
    await expect(page).toHaveURL((url) => url.pathname === `/tree/${motherId}`);
    await expect(heading).toHaveText(motherName);

    // 3. The tree shows the union with the father: his card and the child's.
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${fatherName}`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: childName, exact: true }),
    ).toBeVisible();

    // 4. The child's panel stayed open.
    await expect(childPanel).toBeVisible();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('person') === String(childId),
    );
});
