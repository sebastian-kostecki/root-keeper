import { Link } from '@inertiajs/react';
import { EllipsisIcon } from 'lucide-react';
import { useState } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { EmptyCell, SortableHead } from '@/components/table-parts';
import { SourceEditDialog } from '@/components/sources/source-edit-dialog';
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
import { columnOf } from '@/lib/table-sort';
import { stickyCell, stickyHeadCell } from '@/lib/table-styles';
import { cn } from '@/lib/utils';
import type { IndexSource, SourceKind, SourceRecordType } from '@/types';

type Props = {
    sources: IndexSource[];
    sort: SourcesSort;
    onSortChange: (sort: SourcesSort) => void;
    kinds: { value: SourceKind; label: string }[];
    recordTypes: { value: SourceRecordType; label: string }[];
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
    kinds,
    recordTypes,
}: Props) {
    const [editing, setEditing] = useState<IndexSource | null>(null);
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
                        <TableHead>Nr wpisu</TableHead>
                        <TableHead>Pochodzenie</TableHead>
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
                            <TableRow key={source.id} className="group">
                                <TableCell
                                    className={cn(stickyCell, 'px-4 py-3')}
                                >
                                    <Link
                                        href={SourceController.show(source.id)}
                                        className="focus-visible:ring-ring/50 -m-1 flex items-center gap-3 rounded-md p-1 outline-none focus-visible:ring-[3px]"
                                    >
                                        <SourceThumbnail
                                            source={source}
                                            decorative
                                            iconClassName="size-5"
                                            className="size-10 shrink-0 rounded-md"
                                        />
                                        <span className="min-w-0 font-medium break-words whitespace-normal">
                                            {source.display_name}
                                        </span>
                                    </Link>
                                </TableCell>
                                <TableCell>{sourceKindText(source)}</TableCell>
                                <TableCell className="tabular-nums">
                                    {source.year ?? <EmptyCell />}
                                </TableCell>
                                <TableCell>
                                    {source.entry_number ? (
                                        source.entry_number
                                    ) : (
                                        <EmptyCell />
                                    )}
                                </TableCell>
                                <TableCell className="max-w-48">
                                    {source.provenance ? (
                                        <span
                                            className="block truncate"
                                            title={source.provenance}
                                        >
                                            {source.provenance}
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
                                                    href={SourceController.show(
                                                        source.id,
                                                    )}
                                                >
                                                    Otwórz
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setEditing(source)
                                                }
                                            >
                                                Edytuj
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>

            {editing !== null && (
                <SourceEditDialog
                    key={editing.id}
                    source={editing}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                    kinds={kinds}
                    recordTypes={recordTypes}
                />
            )}
        </div>
    );
}
