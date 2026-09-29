import { Head, Link } from '@inertiajs/react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import Heading from '@/components/heading';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as sourcesIndex } from '@/routes/sources';
import type { BreadcrumbItem, Source } from '@/types';

type Props = {
    sources: Source[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Skany',
        href: sourcesIndex(),
    },
];

export default function Index({ sources }: Props) {
    return (
        <>
            <Head title="Skany" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Skany"
                        description="Zeskanowane akty i dokumenty Twojego drzewa"
                    />

                    <Button asChild>
                        <Link href={SourceController.create()}>Dodaj skan</Link>
                    </Button>
                </div>

                {sources.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground text-sm">
                            Nie masz jeszcze żadnych skanów.{' '}
                            <Link
                                href={SourceController.create()}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Dodaj pierwszy skan
                            </Link>
                            .
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {sources.map((source) => (
                            <Link
                                key={source.id}
                                href={SourceController.show(source.id)}
                            >
                                <Card className="hover:bg-accent/50 gap-3 overflow-hidden py-0 transition-colors">
                                    <SourceThumbnail source={source} />
                                    <CardContent className="space-y-0.5 pb-4">
                                        <p
                                            className="truncate text-sm font-medium"
                                            title={source.original_name}
                                        >
                                            {source.original_name}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {new Date(
                                                source.uploaded_at,
                                            ).toLocaleDateString('pl-PL')}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs,
};
