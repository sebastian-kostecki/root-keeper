// Risk: "entry to the ancestors view and recentring lead to the wrong person or
// lose the panel". The ancestors page is opened from a person's panel and
// recentred from a card; after each step the address, the heading and the open
// panel must all name the same person, and the generations switch must keep the
// chart's person. Data is prepared through the app's routes
// (tests/e2e/support/api.ts).
import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { createParent, createPerson } from './support/api';
import { gotoTree, openPerson } from './support/navigation';

test('ancestors-view: entry from the person panel and recentring keep the right person and the panel', async ({
    page,
    suffix,
}) => {
    const childName = `Dziecko ${suffix}`;
    const fatherName = `Ojciec ${suffix}`;
    const motherName = `Matka ${suffix}`;
    const grandfatherName = `Dziadek ${suffix}`;

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
    const motherId = await createParent(
        page,
        childId,
        { first_name: 'Matka', last_name: suffix, gender: 'female' },
        'mother',
    );
    // The father's father only: his mother stays unknown.
    await createParent(
        page,
        fatherId,
        { first_name: 'Dziadek', last_name: suffix, gender: 'male' },
        'father',
    );

    // 1. Entry: the child's panel on the tree leads to the ancestors page.
    const childPanel = await openPerson(page, childId, childName);
    await childPanel
        .getByRole('button', { name: 'Więcej działań', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Pokaż przodków' }).click();

    await expect(page).toHaveURL(
        (url) => url.pathname === `/tree/${childId}/ancestors`,
    );
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toHaveText(childName);
    await expect(childPanel).toBeVisible();

    // 2. The chart shows the known ancestors and the placeholders for the
    // missing parents. The default is min(4, available generations): this line
    // has three, so the grandfather's row has no placeholders above it.
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${fatherName}`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: `kobieta ${motherName}`, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${grandfatherName}`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(page.getByText('Nieznana matka', { exact: true })).toHaveCount(
        2,
    );
    await expect(
        page.getByText('Nieznany ojciec', { exact: true }),
    ).toHaveCount(1);

    // 3. Two generations hide the grandfather and every placeholder; the
    // parents stay.
    await page.getByRole('radio', { name: '2', exact: true }).click();
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${childId}/ancestors` &&
            url.searchParams.get('generations') === '2',
    );
    await expect(childPanel).toBeVisible();
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${grandfatherName}`,
            exact: true,
        }),
    ).toHaveCount(0);
    await expect(page.getByText('Nieznana matka', { exact: true })).toHaveCount(
        0,
    );
    await expect(
        page.getByText('Nieznany ojciec', { exact: true }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${fatherName}`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: `kobieta ${motherName}`, exact: true }),
    ).toBeVisible();

    // 4. A click on the mother's card opens her panel beside the chart.
    await page
        .getByRole('link', { name: `kobieta ${motherName}`, exact: true })
        .click();
    const motherPanel = page.getByRole('dialog', { name: motherName });
    await expect(motherPanel).toBeVisible();

    // 5. Her panel's "Pokaż przodków" recentres the chart on her and keeps
    // the panel open and the generations of the address.
    await motherPanel
        .getByRole('button', { name: 'Więcej działań', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Pokaż przodków' }).click();

    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${motherId}/ancestors` &&
            url.searchParams.get('generations') === '2' &&
            url.searchParams.get('person') === String(motherId),
    );
    await expect(heading).toHaveText(motherName);
    await expect(motherPanel).toBeVisible();

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});

