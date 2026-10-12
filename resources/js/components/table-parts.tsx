import { ArrowDownIcon, ArrowUpIcon, ChevronsUpDownIcon } from 'lucide-react';
import { TableHead } from '@/components/ui/table';
import { focusRing } from '@/lib/focus-ring';
import { columnOf, nextSort } from '@/lib/table-sort';
import type { SortKey } from '@/lib/table-sort';
import { cn } from '@/lib/utils';

/** The muted „—” of a table cell without a value. */
export function EmptyCell() {
    return <span className="text-muted-foreground">—</span>;
}

/** A column head that is a sort button; the caller passes a `sort` of its own column type `C`. */
export function SortableHead<C extends string>({
    column,
    label,
    sort,
    onSortChange,
    className,
}: {
    column: C;
    label: string;
    sort: SortKey<C>;
    onSortChange: (sort: SortKey<C>) => void;
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
                className={cn(
                    focusRing,
                    'hover:bg-accent -mx-2 inline-flex h-8 items-center gap-1.5 rounded-md px-2 transition-colors',
                )}
            >
                {label}
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
