// Risk: "a person's name on the tree card breaks inside a word, runs under the eye
// button or is cut at the card edge; a woman's card shows her maiden surname and
// her accessible name follows it". Pest does not see the layout, so the card is
// measured in the browser at two screen sizes. Data is prepared through the app's
// routes (tests/e2e/support/api.ts). The centre person carries the suffix in the
// last name, so the suffix fixture removes it; the other people keep their names
// and are deleted by id in `finally`.
import { expect, test, type Locator } from './support/fixtures';
import { createParent, createPerson, deletePerson } from './support/api';
import { gotoTree } from './support/navigation';

/**
 * Lines of text the name shows: the distinct line tops of its text inside the
 * element's box. Lines the clamp hides lie below the box and are not counted.
 */
async function visibleNameLines(name: Locator): Promise<number> {
    return name.evaluate((element) => {
        const box = element.getBoundingClientRect();
        const textNode = [...element.childNodes].find(
            (node) =>
                node.nodeType === Node.TEXT_NODE && node.textContent?.trim(),
        );
        if (!textNode) {
            return 0;
        }
        const range = document.createRange();
        range.selectNodeContents(textNode);
        const tops = new Set<number>();
        for (const rect of range.getClientRects()) {
            if (rect.top < box.bottom - 1) {
                tops.add(Math.round(rect.top));
            }
        }

        return tops.size;
    });
}

test('tree-card-name: at 2560 a grandparent name of reasonable length fits on one line of the card', async ({
    page,
    suffix,
}) => {
    await page.setViewportSize({ width: 2560, height: 1440 });

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
    const grandfatherId = await createParent(
        page,
        fatherId,
        { first_name: 'Jan', last_name: 'Wróblewski', gender: 'male' },
        'father',
    );

    try {
        await gotoTree(page, childId);

        const name = page.getByTitle('Jan Wróblewski', { exact: true });
        await expect(name).toBeVisible();
        // One line of text.
        expect(await visibleNameLines(name)).toBe(1);
    } finally {
        await deletePerson(page, grandfatherId);
    }
});

test('tree-card-name: at 1366 a long name wraps by words to at most two lines, and a woman card shows her maiden surname', async ({
    page,
    suffix,
}) => {
    await page.setViewportSize({ width: 1366, height: 768 });

    const childId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    const motherId = await createParent(
        page,
        childId,
        {
            first_name: 'Anna',
            last_name: 'Kowalska',
            maiden_name: 'Nowak',
            gender: 'female',
        },
        'mother',
    );
    const grandmotherId = await createParent(
        page,
        motherId,
        {
            first_name: 'Aleksandra',
            last_name: 'Brzęczyszczykiewicz',
            gender: 'female',
        },
        'mother',
    );

    try {
        await gotoTree(page, childId);

        const longName = page.getByTitle('Aleksandra Brzęczyszczykiewicz', {
            exact: true,
        });
        await expect(longName).toBeVisible();
        // At most two lines of text, then the ellipsis.
        expect(await visibleNameLines(longName)).toBeLessThanOrEqual(2);

        // The maiden surname is the name on the card; the married one is not.
        const maidenName = page.getByTitle('Anna Nowak', { exact: true });
        await expect(maidenName).toBeVisible();
        await expect(
            page.getByTitle('Anna Kowalska z d. Nowak', { exact: true }),
        ).toHaveCount(0);

        // The card link's accessible name carries the gender and the maiden name.
        await expect(
            page.getByRole('link', { name: 'kobieta Anna Nowak' }),
        ).toBeVisible();
    } finally {
        await deletePerson(page, grandmotherId);
        await deletePerson(page, motherId);
    }
});
