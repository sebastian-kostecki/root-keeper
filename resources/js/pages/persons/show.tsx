import { Head, setLayoutProps } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as personsIndex } from '@/routes/persons';
import type { Person } from '@/types';

type Props = {
    person: Person;
};

export default function Show({ person }: Props) {
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

                <Card>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground text-sm">
                                Miejsce zamieszkania
                            </p>
                            <p>{person.residence ?? 'brak danych'}</p>
                        </div>

                        <div>
                            <p className="text-muted-foreground text-sm">
                                Wyznanie
                            </p>
                            {person.religion ? (
                                <Badge variant="secondary">
                                    {person.religion.label}
                                </Badge>
                            ) : (
                                <p>brak danych</p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Urodzenie</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground text-sm">
                                Data
                            </p>
                            <p>{person.birth?.date ?? 'brak danych'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                Miejsce
                            </p>
                            <p>{person.birth?.place ?? 'brak danych'}</p>
                        </div>
                    </CardContent>
                </Card>

                {person.death && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Zgon</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-muted-foreground text-sm">
                                    Data
                                </p>
                                <p>{person.death.date ?? 'brak danych'}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">
                                    Miejsce
                                </p>
                                <p>{person.death.place ?? 'brak danych'}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
