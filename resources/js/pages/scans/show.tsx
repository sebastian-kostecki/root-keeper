import { Head, Link, setLayoutProps } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import Heading from '@/components/heading';
import { DeleteScanDialog } from '@/components/scans/delete-scan-dialog';
import { ScanViewer } from '@/components/scans/scan-viewer';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as scansIndex } from '@/routes/scans';
import type { Scan, ScanConfirmation } from '@/types';

type Props = {
    scan: Scan;
    confirms: ScanConfirmation[];
};

export default function Show({ scan, confirms }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Skany', href: scansIndex() },
            {
                title: scan.original_name,
                href: ScanController.show(scan.id),
            },
        ],
    });

    const sizeInMb = (scan.size / (1024 * 1024)).toFixed(1);
    const dimensions =
        scan.width !== null && scan.height !== null
            ? `${scan.width}×${scan.height}`
            : 'brak danych';

    return (
        <>
            <Head title={scan.original_name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={scan.original_name}
                        description={`${new Date(scan.uploaded_at).toLocaleDateString('pl-PL')} · ${sizeInMb} MB · ${dimensions}`}
                    />

                    <DeleteScanDialog
                        scan={scan}
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

                <ScanViewer
                    src={ScanController.file.url(scan.id)}
                    alt={scan.original_name}
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
                                        <Link
                                            href={PersonController.show(
                                                confirmation.person.id,
                                            )}
                                            className="text-primary underline-offset-4 hover:underline"
                                        >
                                            {confirmation.event_label} —{' '}
                                            {confirmation.person.display_name}
                                        </Link>
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
