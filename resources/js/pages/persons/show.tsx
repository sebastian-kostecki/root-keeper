import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/heading';
import { PersonPreview } from '@/components/persons/person-preview';
import { index as personsIndex } from '@/routes/persons';
import type { PersonPreviewPayload } from '@/types';

export default function Show(preview: PersonPreviewPayload) {
    const { availableScans, relationCandidates } = usePage().props;
    const { person } = preview;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Osoby', href: personsIndex() },
            {
                title: person.display_name,
                href: PersonController.show(person.id),
            },
        ],
    });

    return (
        <>
            <Head title={person.display_name} />

            <div className="max-w-2xl space-y-6">
                <Heading title={person.display_name} />

                <PersonPreview
                    preview={preview}
                    availableScans={availableScans}
                    relationCandidates={relationCandidates}
                />
            </div>
        </>
    );
}
