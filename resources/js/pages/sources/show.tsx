import { Head, setLayoutProps } from '@inertiajs/react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import Heading from '@/components/heading';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { DeleteSourceDialog } from '@/components/sources/delete-source-dialog';
import { SourceDetails } from '@/components/sources/source-details';
import { SourceEditDialog } from '@/components/sources/source-edit-dialog';
import { SourceViewer } from '@/components/sources/source-viewer';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as sourcesIndex } from '@/routes/sources';
import type {
    Source,
    SourceConfirmation,
    SourceKind,
    SourceRecordType,
} from '@/types';

type Props = {
    source: Source;
    kinds: { value: SourceKind; label: string }[];
    recordTypes: { value: SourceRecordType; label: string }[];
    confirms: SourceConfirmation[];
};

export default function Show({ source, kinds, recordTypes, confirms }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Źródła', href: sourcesIndex() },
            {
                title: source.display_name,
                href: SourceController.show(source.id),
            },
        ],
    });

    const headerLine = [
        source.kind_label,
        source.record_type_label,
        source.year,
        source.entry_number ? `nr ${source.entry_number}` : null,
    ]
        .filter((part) => part !== null && part !== '')
        .join(' · ');

    return (
        <>
            <Head title={source.display_name} />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={source.display_name}
                        description={headerLine}
                    />

                    <div className="flex shrink-0 items-center gap-2">
                        <SourceEditDialog
                            source={source}
                            kinds={kinds}
                            recordTypes={recordTypes}
                            trigger={
                                <Button variant="outline" size="sm">
                                    Edytuj
                                </Button>
                            }
                        />
                        <DeleteSourceDialog
                            source={source}
                            confirms={confirms}
                            trigger={
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-destructive hover:text-destructive"
                                >
                                    Usuń
                                </Button>
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="h-[calc(100vh-12rem)]">
                        <SourceViewer
                            src={SourceController.file.url(source.id)}
                            alt={source.display_name}
                        />
                    </div>

                    <SourceDetails
                        source={source}
                        emptyAction={
                            <SourceEditDialog
                                source={source}
                                kinds={kinds}
                                recordTypes={recordTypes}
                                trigger={
                                    <Button variant="outline" size="sm">
                                        Dodaj opis
                                    </Button>
                                }
                            />
                        }
                    />
                </div>

                <Card>
                    <CardContent className="space-y-2">
                        <p className="text-muted-foreground text-sm">
                            Potwierdza
                        </p>
                        {confirms.length === 0 ? (
                            <p className="text-sm">
                                To źródło nie potwierdza jeszcze żadnego
                                wydarzenia.
                            </p>
                        ) : (
                            <ul className="space-y-1">
                                {confirms.map((confirmation) => (
                                    <li
                                        key={`${confirmation.event_id}-${confirmation.person.id}`}
                                        className="text-sm"
                                    >
                                        <PersonPreviewLink
                                            personId={confirmation.person.id}
                                            className="text-primary underline-offset-4 hover:underline"
                                        >
                                            {confirmation.event_label} —{' '}
                                            {confirmation.person.display_name}
                                        </PersonPreviewLink>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
