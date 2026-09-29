import { ChevronRightIcon } from 'lucide-react';
import {
    ConfirmedMark,
    ProbandBadge,
} from '@/components/persons/person-list-parts';
import { PersonAvatar } from '@/components/persons/person-avatar';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import {
    formatEventMeta,
    groupBySurnameInitial,
    peopleCount,
} from '@/lib/persons-index';
import type { IndexPersonEvent, Person } from '@/types';

type Props = {
    persons: Person[];
    rootPersonId: number | null;
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
}: {
    person: Person;
    isProband: boolean;
}) {
    const birth = formatEventMeta('ur.', person.birth);
    const death = formatEventMeta('zm.', person.death);

    return (
        <PersonPreviewLink
            personId={person.id}
            className="hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:ring-ring/50 flex items-center gap-3 px-4 py-3 transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-inset"
        >
            <PersonAvatar
                name={person.display_name}
                gender={person.gender?.value ?? null}
                size="sm"
                announceGender={false}
            />
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="font-medium break-words">
                        {person.display_name}
                    </span>
                    {isProband && <ProbandBadge />}
                </span>
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
export function PersonsList({ persons, rootPersonId }: Props) {
    const groups = groupBySurnameInitial(persons);

    return (
        <div className="space-y-6">
            <nav
                aria-label="Przejdź do litery"
                className="flex flex-wrap gap-1.5"
            >
                {groups.map((group) => (
                    <a
                        key={group.key}
                        href={`#grupa-${group.key}`}
                        className="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring/50 inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px]"
                    >
                        {group.title}
                    </a>
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
                            />
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
