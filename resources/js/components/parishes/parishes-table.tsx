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
import type { Parish } from '@/types';

type Props = {
    parishes: Parish[];
    openId: number | null;
    hrefFor: (id: number) => string;
    onRowClick: (event: MouseEvent<Element>, id: number) => void;
};

/** Alphabetical list; a row is a link that opens the parish in the side panel. */
export function ParishesTable({
    parishes,
    openId,
    hrefFor,
    onRowClick,
}: Props) {
    return (
        <div className="rounded-lg border">
            <Table>
                <TableCaption className="sr-only">
                    Parafie i urzędy w drzewie w kolejności alfabetycznej.
                </TableCaption>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <TableHead className={cn(stickyHeadCell, 'px-4')}>
                            Parafia / urząd
                        </TableHead>
                        <TableHead>Rodzaj</TableHead>
                        <TableHead>Wyznanie</TableHead>
                        <TableHead>Siedziba</TableHead>
                        <TableHead className="px-4 text-right">
                            Źródła
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {parishes.map((parish) => (
                        <TableRow
                            key={parish.id}
                            className="group"
                            data-state={
                                parish.id === openId ? 'selected' : undefined
                            }
                        >
                            <TableCell className={cn(stickyCell, 'px-4 py-3')}>
                                <Link
                                    href={hrefFor(parish.id)}
                                    {...panelVisitOptions}
                                    aria-current={
                                        parish.id === openId
                                            ? 'true'
                                            : undefined
                                    }
                                    onClick={(event) =>
                                        onRowClick(event, parish.id)
                                    }
                                    className={cn(
                                        focusRing,
                                        '-m-1 block rounded-md p-1 font-medium break-words whitespace-normal',
                                        parish.id === openId && 'underline',
                                    )}
                                >
                                    {parish.label}
                                </Link>
                            </TableCell>
                            <TableCell>{parish.kind_label}</TableCell>
                            <TableCell>
                                {parish.religion_label ?? <EmptyCell />}
                            </TableCell>
                            <TableCell>
                                {parish.locality_name ?? <EmptyCell />}
                            </TableCell>
                            <TableCell className="px-4 text-right tabular-nums">
                                {parish.sources_count ?? 0}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
