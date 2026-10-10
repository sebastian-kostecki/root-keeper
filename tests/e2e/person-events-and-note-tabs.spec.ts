// Risks (context/changes/person-events-and-note-tabs/plan.md, Phase 4):
// 1. "note-unsaved-guard": an unsaved note blocks a tab switch in the person panel
//    until the user saves or discards it; `Zapisz` saves it and the switch goes on.
// 2. "timeline-relative-navigation": a child's birth row on the `Wydarzenia` tab
//    names the child; clicking the name replaces the panel with the child's panel.
// Data is prepared through the app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createChildOfOneParent, createPerson } from './support/api';
import { gotoTree, openPerson } from './support/navigation';

test('person-events-and-note-tabs: an unsaved note asks before the tab switch, Zapisz saves it and the switch goes on', async ({
    page,
    suffix,
}) => {
    const personName = `Notatka ${suffix}`;
    const noteText = `Zapis testowy ${suffix}`;

    const personId = await createPerson(page, {
        first_name: 'Notatka',
        last_name: suffix,
    });

    await gotoTree(page, personId);
    await page.getByRole('link', { name: `Podgląd: ${personName}` }).click();
    const panel = page.getByRole('dialog', { name: personName });
    await expect(panel).toBeVisible();

    // 1. The note is typed but not saved.
    await panel.getByRole('tab', { name: /^Notatka/ }).click();
    await panel.getByRole('button', { name: 'Edytuj Notatka' }).click();
    await panel.getByRole('textbox', { name: 'Treść notatki' }).fill(noteText);

    // 2. Switching to `Wydarzenia` asks first; the tab does not change yet.
    await panel.getByRole('tab', { name: 'Wydarzenia' }).click();
    const unsaved = panel.getByRole('alert');
    await expect(unsaved).toContainText('Masz niezapisane zmiany w notatce.');
    await expect(panel.getByRole('tab', { name: 'Notatka' })).toHaveAttribute(
        'aria-selected',
        'true',
    );

    // 3. `Zapisz` saves the note, then the switch goes on.
    await unsaved.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(
        panel.getByRole('tab', { name: 'Wydarzenia' }),
    ).toHaveAttribute('aria-selected', 'true');

    // 4. The saved note survives a fresh open of the panel.
    const reopened = await openPerson(page, personId, personName);
    await reopened.getByRole('tab', { name: /^Notatka/ }).click();
    await expect(reopened.getByText(noteText, { exact: true })).toBeVisible();
});

test('person-events-and-note-tabs: a child birth row on Wydarzenia names the child, and clicking the name replaces the panel', async ({
    page,
    suffix,
}) => {
    const parentName = `Ojciec ${suffix}`;
    const childName = `Anna ${suffix}`;

    const parentId = await createPerson(page, {
        first_name: 'Ojciec',
        last_name: suffix,
        gender: 'male',
    });
    await createChildOfOneParent(page, parentId, {
        first_name: 'Anna',
        last_name: suffix,
        gender: 'female',
        birth: { year: 1990 },
    });

    await gotoTree(page, parentId);
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toHaveText(parentName);

    // 1. The parent's panel shows the child's birth on the timeline.
    const parentPanel = await openPerson(page, parentId, parentName);
    await parentPanel.getByRole('tab', { name: 'Wydarzenia' }).click();
    const birthRow = parentPanel
        .getByRole('listitem')
        .filter({ hasText: 'Urodzenie córki' });
    await expect(birthRow).toContainText('1990');

    // 2. The child's name replaces the panel content; the tree stays on the parent.
    await birthRow.getByRole('link', { name: childName }).click();
    const childPanel = page.getByRole('dialog', { name: childName });
    await expect(childPanel).toBeVisible();
    await expect(parentPanel).toBeHidden();
    await expect(heading).toHaveText(parentName);
});
