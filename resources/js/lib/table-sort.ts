/** A sort key: the column name, ascending, or the column name with a leading `-`, descending. */
export type SortKey<C extends string> = C | `-${C}`;

export function columnOf<C extends string>(sort: SortKey<C>): C {
    return (sort.startsWith('-') ? sort.slice(1) : sort) as C;
}

/** Clicking the active column flips the direction; a new column starts ascending. */
export function nextSort<C extends string>(
    current: SortKey<C>,
    column: C,
): SortKey<C> {
    if (columnOf(current) === column) {
        return current.startsWith('-') ? column : (`-${column}` as SortKey<C>);
    }

    return column;
}
