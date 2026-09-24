import { Head, setLayoutProps } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import Heading from '@/components/heading';
import { ScanViewer } from '@/components/scans/scan-viewer';
import { index as scansIndex } from '@/routes/scans';
import type { Scan } from '@/types';

type Props = {
    scan: Scan;
};

export default function Show({ scan }: Props) {
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
                <Heading
                    title={scan.original_name}
                    description={`${new Date(scan.uploaded_at).toLocaleDateString('pl-PL')} · ${sizeInMb} MB · ${dimensions}`}
                />

                <ScanViewer
                    src={ScanController.file.url(scan.id)}
                    alt={scan.original_name}
                />
            </div>
        </>
    );
}
