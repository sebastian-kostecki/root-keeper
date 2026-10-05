import { Link } from '@inertiajs/react';
import { EllipsisIcon } from 'lucide-react';
import type { MouseEvent } from 'react';
import { EmptyCell, SortableHead } from '@/components/table-parts';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
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
import { sourceKindText } from '@/lib/source-description';
import { eventsLabel, sortSources } from '@/lib/sources-index';
import type { SourcesSort, SourcesSortColumn } from '@/lib/sources-index';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { columnOf } from '@/lib/table-sort';
import { stickyCell, stickyHeadCell } from '@/lib/table-styles';
import { cn } from '@/lib/utils';
import type { IndexSource } from '@/types';

type Props = {
    sources: IndexSource[];
    sort: SourcesSort;
    onSortChange: (sort: SourcesSort) => void;
    openId: number | null;
    hrefFor: (id: number) => string;
    onOpen: (event: MouseEvent<Element>, id: number) => void;
};

const columnLabels: Record<SourcesSortColumn, string> = {
    nazwa: 'Źródło',
    rodzaj: 'Rodzaj',
    rok: 'Rok',
    potwierdza: 'Potwierdza',
    dodano: 'Dodano',
};

function formatDate(uploadedAt: string): string {
    const date = new Date(uploadedAt);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleDateString('pl-PL');
}

/** View B: sortable table; sorting is done in the browser over the loaded list. */
export function SourcesTable({
    sources,
    sort,
    onSortChange,
    openId,
    hrefFor,
    onOpen,
}: Props) {
    const rows = sortSources(sources, sort);
    const column = columnOf<SourcesSortColumn>(sort);
    const direction = sort.startsWith('-') ? 'malejąco' : 'rosnąco';

    return (
        <div className="rounded-lg border">
            <Table>
                <TableCaption className="sr-only">
                    Źródła w drzewie, posortowane {direction} wg kolumny{' '}
                    {columnLabels[column]}. Nagłówki Źródło, Rodzaj, Rok,
                    Potwierdza i Dodano są przyciskami sortowania.
                </TableCaption>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <SortableHead<SourcesSortColumn>
                            column="nazwa"
                            label={columnLabels.nazwa}
                            sort={sort}
                            onSortChange={onSortChange}
                            className={cn(stickyHeadCell, 'px-4')}
                        />
                        <SortableHead<SourcesSortColumn>
                            column="rodzaj"
                            label={columnLabels.rodzaj}
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <SortableHead<SourcesSortColumn>
                            column="rok"
                            label={columnLabels.rok}
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <TableHead>Parafia</TableHead>
                        <TableHead>Archiwum</TableHead>
                        <SortableHead<SourcesSortColumn>
                            column="potwierdza"
                            label={columnLabels.potwierdza}
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <SortableHead<SourcesSortColumn>
                            column="dodano"
                            label={columnLabels.dodano}
                            sort={sort}
                            onSortChange={onSortChange}
                        />
                        <TableHead className="w-12">
                            <span className="sr-only">Akcje</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((source) => {
                        const events = eventsLabel(source.events_count);

                        return (
                            <TableRow
                                key={source.id}
                                className="group"
                                data-state={
                                    source.id === openId
                                        ? 'selected'
                                        : undefined
                                }
                            >
                                <TableCell
                                    className={cn(stickyCell, 'px-4 py-3')}
                                >
                                    <Link
                                        href={hrefFor(source.id)}
                                        {...panelVisitOptions}
                                        aria-current={
                                            source.id === openId
                                                ? 'true'
                                                : undefined
                                        }
                                        onClick={(event) =>
                                            onOpen(event, source.id)
                                        }
                                        className="focus-visible:ring-ring/50 -m-1 flex items-center gap-3 rounded-md p-1 outline-none focus-visible:ring-[3px]"
                                    >
                                        <SourceThumbnail
                                            source={source}
                                            decorative
                                            iconClassName="size-5"
                                            className="size-10 shrink-0 rounded-md"
                                        />
                                        <span
                                            className={cn(
                                                'min-w-0 font-medium break-words whitespace-normal',
                                                source.id === openId &&
                                                    'underline',
                                            )}
                                        >
                                            {source.display_name}
                                        </span>
                                    </Link>
                                </TableCell>
                                <TableCell>{sourceKindText(source)}</TableCell>
                                <TableCell className="tabular-nums">
                                    {source.year ?? <EmptyCell />}
                                </TableCell>
                                <TableCell className="max-w-48">
                                    {source.parish_label ? (
                                        <span
                                            className="block truncate"
                                            title={source.parish_label}
                                        >
                                            {source.parish_label}
                                        </span>
                                    ) : (
                                        <EmptyCell />
                                    )}
                                </TableCell>
                                <TableCell className="max-w-48">
                                    {source.archive ? (
                                        <span
                                            className="block truncate"
                                            title={source.archive}
                                        >
                                            {source.archive}
                                        </span>
                                    ) : (
                                        <EmptyCell />
                                    )}
                                </TableCell>
                                <TableCell>
                                    {events ?? (
                                        <span className="text-muted-foreground">
                                            bez powiązań
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {formatDate(source.uploaded_at)}
                                </TableCell>
                                <TableCell className="text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Akcje: ${source.display_name}`}
                                            >
                                                <EllipsisIcon />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={hrefFor(source.id)}
                                                    {...panelVisitOptions}
                                                    onClick={(event) =>
                                                        onOpen(event, source.id)
                                                    }
                                                >
                                                    Otwórz
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
