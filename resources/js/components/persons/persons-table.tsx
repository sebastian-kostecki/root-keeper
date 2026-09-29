import { Link } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronsUpDownIcon,
    EllipsisIcon,
} from 'lucide-react';
import {
    ConfirmedMark,
    ProbandBadge,
} from '@/components/persons/person-list-parts';
import { PersonAvatar } from '@/components/persons/person-avatar';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { sortPersons } from '@/lib/persons-index';
import type { PersonsSort } from '@/lib/persons-index';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { IndexPersonEvent, Person } from '@/types';

type Column = 'nazwisko' | 'urodzenie' | 'zgon';

type Props = {
    persons: Person[];
    rootPersonId: number | null;
    sort: PersonsSort;
    onSortChange: (sort: PersonsSort) => void;
};

const columnLabels: Record<Column, string> = {
    nazwisko: 'Osoba',
    urodzenie: 'Urodzenie',
    zgon: 'Zgon',
};

const stickyCell =
    'max-md:sticky max-md:left-0 max-md:z-10 max-md:w-56 max-md:max-w-56 max-md:bg-background max-md:group-hover:bg-muted max-md:shadow-[1px_0_0_var(--color-border)]';

function columnOf(sort: PersonsSort): Column {
    return (sort.startsWith('-') ? sort.slice(1) : sort) as Column;
}

/** Clicking the active column flips the direction; a new column starts ascending. */
function nextSort(current: PersonsSort, column: Column): PersonsSort {
    if (columnOf(current) === column) {
        return current.startsWith('-') ? column : (`-${column}` as PersonsSort);
    }

    return column;
}

function SortableHead({
    column,
    sort,
    onSortChange,
    className,
}: {
    column: Column;
    sort: PersonsSort;
    onSortChange: (sort: PersonsSort) => void;
    className?: string;
}) {
    const isActive = columnOf(sort) === column;
    const isDescending = sort.startsWith('-');
    const Icon = !isActive
        ? ChevronsUpDownIcon
        : isDescending
          ? ArrowDownIcon
          : ArrowUpIcon;

    return (
        <TableHead
            aria-sort={
                isActive ? (isDescending ? 'descending' : 'ascending') : 'none'
            }
            className={className}
        >
            <button
                type="button"
                onClick={() => onSortChange(nextSort(sort, column))}
                className="hover:bg-accent focus-visible:ring-ring/50 -mx-2 inline-flex h-8 items-center gap-1.5 rounded-md px-2 transition-colors outline-none focus-visible:ring-[3px]"
            >
                {columnLabels[column]}
                <Icon
                    aria-hidden="true"
                    className={cn(
                        'size-3.5',
                        !isActive && 'text-muted-foreground',
                    )}
                />
                <span className="sr-only">
                    {isActive
                        ? `, posortowano ${isDescending ? 'malejąco' : 'rosnąco'}`
                        : ', sortuj'}
                </span>
            </button>
        </TableHead>
    );
}

function EventCell({
    event,
    isDeath = false,
}: {
    event: IndexPersonEvent | null;
    isDeath?: boolean;
}) {
    const date = event?.date || (isDeath && event ? 'data nieznana' : null);

    if (!event || (date === null && !event.place)) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div>
            {date !== null && (
                <div className={cn(event.date && 'tabular-nums')}>
                    <span
                        className={cn(!event.date && 'text-muted-foreground')}
                    >
                        {date}
                    </span>
                    {event.date && event.is_confirmed && (
                        <>
                            {' '}
                            <ConfirmedMark />
                        </>
                    )}
                </div>
            )}
            {event.place && (
                <div className="text-muted-foreground text-xs">
                    {event.place}
                </div>
            )}
        </div>
    );
}

function Dash() {
    return <span className="text-muted-foreground">—</span>;
}

/** View B: sortable table; sorting is done in the browser over the loaded list. */
export function PersonsTable({
    persons,
    rootPersonId,
    sort,
    onSortChange,
}: Props) {
    const rows = sortPersons(persons, sort);
    const column = columnOf(sort);
    const direction = sort.startsWith('-') ? 'malejąco' : 'rosnąco';

    return (
        <div className="rounded-lg border">
            <Table>
                <TableCaption className="sr-only">
                    Osoby w drzewie, posortowane {direction} wg kolumny{' '}
                    {columnLabels[column]}. Nagłówki Osoba, Urodzenie i Zgon są
                    przyciskami sortowania.
                </TableCaption>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <SortableHead
                            column="nazwisko"
                            sort={sort}
                            onSortChange={onSortChange}
                            className={cn(
                                'max-md:bg-background px-4 max-md:sticky max-md:left-0 max-md:z-10 max-md:shadow-[1px_0_0_var(--color-border)]',
                            )}
                        />
                        <SortableHead
                            column="urodzenie"
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <SortableHead
                            column="zgon"
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <TableHead>Zamieszkanie</TableHead>
                        <TableHead>Wyznanie</TableHead>
                        <TableHead className="w-12">
                            <span className="sr-only">Akcje</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((person) => (
                        <TableRow key={person.id} className="group">
                            <TableCell className={cn(stickyCell, 'px-4 py-3')}>
                                <PersonPreviewLink
                                    personId={person.id}
                                    className="focus-visible:ring-ring/50 -m-1 flex items-center gap-3 rounded-md p-1 outline-none focus-visible:ring-[3px]"
                                >
                                    <PersonAvatar
                                        name={person.display_name}
                                        gender={person.gender?.value ?? null}
                                        size="sm"
                                        announceGender={false}
                                    />
                                    <span className="min-w-0 font-medium whitespace-normal">
                                        {person.display_name}
                                    </span>
                                    {person.id === rootPersonId && (
                                        <ProbandBadge />
                                    )}
                                </PersonPreviewLink>
                            </TableCell>
                            <TableCell>
                                <EventCell event={person.birth} />
                            </TableCell>
                            <TableCell>
                                <EventCell event={person.death} isDeath />
                            </TableCell>
                            <TableCell>
                                {person.residence ? person.residence : <Dash />}
                            </TableCell>
                            <TableCell>
                                {person.religion ? (
                                    person.religion.label
                                ) : (
                                    <Dash />
                                )}
                            </TableCell>
                            <TableCell className="text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Akcje: ${person.display_name}`}
                                        >
                                            <EllipsisIcon />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem asChild>
                                            <PersonPreviewLink
                                                personId={person.id}
                                            >
                                                Otwórz podgląd
                                            </PersonPreviewLink>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link href={treeShow(person.id)}>
                                                Pokaż na drzewie
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
