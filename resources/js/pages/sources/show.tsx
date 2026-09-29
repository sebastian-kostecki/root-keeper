import { Head, setLayoutProps } from '@inertiajs/react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import Heading from '@/components/heading';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { DeleteSourceDialog } from '@/components/sources/delete-source-dialog';
import { SourceViewer } from '@/components/sources/source-viewer';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as sourcesIndex } from '@/routes/sources';
import type { Source, SourceConfirmation } from '@/types';

type Props = {
    source: Source;
    confirms: SourceConfirmation[];
};

export default function Show({ source, confirms }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Skany', href: sourcesIndex() },
            {
                title: source.original_name,
                href: SourceController.show(source.id),
            },
        ],
    });

    const sizeInMb = (source.size / (1024 * 1024)).toFixed(1);
    const dimensions =
        source.width !== null && source.height !== null
            ? `${source.width}×${source.height}`
            : 'brak danych';

    return (
        <>
            <Head title={source.original_name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={source.original_name}
                        description={`${new Date(source.uploaded_at).toLocaleDateString('pl-PL')} · ${sizeInMb} MB · ${dimensions}`}
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

                <SourceViewer
                    src={SourceController.file.url(source.id)}
                    alt={source.original_name}
                />

                <Card>
                    <CardContent className="space-y-2">
                        <p className="text-muted-foreground text-sm">
                            Potwierdza
                        </p>
                        {confirms.length === 0 ? (
                            <p className="text-sm">
                                Ten skan nie potwierdza jeszcze żadnego
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
