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
import { panelVisitOptions } from '@/lib/side-panel-url';
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
                        <TableHead className="px-4">Nazwa</TableHead>
                        <TableHead>Opis</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {localities.map((locality) => (
                        <TableRow
                            key={locality.id}
                            data-state={
                                locality.id === openId ? 'selected' : undefined
                            }
                        >
                            <TableCell className="px-4 py-3">
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
                                        'focus-visible:ring-ring/50 -m-1 block rounded-md p-1 font-medium outline-none focus-visible:ring-[3px]',
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
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
