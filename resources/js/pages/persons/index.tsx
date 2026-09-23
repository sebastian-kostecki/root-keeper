import { Head, Link } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as personsIndex } from '@/routes/persons';
import type { BreadcrumbItem, Person } from '@/types';

type Props = {
    persons: Person[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Osoby',
        href: personsIndex(),
    },
];

export default function Index({ persons }: Props) {
    return (
        <>
            <Head title="Osoby" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Osoby"
                        description="Osoby zapisane w Twoim drzewie genealogicznym"
                    />

                    <Button asChild>
                        <Link href={PersonController.create()}>
                            Dodaj osobę
                        </Link>
                    </Button>
                </div>

                {persons.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground text-sm">
                            Nie dodano jeszcze żadnej osoby. Zacznij od dodania
                            pierwszej osoby do drzewa.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3">
                        {persons.map((person) => (
                            <Link
                                key={person.id}
                                href={PersonController.show(person.id)}
                            >
                                <Card className="hover:bg-accent/50 transition-colors">
                                    <CardContent className="flex items-center justify-between">
                                        <span className="font-medium">
                                            {person.display_name}
                                        </span>
                                        <span className="text-muted-foreground text-sm">
                                            {[
                                                person.birth?.date &&
                                                    `ur. ${person.birth.date}`,
                                                person.death?.date &&
                                                    `zm. ${person.death.date}`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
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
