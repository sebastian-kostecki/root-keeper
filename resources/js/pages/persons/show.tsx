import { Head, Link, setLayoutProps } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import { DeletePersonDialog } from '@/components/persons/delete-person-dialog';
import { EventCard } from '@/components/persons/event-card';
import { MarriagesSection } from '@/components/persons/marriages-section';
import { RelativesCard } from '@/components/persons/relatives-card';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as personsIndex } from '@/routes/persons';
import type { PersonPreview, RelationCandidate, Scan } from '@/types';

type Props = {
    person: PersonPreview;
    availableScans?: Scan[];
    relationCandidates?: RelationCandidate[];
};

export default function Show({
    person,
    availableScans,
    relationCandidates,
}: Props) {
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

                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={PersonController.edit(person.id)}>
                                Edytuj
                            </Link>
                        </Button>

                        <DeletePersonDialog
                            person={person}
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
                                Płeć
                            </p>
                            {person.gender ? (
                                <Badge variant="secondary">
                                    {person.gender.label}
                                </Badge>
                            ) : (
                                <p>brak danych</p>
                            )}
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

                <RelativesCard
                    kind="parent"
                    person={person}
                    relatives={person.parents}
                    candidates={relationCandidates}
                />

                <RelativesCard
                    kind="child"
                    person={person}
                    relatives={person.children}
                    candidates={relationCandidates}
                />

                {person.birth && (
                    <EventCard
                        title="Urodzenie"
                        event={person.birth}
                        availableScans={availableScans}
                    />
                )}

                <MarriagesSection
                    person={person}
                    availableScans={availableScans}
                    candidates={relationCandidates}
                />

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
