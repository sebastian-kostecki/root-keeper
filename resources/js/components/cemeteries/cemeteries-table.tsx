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
import type { Cemetery } from '@/types';

type Props = {
    cemeteries: Cemetery[];
    openId: number | null;
    hrefFor: (id: number) => string;
    onRowClick: (event: MouseEvent<Element>, id: number) => void;
};

/** List by locality; a row is a link that opens the cemetery in the side panel. */
export function CemeteriesTable({
    cemeteries,
    openId,
    hrefFor,
    onRowClick,
}: Props) {
    return (
        <div className="rounded-lg border">
            <Table>
                <TableCaption className="sr-only">
                    Cmentarze w drzewie w kolejności miejscowości.
                </TableCaption>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <TableHead className={cn(stickyHeadCell, 'px-4')}>
                            Nazwa
                        </TableHead>
                        <TableHead>Miejscowość</TableHead>
                        <TableHead>Zarządca</TableHead>
                        <TableHead className="text-right">Nagrobki</TableHead>
                        <TableHead className="px-4 text-right">
                            Pochowani
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {cemeteries.map((cemetery) => (
                        <TableRow
                            key={cemetery.id}
                            className="group"
                            data-state={
                                cemetery.id === openId ? 'selected' : undefined
                            }
                        >
                            <TableCell className={cn(stickyCell, 'px-4 py-3')}>
                                <Link
                                    href={hrefFor(cemetery.id)}
                                    {...panelVisitOptions}
                                    aria-current={
                                        cemetery.id === openId
                                            ? 'true'
                                            : undefined
                                    }
                                    onClick={(event) =>
                                        onRowClick(event, cemetery.id)
                                    }
                                    className={cn(
                                        focusRing,
                                        '-m-1 block rounded-md p-1 font-medium break-words whitespace-normal',
                                        cemetery.id === openId && 'underline',
                                    )}
                                >
                                    {cemetery.label}
                                </Link>
                            </TableCell>
                            <TableCell>{cemetery.locality_name}</TableCell>
                            <TableCell>
                                {cemetery.manager_label ?? <EmptyCell />}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {cemetery.sources_count ?? 0}
                            </TableCell>
                            <TableCell className="px-4 text-right tabular-nums">
                                {cemetery.burials_count ?? 0}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
