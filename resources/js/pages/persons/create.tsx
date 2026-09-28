import { Form, Head, Link } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/heading';
import { PersonFormFields } from '@/components/persons/person-form-fields';
import { Button } from '@/components/ui/button';
import { index as personsIndex } from '@/routes/persons';
import type { BreadcrumbItem, Gender, Religion } from '@/types';

type Props = {
    genders: Gender[];
    religions: Religion[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Osoby', href: personsIndex() },
    { title: 'Dodaj osobę', href: PersonController.create() },
];

export default function Create({ genders, religions }: Props) {
    return (
        <>
            <Head title="Dodaj osobę" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Dodaj osobę"
                    description="Podaj dane osoby, którą chcesz dodać do drzewa"
                />

                <Form {...PersonController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <PersonFormFields
                                genders={genders}
                                religions={religions}
                                errors={errors}
                            />

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="create-person-button"
                                >
                                    Zapisz
                                </Button>

                                <Button variant="outline" asChild>
                                    <Link href={personsIndex()}>Anuluj</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Create.layout = {
    breadcrumbs,
};
