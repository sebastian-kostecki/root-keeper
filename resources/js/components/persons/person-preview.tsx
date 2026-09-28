import { Link } from '@inertiajs/react';
import { DeletePersonDialog } from '@/components/persons/delete-person-dialog';
import { EventCard } from '@/components/persons/event-card';
import { MarriagesSection } from '@/components/persons/marriages-section';
import { PersonFormDialog } from '@/components/persons/person-form-dialog';
import { RelativesCard } from '@/components/persons/relatives-card';
import { SetRootPersonButton } from '@/components/tree/set-root-person-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { show as treeShow } from '@/routes/tree';
import type { PersonPreviewPayload, RelationCandidate, Scan } from '@/types';

type Props = {
    preview: PersonPreviewPayload;
    availableScans?: Scan[];
    relationCandidates?: RelationCandidate[];
};

export function PersonPreview({
    preview,
    availableScans,
    relationCandidates,
}: Props) {
    const { person, personForm, genders, religions, isRootPerson } = preview;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-end gap-2">
                {isRootPerson ? (
                    <Badge variant="secondary">Probant</Badge>
                ) : (
                    <SetRootPersonButton personId={person.id} />
                )}

                <Button variant="outline" size="sm" asChild>
                    <Link href={treeShow(person.id)}>Pokaż w drzewie</Link>
                </Button>

                <PersonFormDialog
                    mode="edit"
                    person={personForm}
                    genders={genders}
                    religions={religions}
                    trigger={
                        <Button variant="outline" size="sm">
                            Edytuj
                        </Button>
                    }
                />

                <DeletePersonDialog
                    person={person}
                    isRootPerson={isRootPerson}
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

            <Card>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Miejsce zamieszkania
                        </p>
                        <p>{person.residence ?? 'brak danych'}</p>
                    </div>

                    <div>
                        <p className="text-muted-foreground text-sm">Płeć</p>
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
    );
}
