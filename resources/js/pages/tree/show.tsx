import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { AddPersonPanel } from '@/components/tree/add-person-panel';
import { FamilySheet } from '@/components/tree/family-sheet';
import { SetRootPersonButton } from '@/components/tree/set-root-person-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as personsIndex } from '@/routes/persons';
import { index as treeIndex, show as treeShow } from '@/routes/tree';
import type {
    FamilySheet as FamilySheetData,
    Gender,
    PersonCandidate,
    Religion,
} from '@/types';

type Props = {
    sheet: FamilySheetData | null;
    rootPersonId: number | null;
    personCandidates?: PersonCandidate[];
    genders: Gender[];
    religions: Religion[];
};

export default function Show({
    sheet,
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

                    <Card>
                        <CardContent className="flex flex-col items-start gap-4 text-sm">
                            <p className="text-muted-foreground">
                                Nie wybrano probanta. Otwórz osobę i wybierz
                                „Ustaw jako probanta”.
                            </p>

                            <Button variant="outline" size="sm" asChild>
                                <Link href={personsIndex()}>
                                    Przejdź do osób
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
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
                    personCandidates={personCandidates}
                    genders={genders}
                    religions={religions}
                />
            </div>
        </>
    );
}