// Risk: "a manual zoom is lost when the canvas resizes, or 'Dopasuj' does not
// bring the fitted view back". Cards are measured by their width on screen, so
// the assertions compare scales without reading the transform.
test('ancestors-view: a manual zoom survives a canvas resize until "Dopasuj"', async ({
    page,
    suffix,
}) => {
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
    await createParent(
        page,
        childId,
        { first_name: 'Matka', last_name: suffix, gender: 'female' },
        'mother',
    );

    await page.goto(`/tree/${childId}/ancestors?generations=2`);

    const fatherCard = page.getByRole('link', {
        name: `mężczyzna ${fatherName}`,
        exact: true,
    });
    await expect(fatherCard).toBeVisible();

    const fitted = await settledWidth(fatherCard);

    // 1. "Pomniejsz" shrinks the cards.
    await page.getByRole('button', { name: 'Pomniejsz', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeLessThan(fitted);
    const zoomedOut = await settledWidth(fatherCard);

    // 2. Opening a panel narrows the canvas; the manual zoom is kept.
    await page
        .getByRole('link', { name: `kobieta ${motherName}`, exact: true })
        .click();
    await expect(page.getByRole('dialog', { name: motherName })).toBeVisible();
    expect(Math.abs((await settledWidth(fatherCard)) - zoomedOut)).toBeLessThan(
        1,
    );

    // 3. "Dopasuj" brings the fitted view back.
    await page.getByRole('button', { name: 'Dopasuj', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeGreaterThan(zoomedOut);
    const refitted = await settledWidth(fatherCard);

    // 4. "Powiększ" grows the cards again.
    await page.getByRole('button', { name: 'Pomniejsz', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeLessThan(refitted);
    const shrunk = await settledWidth(fatherCard);
    await page.getByRole('button', { name: 'Powiększ', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeGreaterThan(shrunk);

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});

/** The tree card or ancestors card of a man, named as its link reads. */
function manCard(page: Page, name: string): Locator {
    return page.getByRole('link', { name: `mężczyzna ${name}`, exact: true });
}

/** Cards are measured by their width on screen; waits until two samples agree (the zoom animates). */
async function settledWidth(card: Locator): Promise<number> {
    let previous = -1;
    let width = 0;

    await expect
        .poll(async () => {
            width = (await card.boundingBox())?.width ?? 0;
            const settled = width > 0 && Math.abs(width - previous) < 0.5;
            previous = width;

            return settled;
        })
        .toBe(true);

    return width;
}

/**
 * Two animation frames: whatever a wheel event would start has started by then.
 * Only for a negative check (the tree does not zoom): there is no state to wait
 * for when nothing should change, so this is the one wait on frames, not time.
 */
async function afterFrames(page: Page): Promise<void> {
    await page.evaluate(
        () =>
            new Promise<void>((resolve) =>
                requestAnimationFrame(() =>
                    requestAnimationFrame(() => resolve()),
                ),
            ),
    );
}

// Risk: "the wheel zoom does nothing, jumps to the ends of the range, or leaks
// onto the tree". A single mouse notch over the ancestors chart zooms by a
// small step without Ctrl; the same notch over the tree changes nothing.
test('ancestors-view: one wheel notch zooms the chart by a small step and the tree not at all', async ({
    page,
    suffix,
}) => {
    const fatherName = `Ojciec ${suffix}`;

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
    await createParent(
        page,
        childId,
        { first_name: 'Matka', last_name: suffix, gender: 'female' },
        'mother',
    );

    // 1. The ancestors chart. Start below the zoom cap, so a notch has room to grow.
    await page.goto(`/tree/${childId}/ancestors?generations=2`);
    const chartCard = manCard(page, fatherName);
    await expect(chartCard).toBeVisible();
    await page.getByRole('button', { name: 'Pomniejsz', exact: true }).click();
    const before = await settledWidth(chartCard);

    const box = await chartCard.boundingBox();
    await page.mouse.move(
        (box?.x ?? 0) + (box?.width ?? 0) / 2,
        (box?.y ?? 0) + (box?.height ?? 0) / 2,
    );
    await page.mouse.wheel(0, -100);
    await expect
        .poll(async () => (await chartCard.boundingBox())?.width ?? 0)
        .toBeGreaterThan(before);
    const after = await settledWidth(chartCard);
    expect(after / before).toBeLessThan(1.25);

    // 2. The tree: the same notch leaves the cards where they were.
    await page.goto(`/tree/${childId}`);
    const treeCard = manCard(page, fatherName);
    await expect(treeCard).toBeVisible();
    const treeBefore = await settledWidth(treeCard);
    const treeBox = await treeCard.boundingBox();
    await page.mouse.move(
        (treeBox?.x ?? 0) + (treeBox?.width ?? 0) / 2,
        (treeBox?.y ?? 0) + (treeBox?.height ?? 0) / 2,
    );
    await page.mouse.wheel(0, -100);
    await afterFrames(page);
    expect(Math.abs((await settledWidth(treeCard)) - treeBefore)).toBeLessThan(
        0.5,
    );

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});

// Risk: "the generations switch offers a fixed range instead of the data's".
// The options come from what the line really has: a line of three generations
// offers 2 and 3, a person whose parents have no parents offers nothing, and a
// request past the end shows the deepest generation.
test('ancestors-view: the generations switch offers the generations the data has', async ({
    page,
    suffix,
}) => {
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
    // The father's father only: the line is three generations deep.
    await createParent(
        page,
        fatherId,
        { first_name: 'Dziadek', last_name: suffix, gender: 'male' },
        'father',
    );

    // 1. Three generations: the options are exactly 2 and 3, the default is 3.
    await page.goto(`/tree/${childId}/ancestors`);
    const group = page.getByRole('radiogroup', { name: 'Pokolenia' });
    await expect(group.getByRole('radio')).toHaveText(['2', '3']);
    await expect(group.getByRole('radio', { name: '3' })).toBeChecked();

    // 2. A request past the end shows the deepest generation, not a gap.
    await page.goto(`/tree/${childId}/ancestors?generations=9`);
    await expect(group.getByRole('radio', { name: '3' })).toBeChecked();
    await expect(group.getByRole('radio')).toHaveCount(2);

    // 3. The father's parents have no parents: one choice, so no switch at all.
    await page.goto(`/tree/${fatherId}/ancestors`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        `Ojciec ${suffix}`,
    );
    await expect(page.getByText('Pokolenia', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('radiogroup')).toHaveCount(0);

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});

/** Adds both parents to `childId` and goes on `depth - 1` more generations up; returns how many people it created. */
async function createAncestors(
    page: Page,
    childId: number,
    depth: number,
    suffix: string,
    path = 'A',
): Promise<number> {
    if (depth === 0) {
        return 0;
    }

    const fatherId = await createParent(
        page,
        childId,
        { first_name: `Ojciec${path}`, last_name: suffix, gender: 'male' },
        'father',
    );
    const motherId = await createParent(
        page,
        childId,
        { first_name: `Matka${path}`, last_name: suffix, gender: 'female' },
        'mother',
    );

    return (
        2 +
        (await createAncestors(page, fatherId, depth - 1, suffix, `${path}o`)) +
        (await createAncestors(page, motherId, depth - 1, suffix, `${path}m`))
    );
}

// Risk: "a deep chart starts as an unreadable speck". Five full generations of
// ancestors are 32 cards in the top row, far wider than a phone screen: the
// chart must start anchored on the focus person at a readable scale, and zoom
// out to the whole chart, which is smaller than the fit floor.
test('ancestors-view: a deep chart starts anchored on the focus person and zooms out to the whole', async ({
    page,
    suffix,
}) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 390, height: 844 });
    const focusName = `Dziecko ${suffix}`;

    const childId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
        gender: 'male',
    });
    await createAncestors(page, childId, 5, suffix);

    await page.goto(`/tree/${childId}/ancestors?generations=6`);
    const focusCard = manCard(page, focusName);
    await expect(focusCard).toBeVisible();

    // The column of a wide chart is the 12 rem minimum, so a card's width over
    // 12 rem is the scale. The anchored start holds ANCESTORS_ANCHOR_SCALE (0.75).
    const rootFontSize = await page.evaluate(() =>
        parseFloat(getComputedStyle(document.documentElement).fontSize),
    );
    const scaleOf = async () =>
        (await settledWidth(focusCard)) / (12 * rootFontSize);

    expect(await scaleOf()).toBeGreaterThanOrEqual(0.7);
    const box = await focusCard.boundingBox();
    const viewport = page.viewportSize();
    expect(box?.x ?? -1).toBeGreaterThanOrEqual(0);
    expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(
        viewport?.width ?? 0,
    );

    // Zooming out goes below the fit floor (0.1) to the whole chart.
    const zoomOut = page.getByRole('button', {
        name: 'Pomniejsz',
        exact: true,
    });
    await expect(async () => {
        await zoomOut.click();
        expect(await scaleOf()).toBeLessThan(0.1);
    }).toPass();

    // "Dopasuj" is back on the person.
    await page.getByRole('button', { name: 'Dopasuj', exact: true }).click();
    await expect.poll(scaleOf).toBeGreaterThanOrEqual(0.7);

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});

// Risk: "the way into the ancestors view from the tree leads to another
// person". The tree's "Przodkowie" link opens the ancestors of the person the
// tree is centred on, with the default depth.
test('ancestors-view: the "Przodkowie" link on the tree opens the ancestors of the centre person', async ({
    page,
    suffix,
}) => {
    const fatherName = `Ojciec ${suffix}`;

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

    // Centred on the father, not on the child the data starts from.
    await gotoTree(page, fatherId);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        fatherName,
    );
    await page
        .getByRole('main')
        .getByRole('link', { name: 'Przodkowie', exact: true })
        .click();

    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${fatherId}/ancestors` && url.search === '',
    );
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        fatherName,
    );

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});
