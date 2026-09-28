import { Link } from '@inertiajs/react';
import { Eye, Mars, Venus } from 'lucide-react';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { SheetPerson } from '@/types';

type Props = {
    person: SheetPerson;
    /** Union to preselect after re-centring on this person; omitted for none. */
    spouse?: string;
    isFocus?: boolean;
};

const strip = {
    male: 'border-l-gender-male',
    female: 'border-l-gender-female',
    unknown: 'border-l-gender-unknown',
} as const;

function CardBody({ person }: { person: SheetPerson }) {
    return (
        <>
            <span className="flex items-start gap-1.5">
                {person.gender === 'male' && (
                    <Mars
                        role="img"
                        aria-label="mężczyzna"
                        className="text-gender-male mt-0.5 size-4 shrink-0"
                    />
                )}
                {person.gender === 'female' && (
                    <Venus
                        role="img"
                        aria-label="kobieta"
                        className="text-gender-female mt-0.5 size-4 shrink-0"
                    />
                )}
                <span className="text-sm leading-tight font-medium break-words">
                    {person.display_name}
                </span>
            </span>
            {person.lifespan && (
                <span className="text-muted-foreground mt-1 block text-xs">
                    {person.lifespan}
                </span>
            )}
        </>
    );
}

export function SheetPersonCard({ person, spouse, isFocus = false }: Props) {
    const body = cn(
        'bg-card text-card-foreground block h-full min-h-16 rounded-md border border-l-4 p-2 pr-9 shadow-xs',
        strip[person.gender ?? 'unknown'],
    );

    return (
        <div className="relative h-full">
            {isFocus ? (
                <div className={cn(body, 'ring-primary ring-2')}>
                    <CardBody person={person} />
                </div>
            ) : (
                <Link
                    href={treeShow(person.id, {
                        query: spouse === undefined ? undefined : { spouse },
                    })}
                    className={cn(
                        body,
                        'hover:bg-accent/50 focus-visible:ring-ring focus-visible:ring-[3px] focus-visible:outline-none',
                    )}
                >
                    <CardBody person={person} />
                </Link>
            )}

            <Button
                variant="ghost"
                size="icon"
                className="absolute top-1 right-1 size-7"
                asChild
            >
                <PersonPreviewLink
                    personId={person.id}
                    aria-label={`Podgląd: ${person.display_name}`}
                    title="Podgląd"
                >
                    <Eye />
                </PersonPreviewLink>
            </Button>
        </div>
    );
}
