import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { UsersIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { AddPersonPanel } from '@/components/tree/add-person-panel';
import { FamilySheet } from '@/components/tree/family-sheet';
import { SetRootPersonButton } from '@/components/tree/set-root-person-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
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
    rootPersonId: number | null;
    personCandidates?: PersonCandidate[];
    genders: Gender[];
    religions: Religion[];
};

export default function Show({
    sheet,
    hasPeople = false,
    rootPersonId,
    personCandidates,
    genders,
    religions,
}: Props) {
    const { url } = usePage();
    const addValue = new URL(url, 'http://localhost').searchParams.get('add');

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
                    sheet={null}
                    hasPeople={hasPeople}
                    personCandidates={personCandidates}
                    genders={genders}
                    religions={religions}
                />
            </>
        );
    }

    const isProband = sheet.focus.id === rootPersonId;

    return (
        <>
            <Head title={`Drzewo: ${sheet.focus.display_name}`} />

            <div className="flex min-h-0 flex-1 flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading title={sheet.focus.display_name} variant="page" />

                    <div className="flex flex-wrap items-center gap-2">
                        {isProband ? (
                            <Badge variant="secondary">Probant</Badge>
                        ) : (
                            <>
                                {rootPersonId !== null && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={treeIndex()}>
                                            Wróć do probanta
                                        </Link>
                                    </Button>
                                )}

                                <SetRootPersonButton
                                    personId={sheet.focus.id}
                                />
                            </>
                        )}
                    </div>
                </div>

                <FamilySheet sheet={sheet} />

                <AddPersonPanel
                    value={addValue}
                    sheet={sheet}
                    hasPeople
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
