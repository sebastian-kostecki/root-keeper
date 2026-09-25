import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/heading';
import { PersonFormFields } from '@/components/persons/person-form-fields';
import { Button } from '@/components/ui/button';
import { index as personsIndex } from '@/routes/persons';
import type { PersonFormData, Religion } from '@/types';

type Props = {
    person: PersonFormData;
    religions: Religion[];
};

export default function Edit({ person, religions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Osoby', href: personsIndex() },
            {
                title: person.display_name,
                href: PersonController.show(person.id),
            },
            { title: 'Edytuj', href: PersonController.edit(person.id) },
        ],
    });

    return (
        <>
            <Head title={`Edytuj ${person.display_name}`} />

            <div className="max-w-2xl space-y-6">
                <Heading title="Edytuj osobę" />

                <Form
                    {...PersonController.update.form(person.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PersonFormFields
                                religions={religions}
                                errors={errors}
                                person={person}
                            />

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Zapisz</Button>

                                <Button variant="outline" asChild>
                                    <Link
                                        href={PersonController.show(person.id)}
                                    >
                                        Anuluj
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
