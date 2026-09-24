import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Props = {
    scan: unknown;
};

export default function Show({ scan }: Props) {
    return (
        <>
            <Head title="Skan" />

            <div className="space-y-6">
                <Heading title="Skan" />
                <pre>{JSON.stringify(scan, null, 2)}</pre>
            </div>
        </>
    );
}
