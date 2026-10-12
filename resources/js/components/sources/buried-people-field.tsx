import { useEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import InputError from '@/components/input-error';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { PickPersonDialog } from '@/components/persons/pick-person-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { BuriedPerson } from '@/types';

type Props = {
    defaults?: BuriedPerson[];
    errors: Partial<Record<string, string>>;
    /** A click on a person link; the page decides whether it may leave the form. */
    onPersonLinkClick?: (event: MouseEvent, personId: number) => void;
};

/** The list of people buried at a gravestone; the whole list is submitted as `buried_person_ids[]`. */
export function BuriedPeopleField({
    defaults = [],
    errors,
    onPersonLinkClick,
}: Props) {
    const [people, setPeople] = useState<BuriedPerson[]>(defaults);
    const [pickerOpen, setPickerOpen] = useState(false);
    const listRef = useRef<HTMLDivElement>(null);
    const previousPeople = useRef(people);

    // The Inertia form only notices native events. The container stays mounted, so the
    // event is also sent after the last person is removed (no hidden input is left then).
    useEffect(() => {
        if (previousPeople.current === people) {
            return;
        }

        previousPeople.current = people;
        listRef.current?.dispatchEvent(new Event('change', { bubbles: true }));
    }, [people]);

    const messages = [
        ...new Set(
            Object.entries(errors)
                .filter(([key]) => key.startsWith('buried_person_ids'))
                .map(([, message]) => message)
                .filter((message): message is string => message !== undefined),
        ),
    ];

    return (
        <div className="grid gap-2">
            <Label id="buried-people-label">Osoby pochowane</Label>

            <div
                ref={listRef}
                role="group"
                aria-labelledby="buried-people-label"
            >
                {people.map((person) => (
                    <input
                        key={person.id}
                        type="hidden"
                        name="buried_person_ids[]"
                        value={person.id}
                    />
                ))}

                {people.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Nikt nie jest jeszcze na liście.
                    </p>
                ) : (
                    <ul className="space-y-1">
                        {people.map((person) => (
                            <li
                                key={person.id}
                                className="flex items-center justify-between gap-2 text-sm"
                            >
                                <PersonPreviewLink
                                    personId={person.id}
                                    className="text-primary underline-offset-4 hover:underline"
                                    onClick={(event) =>
                                        onPersonLinkClick?.(event, person.id)
                                    }
                                >
                                    {person.display_name}
                                </PersonPreviewLink>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    aria-label={`Usuń z listy: ${person.display_name}`}
                                    onClick={() =>
                                        setPeople((current) =>
                                            current.filter(
                                                (known) =>
                                                    known.id !== person.id,
                                            ),
                                        )
                                    }
                                >
                                    Usuń z listy
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPickerOpen(true)}
                >
                    Dodaj osobę
                </Button>
            </div>

            {messages.map((message) => (
                <InputError key={message} message={message} />
            ))}

            <PickPersonDialog
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                excludedIds={people.map((person) => person.id)}
                onPick={(person) => {
                    setPeople((current) =>
                        current.some((known) => known.id === person.id)
                            ? current
                            : [...current, person],
                    );
                    setPickerOpen(false);
                }}
            />
        </div>
    );
}
