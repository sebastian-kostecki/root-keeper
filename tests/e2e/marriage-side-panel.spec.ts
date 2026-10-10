// Risk: the marriage side panel (S-12) opens for the wrong pair, lists a child
// of one spouse only as the couple's child, or drops an unsaved marriage date
// without a word when the author leaves through the guard. Data is prepared
// through the app's routes (tests/e2e/support/api.ts); the husband's child from
// no marriage with the wife must stay out of the panel.
import { expect, test } from './support/fixtures';
import {
    createChildOfCouple,
    createChildOfOneParent,
    createPerson,
    createSpouse,
    personPreview,
} from './support/api';
import {
    gotoTree,
    marriagePanel,
    openMarriageFromUnion,
} from './support/navigation';

test("marriage-side-panel: the union menu opens the pair's marriage, the panel lists only the common children, and an unsaved date survives the guard", async ({
    page,
    suffix,
}) => {
    const husbandName = `Jan ${suffix}`;
    const wifeName = `Anna ${suffix}`;
    const commonChildName = `Zofia ${suffix}`;

    const husbandId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
        gender: 'male',
    });
    const wifeId = await createSpouse(page, husbandId, {
        first_name: 'Anna',
        last_name: suffix,
        gender: 'female',
    });
    await createChildOfCouple(page, husbandId, wifeId, {
        first_name: 'Zofia',
        last_name: suffix,
        gender: 'female',
    });
    await createChildOfOneParent(page, husbandId, {
        first_name: 'Piotr',
        last_name: suffix,
        gender: 'male',
    });
    const marriage = (
        await personPreview(page, husbandId)
    ).person.marriages.find((candidate) => candidate.spouse?.id === wifeId);
    if (!marriage) {
        throw new Error('The marriage of the pair was not created');
    }

    // 1. The husband's tree; the union node's menu opens the marriage panel.
    await gotoTree(page, husbandId);
    await openMarriageFromUnion(page);

    // 2. The dialog is named after the pair, husband first.
    const panel = marriagePanel(page, `${husbandName} i ${wifeName}`);
    await expect(panel).toBeVisible();

    // 3. The children region holds the common child only; the husband's child
    //    from another union is not listed.
    const children = panel.getByRole('region', { name: 'Dzieci' });
    await expect(
        children.getByRole('link', { name: commonChildName }),
    ).toBeVisible();
    await expect(children.getByRole('link')).toHaveCount(1);

    // 4. Edit the date in the marriage card and leave it unsaved.
    await panel.getByRole('button', { name: 'Edytuj Ślub' }).click();
    const date = panel.getByRole('textbox', { name: 'Data' });
    await date.fill('1901');

    // 5. `Źródła` in the sidebar asks first; the dialog and the typed date stay.
    await page.getByRole('link', { name: 'Źródła', exact: true }).click();
    const unsaved = panel.getByRole('alert');
    await expect(unsaved).toContainText(
        'Masz niezapisane zmiany w małżeństwie',
    );
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${husbandId}` &&
            url.searchParams.get('marriage') === String(marriage.id),
    );
    await expect(panel).toBeVisible();
    await expect(date).toHaveValue('1901');

    // 6. A click on the common child asks the same way; the pair stays open.
    await children.getByRole('link', { name: commonChildName }).click();
    await expect(unsaved).toContainText(
        'Masz niezapisane zmiany w małżeństwie',
    );
    await expect(panel).toBeVisible();
    await expect(date).toHaveValue('1901');

    // 7. `Zapisz` saves the marriage and then opens the child's dialog.
    await unsaved.getByRole('button', { name: 'Zapisz' }).click();
    await expect(
        page.getByRole('dialog', { name: commonChildName }),
    ).toBeVisible();

    // 8. The marriage date was saved.
    const saved = (await personPreview(page, husbandId)).person.marriages.find(
        (candidate) => candidate.id === marriage.id,
    );
    expect(saved?.date).toContain('1901');
});
