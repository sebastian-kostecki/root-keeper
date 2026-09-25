import { Head, Link, setLayoutProps } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import { EventCard } from '@/components/persons/event-card';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as personsIndex } from '@/routes/persons';
import type { PersonPreview, Scan } from '@/types';

type Props = {
    person: PersonPreview;
    availableScans?: Scan[];
};

export default function Show({ person, availableScans }: Props) {
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
                <div className="flex items-center justify-between gap-4">
                    <Heading title={person.display_name} />

                    <Button variant="outline" size="sm" asChild>
                        <Link href={PersonController.edit(person.id)}>
                            Edytuj
                        </Link>
                    </Button>
                </div>

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

                {person.birth && (
                    <EventCard
                        title="Urodzenie"
                        event={person.birth}
                        availableScans={availableScans}
                    />
                )}

                {person.death && (
                    <EventCard
                        title="Zgon"
                        event={person.death}
                        availableScans={availableScans}
                    />
                )}
            </div>
        </>
    );
}
