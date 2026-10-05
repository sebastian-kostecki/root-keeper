import { EllipsisIcon } from 'lucide-react';
import { useId, useState } from 'react';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { RemoveMarriageDialog } from '@/components/persons/remove-marriage-dialog';
import { RemoveRelationDialog } from '@/components/persons/remove-relation-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { genderCard } from '@/lib/gender-styles';
import { formatLifespan } from '@/lib/lifespan';
import { cn } from '@/lib/utils';
import type { Marriage, PersonPreview, Relative } from '@/types';

type Props = {
    person: PersonPreview;
};

type PersonLike = {
    id: number;
    display_name: string;
    gender: 'male' | 'female' | null;
    birth_date: string | null;
    death_date: string | null;
};

/** Row menu with a single destructive item that opens a controlled dialog. */
function RowMenu({
    label,
    itemLabel,
    renderDialog,
}: {
    label: string;
    itemLabel: string;
    renderDialog: (
        open: boolean,
        onOpenChange: (open: boolean) => void,
    ) => React.ReactNode;
}) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8 shrink-0"
                        aria-label={label}
                    >
                        <EllipsisIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setIsOpen(true)}
                    >
                        {itemLabel}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            {renderDialog(isOpen, setIsOpen)}
        </>
    );
}

function PersonRowBody({
    person,
    extra,
}: {
    person: PersonLike;
    extra?: React.ReactNode;
}) {
    const getInitials = useInitials();
    const lifespan = formatLifespan(person.birth_date, person.death_date);

    return (
        <>
            <span
                aria-hidden="true"
                className="bg-background/60 flex size-9 shrink-0 items-center justify-center rounded-full border text-sm font-semibold"
            >
                {getInitials(person.display_name)}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-medium [overflow-wrap:anywhere]">
                    {person.display_name}
                </span>
                {lifespan && (
                    <span className="text-tree-meta block text-xs">
                        {lifespan}
                    </span>
                )}
                {extra}
            </span>
        </>
    );
}

function PersonRow({
    person,
    menu,
    extra,
}: {
    person: PersonLike;
    menu: React.ReactNode;
    extra?: React.ReactNode;
}) {
    return (
        <li
            className={cn(
                'flex items-center gap-1 rounded-xl border pr-2',
                genderCard[person.gender ?? 'unknown'],
            )}
        >
            <PersonPreviewLink
                personId={person.id}
                className="focus-visible:ring-ring flex min-w-0 flex-1 items-center gap-3 rounded-xl px-3 py-2.5 focus-visible:ring-[3px] focus-visible:outline-none"
            >
                <PersonRowBody person={person} extra={extra} />
            </PersonPreviewLink>
            {menu}
        </li>
    );
}

function Section({
    title,
    empty,
    isEmpty,
    children,
}: {
    title: string;
    empty: string;
    isEmpty: boolean;
    children: React.ReactNode;
}) {
    const headingId = useId();

    return (
        <section aria-labelledby={headingId} className="space-y-3">
            <h3
                id={headingId}
                className="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
            >
                {title}
            </h3>
            {isEmpty ? (
                <p className="text-muted-foreground text-sm">{empty}</p>
            ) : (
                <ul className="space-y-2">{children}</ul>
            )}
        </section>
    );
}

function RelativeRow({
    kind,
    person,
    relative,
}: {
    kind: 'parent' | 'child';
    person: PersonPreview;
    relative: Relative;
}) {
    return (
        <PersonRow
            person={relative}
            menu={
                <RowMenu
                    label={`Więcej działań: ${relative.display_name}`}
                    itemLabel="Usuń relację"
                    renderDialog={(open, onOpenChange) => (
                        <RemoveRelationDialog
                            relationId={relative.relation_id}
                            parentName={
                                kind === 'parent'
                                    ? relative.display_name
                                    : person.display_name
                            }
                            childName={
                                kind === 'parent'
                                    ? person.display_name
                                    : relative.display_name
                            }
                            open={open}
                            onOpenChange={onOpenChange}
                        />
                    )}
                />
            }
        />
    );
}

function MarriageRow({
    person,
    marriage,
}: {
    person: PersonPreview;
    marriage: Marriage;
}) {
    const spouseName = marriage.spouse?.display_name ?? 'Nieznany małżonek';
    const details = [marriage.date, marriage.place].filter(Boolean).join(', ');

    const extra = (
        <span className="mt-1 flex flex-wrap items-center gap-2">
            {details && (
                <span className="text-muted-foreground text-xs">{details}</span>
            )}
            <EventSourceStatus kinds={marriage.confirmed_kinds} />
        </span>
    );

    const menu = (
        <RowMenu
            label={`Więcej działań: małżeństwo z ${spouseName}`}
            itemLabel="Usuń małżeństwo"
            renderDialog={(open, onOpenChange) => (
                <RemoveMarriageDialog
                    marriage={marriage}
                    personName={person.display_name}
                    spouseName={spouseName}
                    open={open}
                    onOpenChange={onOpenChange}
                />
            )}
        />
    );

    if (marriage.spouse === null) {
        return (
            <li
                className={cn(
                    'flex items-center gap-3 rounded-xl border px-3 py-2.5',
                    genderCard.unknown,
                )}
            >
                <span className="min-w-0 flex-1">
                    <span className="block font-medium">{spouseName}</span>
                    {extra}
                </span>
                {menu}
            </li>
        );
    }

    return <PersonRow person={marriage.spouse} menu={menu} extra={extra} />;
}

export function FamilyTab({ person }: Props) {
    return (
        <div className="space-y-6">
            <Section
                title="Rodzice"
                empty="Brak znanych rodziców. Dodasz ich na drzewie."
                isEmpty={person.parents.length === 0}
            >
                {person.parents.map((relative) => (
                    <RelativeRow
                        key={relative.relation_id}
                        kind="parent"
                        person={person}
                        relative={relative}
                    />
                ))}
            </Section>

            <Section
                title="Małżeństwa"
                empty="Brak znanych małżeństw. Dodasz je na drzewie."
                isEmpty={person.marriages.length === 0}
            >
                {person.marriages.map((marriage) => (
                    <MarriageRow
                        key={marriage.id}
                        person={person}
                        marriage={marriage}
                    />
                ))}
            </Section>

            <Section
                title="Dzieci"
                empty="Brak znanych dzieci. Dodasz je na drzewie."
                isEmpty={person.children.length === 0}
            >
                {person.children.map((relative) => (
                    <RelativeRow
                        key={relative.relation_id}
                        kind="child"
                        person={person}
                        relative={relative}
                    />
                ))}
            </Section>

            <p className="text-muted-foreground text-sm">
                Krewnych dodajesz na drzewie, wybierając osobę i dodając
                rodzica, małżonka lub dziecko.
            </p>
        </div>
    );
}
