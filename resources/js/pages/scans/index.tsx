import { Head, Link } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as scansIndex } from '@/routes/scans';
import type { BreadcrumbItem, Scan } from '@/types';

type Props = {
    scans: Scan[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Skany',
        href: scansIndex(),
    },
];

export default function Index({ scans }: Props) {
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
                        <Link href={ScanController.create()}>Dodaj skan</Link>
                    </Button>
                </div>

                {scans.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground text-sm">
                            Nie masz jeszcze żadnych skanów.{' '}
                            <Link
                                href={ScanController.create()}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Dodaj pierwszy skan
                            </Link>
                            .
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {scans.map((scan) => (
                            <Link
                                key={scan.id}
                                href={ScanController.show(scan.id)}
                            >
                                <Card className="hover:bg-accent/50 gap-3 overflow-hidden py-0 transition-colors">
                                    <div className="bg-muted flex aspect-square items-center justify-center overflow-hidden">
                                        {scan.has_thumbnail ? (
                                            <img
                                                src={ScanController.thumbnail.url(
                                                    scan.id,
                                                )}
                                                alt={scan.original_name}
                                                loading="lazy"
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <ImageOff className="text-muted-foreground size-10" />
                                        )}
                                    </div>
                                    <CardContent className="space-y-0.5 pb-4">
                                        <p
                                            className="truncate text-sm font-medium"
                                            title={scan.original_name}
                                        >
                                            {scan.original_name}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {new Date(
                                                scan.uploaded_at,
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
