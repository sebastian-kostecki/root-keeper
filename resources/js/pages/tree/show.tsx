import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { UsersIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { AddPersonPanel } from '@/components/tree/add-person-panel';
import { FamilySheet } from '@/components/tree/family-sheet';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { resolveAddTarget } from '@/lib/add-person-target';
import { index as treeIndex, show as treeShow } from '@/routes/tree';
import type {
    FamilySheet as FamilySheetData,
    Gender,
    PersonCandidate,
    Religion,
} from '@/types';

type Props = {
    sheet: FamilySheetData | null;
    hasPeople?: boolean;
    personCandidates?: PersonCandidate[];
    genders: Gender[];
    religions: Religion[];
};

export default function Show({
    sheet,
    hasPeople = false,
    personCandidates,
    genders,
    religions,
}: Props) {
    const { url } = usePage();
    const addValue = new URL(url, 'http://localhost').searchParams.get('add');
    const addTarget = resolveAddTarget(sheet, addValue, { hasPeople });

    setLayoutProps({
        breadcrumbs: [
            { title: 'Drzewo', href: treeIndex() },
            ...(sheet
                ? [
                      {
                          title: sheet.focus.display_name,
                          href: treeShow(sheet.focus.id),
                      },
                  ]
                : []),
        ],
        fillHeight: sheet !== null,
    });

    if (sheet === null) {
        return (
            <>
                <Head title="Drzewo" />

                <div className="space-y-6">
                    <Heading title="Drzewo" variant="page" />

                    <ProbandEmptyState hasPeople={hasPeople} />
                </div>

                <AddPersonPanel
                    value={addValue}
                    target={addTarget}
                    personCandidates={personCandidates}
                    genders={genders}
                    religions={religions}
                />
            </>
        );
    }

    return (
        <>
            <Head title={`Drzewo: ${sheet.focus.display_name}`} />

            <div className="flex min-h-0 flex-1 flex-col">
                <h1 className="sr-only">{sheet.focus.display_name}</h1>

                <FamilySheet sheet={sheet} activeAdd={addValue} />

                <AddPersonPanel
                    value={addValue}
                    target={addTarget}
                    personCandidates={personCandidates}
                    genders={genders}
                    religions={religions}
                />
            </div>
        </>
    );
}

/** The tree without a proband: the first person, or the choice of one among the people. */
function ProbandEmptyState({ hasPeople }: { hasPeople: boolean }) {
    const { open } = useSidePanelNavigation('add');

    return (
        <EmptyState
            icon={<UsersIcon />}
            title={hasPeople ? 'Nie wybrano probanta' : 'Drzewo jest puste'}
            description={
                hasPeople
                    ? 'Wybierz osobę, od której zaczyna się drzewo, albo dodaj nową.'
                    : 'Dodaj pierwszą osobę — zostanie probantem, od którego zaczyna się drzewo.'
            }
            action={
                <Button type="button" onClick={() => open('proband')}>
                    {hasPeople ? 'Wybierz probanta' : 'Dodaj pierwszą osobę'}
                </Button>
            }
        />
    );
}
