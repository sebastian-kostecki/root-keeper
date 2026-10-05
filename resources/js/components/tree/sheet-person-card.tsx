import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import {
    cardFocus,
    cardInteractive,
    cardSurface,
} from '@/components/tree/card-styles';
import { formatAge } from '@/lib/age';
import { genderCard } from '@/lib/gender-styles';
import { cn } from '@/lib/utils';
import type { SheetPerson } from '@/types';

type Props = {
    person: SheetPerson;
    isFocus?: boolean;
};

function CardBody({ person }: { person: SheetPerson }) {
    return (
        <>
            <span className="text-card-foreground text-base leading-tight font-semibold [overflow-wrap:anywhere] break-words">
                {person.gender === 'male' && (
                    <span className="sr-only">mężczyzna</span>
                )}
                {person.gender === 'female' && (
                    <span className="sr-only">kobieta</span>
                )}
                {person.display_name}
            </span>
            {person.lifespan && (
                <span className="text-tree-meta mt-1 block text-sm">
                    {person.lifespan}
                </span>
            )}
            {person.lifespan && person.age && (
                <span className="text-tree-meta block text-sm whitespace-nowrap">
                    {`(${formatAge(person.age)})`}
                </span>
            )}
        </>
    );
}

export function SheetPersonCard({ person, isFocus = false }: Props) {
    return (
        <div className="h-full">
            <PersonPreviewLink
                personId={person.id}
                aria-current={isFocus ? 'true' : undefined}
                className={cn(
                    cardSurface,
                    'text-card-foreground shadow-xs',
                    genderCard[person.gender ?? 'unknown'],
                    isFocus ? cardFocus : null,
                    cardInteractive,
                )}
            >
                <CardBody person={person} />
            </PersonPreviewLink>
        </div>
    );
}
