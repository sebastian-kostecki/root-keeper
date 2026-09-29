import Heading from '@/components/heading';
import { EventCard } from '@/components/persons/event-card';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { MarriageFormDialog } from '@/components/persons/marriage-form-dialog';
import { RemoveMarriageDialog } from '@/components/persons/remove-marriage-dialog';
import { Button } from '@/components/ui/button';
import type { PersonPreview, Scan } from '@/types';

type Props = {
    person: PersonPreview;
    availableScans: Scan[] | undefined;
};

export function MarriagesSection({ person, availableScans }: Props) {
    return (
        <div className="space-y-4">
            <Heading variant="small" title="Małżeństwa" />

            {person.marriages.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    Brak znanych małżeństw. Dodasz je na drzewie.
                </p>
            ) : (
                <div className="space-y-4">
                    {person.marriages.map((marriage) => {
                        const spouseName =
                            marriage.spouse?.display_name ??
                            'Nieznany małżonek';

                        return (
                            <EventCard
                                key={marriage.id}
                                title={`Małżeństwo z ${spouseName}`}
                                header={
                                    <>
                                        Małżeństwo z{' '}
                                        {marriage.spouse ? (
                                            <PersonPreviewLink
                                                personId={marriage.spouse.id}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {spouseName}
                                                {(marriage.spouse.birth_date ||
                                                    marriage.spouse
                                                        .death_date) && (
                                                    <span className="text-muted-foreground ml-2 text-sm font-normal">
                                                        {[
                                                            marriage.spouse
                                                                .birth_date &&
                                                                `ur. ${marriage.spouse.birth_date}`,
                                                            marriage.spouse
                                                                .death_date &&
                                                                `zm. ${marriage.spouse.death_date}`,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </span>
                                                )}
                                            </PersonPreviewLink>
                                        ) : (
                                            spouseName
                                        )}
                                    </>
                                }
                                headerActions={
                                    <>
                                        <MarriageFormDialog
                                            marriage={marriage}
                                            trigger={
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    Edytuj
                                                </Button>
                                            }
                                        />
                                        <RemoveMarriageDialog
                                            marriage={marriage}
                                            personName={person.display_name}
                                            spouseName={spouseName}
                                            trigger={
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                >
                                                    Usuń małżeństwo
                                                </Button>
                                            }
                                        />
                                    </>
                                }
                                event={marriage}
                                availableScans={availableScans}
                            />
                        );
                    })}
                </div>
            )}
        </div>
    );
}
