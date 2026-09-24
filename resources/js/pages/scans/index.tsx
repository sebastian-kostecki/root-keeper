import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Props = {
    scans: unknown[];
};

export default function Index({ scans }: Props) {
    return (
        <>
            <Head title="Skany" />

            <div className="space-y-6">
                <Heading title="Skany" />
                <pre>{JSON.stringify(scans, null, 2)}</pre>
            </div>
        </>
    );
}
