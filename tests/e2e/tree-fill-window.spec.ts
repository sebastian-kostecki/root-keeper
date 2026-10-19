// Risks: "the family sheet does not show the whole family at a laptop screen, or
// leaves the window half empty on a large one" (context/changes/tree-dashboard-fill-window,
// phase 5). Pest and Vitest do not see this: the column width and the row heights
// come from the measured size of the area, so the sheet is measured in the browser.
// Data is prepared through the app's routes (tests/e2e/support/api.ts); every person
// carries the unique suffix in the last name, so the suffix fixture removes them all.
import { expect, test, type Locator, type Page } from './support/fixtures';
import {
    createChildOfCouple,
    createParent,
    createPerson,
    createSpouse,
} from './support/api';
import { gotoTree } from './support/navigation';

type Box = { x: number; y: number; width: number; height: number };

/** The two parents of `personId` and the two parents of each of them: 6 more people. */
async function createParentsWithGrandparents(
    page: Page,
    personId: number,
    suffix: string,
    prefix: string,
): Promise<void> {
    const fatherId = await createParent(
        page,
        personId,
        { first_name: `${prefix}Ojciec`, last_name: suffix, gender: 'male' },
        'father',
    );
    const motherId = await createParent(
        page,
        personId,
        { first_name: `${prefix}Matka`, last_name: suffix, gender: 'female' },
        'mother',
    );

    for (const [childId, side] of [
        [fatherId, `${prefix}Ojca`],
        [motherId, `${prefix}Matki`],
    ] as const) {
        await createParent(
            page,
            childId,
            { first_name: `${side}Dziadek`, last_name: suffix, gender: 'male' },
            'father',
        );
        await createParent(
            page,
            childId,
            {
                first_name: `${side}Babcia`,
                last_name: suffix,
                gender: 'female',
            },
            'mother',
        );
    }
}

/**
 * A full sheet: the centre person and a partner, each with two parents and four
 * grandparents (eight grandparent slots), and one child of the couple.
 */
async function createFullFamily(page: Page, suffix: string): Promise<number> {
    const focusId = await createPerson(page, {
        first_name: 'Centrum',
        last_name: suffix,
        gender: 'male',
    });
    await createParentsWithGrandparents(page, focusId, suffix, 'C');
    const partnerId = await createSpouse(page, focusId, {
        first_name: 'Partnerka',
        last_name: suffix,
        gender: 'female',
    });
    await createParentsWithGrandparents(page, partnerId, suffix, 'P');
    await createChildOfCouple(page, focusId, partnerId, {
        first_name: 'Dziecko',
        last_name: suffix,
        gender: 'male',
    });

    return focusId;
}

/** The link of a person card, by its accessible name (the gender word, then the name). */
function personCard(page: Page, gender: string, name: string): Locator {
    return page.getByRole('link', { name: `${gender} ${name}`, exact: true });
}

async function box(locator: Locator): Promise<Box> {
    const found = await locator.boundingBox();
    if (found === null) {
        throw new Error('The card has no box on the page');
    }

    return found;
}

test('tree-fill-window: at 1366x768 the whole family, from the outer grandparents to the child, is inside the tree area at the start', async ({
    page,
    suffix,
}) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    const focusId = await createFullFamily(page, suffix);

    await gotoTree(page, focusId);

    const outerCards = [
        personCard(page, 'mężczyzna', `COjcaDziadek ${suffix}`),
        personCard(page, 'kobieta', `CMatkiBabcia ${suffix}`),
        personCard(page, 'mężczyzna', `POjcaDziadek ${suffix}`),
        personCard(page, 'kobieta', `PMatkiBabcia ${suffix}`),
        personCard(page, 'mężczyzna', `Dziecko ${suffix}`),
    ];
    for (const card of outerCards) {
        await expect(card).toBeVisible();
    }

    // The fit settles over a few resize passes, so the boxes are read until they
    // are inside the area, not once.
    await expect(async () => {
        const area = await box(page.getByRole('main'));
        for (const card of outerCards) {
            const cardBox = await box(card);
            expect(cardBox.x).toBeGreaterThanOrEqual(area.x);
            expect(cardBox.x + cardBox.width).toBeLessThanOrEqual(
                area.x + area.width,
            );
            expect(cardBox.y).toBeGreaterThanOrEqual(area.y);
            expect(cardBox.y + cardBox.height).toBeLessThanOrEqual(
                area.y + area.height,
            );
        }
    }).toPass();
});

test('tree-fill-window: at 1920x1080 the pair card is the tallest, the grandparents the lowest, and the sheet fills most of the area', async ({
    page,
    suffix,
}) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    const focusId = await createFullFamily(page, suffix);

    await gotoTree(page, focusId);

    const grandparent = personCard(page, 'mężczyzna', `COjcaDziadek ${suffix}`);
    const parent = personCard(page, 'mężczyzna', `COjciec ${suffix}`);
    // The partner's card is the pair card (a link; the centre person's own card is not).
    const pair = personCard(page, 'kobieta', `Partnerka ${suffix}`);
    const child = personCard(page, 'mężczyzna', `Dziecko ${suffix}`);
    await expect(child).toBeVisible();

    await expect(async () => {
        // The tree area is what is left of the page content under the breadcrumbs
        // and the title: from the bottom of the h1 to the bottom of <main>.
        const main = await box(page.getByRole('main'));
        const title = await box(page.getByRole('heading', { level: 1 }));
        const areaHeight = main.y + main.height - (title.y + title.height);
        const grandparentBox = await box(grandparent);
        const parentBox = await box(parent);
        const pairBox = await box(pair);
        const childBox = await box(child);

        expect(pairBox.height).toBeGreaterThan(parentBox.height);
        expect(parentBox.height).toBeGreaterThan(grandparentBox.height);

        const sheetHeight = childBox.y + childBox.height - grandparentBox.y;
        expect(sheetHeight).toBeGreaterThanOrEqual(areaHeight * 0.8);
    }).toPass();
});
