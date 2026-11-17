import { Link } from '@inertiajs/react';
import { EllipsisIcon } from 'lucide-react';
import {
    ConfirmedMark,
    ProbandBadge,
} from '@/components/persons/person-list-parts';
import { PersonAvatar } from '@/components/persons/person-avatar';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { EmptyCell, SortableHead } from '@/components/table-parts';
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
import { focusRing } from '@/lib/focus-ring';
import { personPhotoUrl } from '@/lib/person-photo';
import { sortPersons, surnameVariantHint } from '@/lib/persons-index';
import type { PersonsSort } from '@/lib/persons-index';
import { columnOf } from '@/lib/table-sort';
import { stickyCell, stickyHeadCell } from '@/lib/table-styles';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { IndexPersonEvent, Person } from '@/types';

type Column = 'nazwisko' | 'urodzenie' | 'zgon';

type Props = {
    persons: Person[];
    rootPersonId: number | null;
    sort: PersonsSort;
    query: string;
    onSortChange: (sort: PersonsSort) => void;
};

const columnLabels: Record<Column, string> = {
    nazwisko: 'Osoba',
    urodzenie: 'Urodzenie',
    zgon: 'Zgon',
};

function EventCell({
    event,
    isDeath = false,
}: {
    event: IndexPersonEvent | null;
    isDeath?: boolean;
}) {
    const date = event?.date || (isDeath && event ? 'data nieznana' : null);

    if (!event || (date === null && !event.place)) {
        return <EmptyCell />;
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

/** View B: sortable table; sorting is done in the browser over the loaded list. */
export function PersonsTable({
    persons,
    rootPersonId,
    sort,
    query,
    onSortChange,
}: Props) {
    const rows = sortPersons(persons, sort);
    const column = columnOf<Column>(sort);
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
                        <SortableHead<Column>
                            column="nazwisko"
                            label={columnLabels.nazwisko}
                            sort={sort}
                            onSortChange={onSortChange}
                            className={cn(stickyHeadCell, 'px-4')}
                        />
                        <SortableHead<Column>
                            column="urodzenie"
                            label={columnLabels.urodzenie}
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <SortableHead<Column>
                            column="zgon"
                            label={columnLabels.zgon}
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <TableHead>Wyznanie</TableHead>
                        <TableHead className="w-12">
                            <span className="sr-only">Akcje</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((person) => {
                        const variants = surnameVariantHint(person, query);

                        return (
                            <TableRow key={person.id} className="group">
                                <TableCell
                                    className={cn(stickyCell, 'px-4 py-3')}
                                >
                                    <PersonPreviewLink
                                        personId={person.id}
                                        className={cn(
                                            focusRing,
                                            '-m-1 flex items-center gap-3 rounded-md p-1',
                                        )}
                                    >
                                        <PersonAvatar
                                            name={person.display_name}
                                            gender={
                                                person.gender?.value ?? null
                                            }
                                            size="sm"
                                            announceGender={false}
                                            photoUrl={personPhotoUrl(
                                                person.id,
                                                person.photo_version,
                                            )}
                                        />
                                        <span className="min-w-0 whitespace-normal">
                                            <span className="font-medium">
                                                {person.display_name}
                                            </span>
                                            {variants.length > 0 && (
                                                <span className="text-muted-foreground block text-xs">
                                                    zapis: {variants.join(', ')}
                                                </span>
                                            )}
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
                                    {person.religion ? (
                                        person.religion.label
                                    ) : (
                                        <EmptyCell />
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
                                                <Link
                                                    href={treeShow(person.id)}
                                                >
                                                    Pokaż na drzewie
                                                </Link>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}
