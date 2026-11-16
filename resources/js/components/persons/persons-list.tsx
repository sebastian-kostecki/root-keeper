import { ChevronRightIcon } from 'lucide-react';
import {
    ConfirmedMark,
    ProbandBadge,
} from '@/components/persons/person-list-parts';
import { PersonAvatar } from '@/components/persons/person-avatar';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { Button } from '@/components/ui/button';
import { focusRing } from '@/lib/focus-ring';
import { personPhotoUrl } from '@/lib/person-photo';
import {
    formatEventMeta,
    groupBySurnameInitial,
    peopleCount,
    surnameVariantHint,
} from '@/lib/persons-index';
import { cn } from '@/lib/utils';
import type { IndexPersonEvent, Person } from '@/types';

type Props = {
    persons: Person[];
    rootPersonId: number | null;
    query: string;
};

function EventMeta({
    prefix,
    event,
}: {
    prefix: 'ur.' | 'zm.';
    event: IndexPersonEvent | null;
}) {
    const meta = formatEventMeta(prefix, event);

    if (meta === null) {
        return null;
    }

    return (
        <span>
            {meta}
            {event?.date && event.is_confirmed && (
                <>
                    {' '}
                    <ConfirmedMark />
                </>
            )}
        </span>
    );
}

function PersonRow({
    person,
    isProband,
    query,
}: {
    person: Person;
    isProband: boolean;
    query: string;
}) {
    const birth = formatEventMeta('ur.', person.birth);
    const death = formatEventMeta('zm.', person.death);
    const variants = surnameVariantHint(person, query);

    return (
        <PersonPreviewLink
            personId={person.id}
            className={cn(
                focusRing,
                'hover:bg-accent/50 focus-visible:bg-accent/50 flex items-center gap-3 px-4 py-3 transition-colors focus-visible:ring-inset',
            )}
        >
            <PersonAvatar
                name={person.display_name}
                gender={person.gender?.value ?? null}
                size="sm"
                announceGender={false}
                photoUrl={personPhotoUrl(person.id, person.photo_version)}
            />
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="font-medium break-words">
                        {person.display_name}
                    </span>
                    {isProband && <ProbandBadge />}
                </span>
                {variants.length > 0 && (
                    <span className="text-muted-foreground mt-0.5 block text-sm">
                        zapis: {variants.join(', ')}
                    </span>
                )}
                {(birth !== null || death !== null) && (
                    <span className="text-muted-foreground mt-0.5 flex flex-wrap gap-x-1.5 text-sm">
                        <EventMeta prefix="ur." event={person.birth} />
                        {birth !== null && death !== null && (
                            <span aria-hidden="true">·</span>
                        )}
                        <EventMeta prefix="zm." event={person.death} />
                    </span>
                )}
            </span>
            <ChevronRightIcon
                aria-hidden="true"
                className="text-muted-foreground size-4 shrink-0"
            />
        </PersonPreviewLink>
    );
}

/** View A: people grouped under the first letter of the surname. */
export function PersonsList({ persons, rootPersonId, query }: Props) {
    const groups = groupBySurnameInitial(persons);

    return (
        <div className="space-y-6">
            <nav
                aria-label="Przejdź do litery"
                className="flex flex-wrap gap-1.5"
            >
                {groups.map((group) => (
                    <Button
                        key={group.key}
                        variant="outline"
                        size="sm"
                        className="min-w-8"
                        asChild
                    >
                        <a href={`#grupa-${group.key}`}>{group.title}</a>
                    </Button>
                ))}
            </nav>

            {groups.map((group) => (
                <section
                    key={group.key}
                    id={`grupa-${group.key}`}
                    aria-labelledby={`grupa-${group.key}-tytul`}
                    className="scroll-mt-20 space-y-2"
                >
                    <div className="flex items-baseline justify-between px-1">
                        <h2
                            id={`grupa-${group.key}-tytul`}
                            className="text-sm font-semibold"
                        >
                            {group.title}
                        </h2>
                        <span className="text-muted-foreground text-xs">
                            {peopleCount(group.persons.length)}
                        </span>
                    </div>
                    <div className="divide-y overflow-hidden rounded-lg border">
                        {group.persons.map((person) => (
                            <PersonRow
                                key={person.id}
                                person={person}
                                isProband={person.id === rootPersonId}
                                query={query}
                            />
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
