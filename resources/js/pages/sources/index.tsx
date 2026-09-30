import { Head, Link } from '@inertiajs/react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import Heading from '@/components/heading';
import { SourceCreateDialog } from '@/components/sources/source-create-dialog';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as sourcesIndex } from '@/routes/sources';
import { formatSourceMeta, showsUploadDate } from '@/lib/source-description';
import type {
    BreadcrumbItem,
    Source,
    SourceKind,
    SourceRecordType,
} from '@/types';

type Props = {
    sources: Source[];
    kinds: { value: SourceKind; label: string }[];
    recordTypes: { value: SourceRecordType; label: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Źródła',
        href: sourcesIndex(),
    },
];

export default function Index({ sources, kinds, recordTypes }: Props) {
    return (
        <>
            <Head title="Źródła" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Źródła"
                        description="Akty, zdjęcia nagrobków i inne dokumenty Twojego drzewa"
                    />

                    <SourceCreateDialog
                        kinds={kinds}
                        recordTypes={recordTypes}
                        trigger={<Button>Dodaj źródło</Button>}
                    />
                </div>

                {sources.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground text-sm">
                            Nie masz jeszcze żadnych źródeł.{' '}
                            <SourceCreateDialog
                                kinds={kinds}
                                recordTypes={recordTypes}
                                trigger={
                                    <button
                                        type="button"
                                        className="text-primary underline-offset-4 hover:underline"
                                    >
                                        Dodaj pierwsze źródło
                                    </button>
                                }
                            />
                            .
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {sources.map((source) => {
                            const meta = formatSourceMeta(source);

                            return (
                                <Link
                                    key={source.id}
                                    href={SourceController.show(source.id)}
                                >
                                    <Card className="hover:bg-accent/50 gap-3 overflow-hidden py-0 transition-colors">
                                        <SourceThumbnail source={source} />
                                        <CardContent className="space-y-0.5 pb-4">
                                            <p
                                                className="truncate text-sm font-medium"
                                                title={source.display_name}
                                            >
                                                {source.display_name}
                                            </p>
                                            <Badge variant="secondary">
                                                {source.kind_label}
                                            </Badge>
                                            {meta !== null && (
                                                <p className="text-muted-foreground text-xs">
                                                    {meta}
                                                </p>
                                            )}
                                            {source.provenance && (
                                                <p
                                                    className="text-muted-foreground truncate text-xs"
                                                    title={source.provenance}
                                                >
                                                    {source.provenance}
                                                </p>
                                            )}
                                            {showsUploadDate(source) && (
                                                <p className="text-muted-foreground text-xs">
                                                    {new Date(
                                                        source.uploaded_at,
                                                    ).toLocaleDateString(
                                                        'pl-PL',
                                                    )}
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs,
};
