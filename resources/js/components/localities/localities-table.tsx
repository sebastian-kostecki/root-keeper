import { Link } from '@inertiajs/react';
import type { MouseEvent } from 'react';
import { EmptyCell } from '@/components/table-parts';
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
import { panelVisitOptions } from '@/lib/side-panel-url';
import { stickyCell, stickyHeadCell } from '@/lib/table-styles';
import { cn } from '@/lib/utils';
import type { Locality } from '@/types';

type Props = {
    localities: Locality[];
    openId: number | null;
    hrefFor: (id: number) => string;
    onRowClick: (event: MouseEvent<Element>, id: number) => void;
};

/** Alphabetical list; a row is a link that opens the locality in the side panel. */
export function LocalitiesTable({
    localities,
    openId,
    hrefFor,
    onRowClick,
}: Props) {
    return (
        <div className="rounded-lg border">
            <Table>
                <TableCaption className="sr-only">
                    Miejscowości w drzewie w kolejności alfabetycznej.
                </TableCaption>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <TableHead className={cn(stickyHeadCell, 'px-4')}>
                            Nazwa
                        </TableHead>
                        <TableHead>Opis</TableHead>
                        <TableHead className="px-4 text-right">
                            Użycia
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {localities.map((locality) => (
                        <TableRow
                            key={locality.id}
                            className="group"
                            data-state={
                                locality.id === openId ? 'selected' : undefined
                            }
                        >
                            <TableCell className={cn(stickyCell, 'px-4 py-3')}>
                                <Link
                                    href={hrefFor(locality.id)}
                                    {...panelVisitOptions}
                                    aria-current={
                                        locality.id === openId
                                            ? 'true'
                                            : undefined
                                    }
                                    onClick={(event) =>
                                        onRowClick(event, locality.id)
                                    }
                                    className={cn(
                                        focusRing,
                                        '-m-1 block rounded-md p-1 font-medium',
                                        locality.id === openId && 'underline',
                                    )}
                                >
                                    {locality.name}
                                </Link>
                            </TableCell>
                            <TableCell className="w-full max-w-0">
                                {locality.description ? (
                                    <span
                                        className="block truncate"
                                        title={locality.description}
                                    >
                                        {locality.description}
                                    </span>
                                ) : (
                                    <EmptyCell />
                                )}
                            </TableCell>
                            <TableCell className="px-4 text-right tabular-nums">
                                {locality.usage?.total ?? 0}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
