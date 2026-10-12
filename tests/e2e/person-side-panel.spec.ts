// Risk: "the UI sends an action to the wrong person" (test-plan.md:86) on the
// new entry of S-06 — the eye button of a tree card opens the panel of *that*
// person beside the tree, a relative from the `Rodzina` tab replaces the panel's
// content, and closing the panel leaves the tree centred on the same person.
// Data is prepared through the app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createParent, createPerson } from './support/api';
import { familySection, gotoTree } from './support/navigation';

test('person-side-panel: the eye button of a card opens its person beside the tree, a relative replaces it, close keeps the centre', async ({
    page,
    suffix,
}) => {
    const centreName = `Dziecko ${suffix}`;
    const parentName = `Ojciec ${suffix}`;

    const centreId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    await createParent(
        page,
        centreId,
        { first_name: 'Ojciec', last_name: suffix, gender: 'male' },
        'father',
    );

    await gotoTree(page, centreId);
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toHaveText(centreName);

    // 1. The eye button of the parent's card opens the parent's panel, the tree stays.
    await page.getByRole('link', { name: `Podgląd: ${parentName}` }).click();
    const parentPanel = page.getByRole('dialog', { name: parentName });
    await expect(parentPanel).toBeVisible();
    await expect(heading).toHaveText(centreName);

    // 2. From the parent's family, the child replaces the panel's content.
    const children = await familySection(parentPanel, 'Dzieci');
    await children.getByRole('link', { name: new RegExp(centreName) }).click();
    const centrePanel = page.getByRole('dialog', { name: centreName });
    await expect(centrePanel).toBeVisible();
    await expect(parentPanel).toBeHidden();

    // 3. Closing returns to the tree of the same centre, without `person`.
    await centrePanel.getByRole('button', { name: 'Zamknij panel' }).click();
    await expect(centrePanel).toBeHidden();
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${centreId}` &&
            !url.searchParams.has('person'),
    );
    await expect(heading).toHaveText(centreName);
});
