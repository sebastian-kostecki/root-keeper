import { Head, router } from '@inertiajs/react';
import { LayoutListIcon, TableIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/heading';
import { PersonCreateDialog } from '@/components/persons/person-create-dialog';
import {
    PersonsEmptyTree,
    PersonsNoResults,
} from '@/components/persons/persons-empty-state';
import { PersonsList } from '@/components/persons/persons-list';
import { PersonsTable } from '@/components/persons/persons-table';
import { SearchField } from '@/components/search-field';
import { SegmentedToggle } from '@/components/segmented-toggle';
import { Button } from '@/components/ui/button';
import { buildPersonsQuery, peopleCount } from '@/lib/persons-index';
import type {
    PersonsFilters,
    PersonsSort,
    PersonsView,
} from '@/lib/persons-index';
import { index as personsIndex } from '@/routes/persons';
import type { BreadcrumbItem, Gender, Person, Religion } from '@/types';

type Props = {
    persons: Person[];
    genders: Gender[];
    religions: Religion[];
    rootPersonId: number | null;
    filters: PersonsFilters;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Osoby',
        href: personsIndex(),
    },
];

export default function Index({
    persons,
    religions,
    rootPersonId,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const [isSearching, setIsSearching] = useState(false);
    const lastVisitedQuery = useRef(filters.q);
    const latestSearchVisit = useRef(0);

    /** Full reload with the search visit options: no history entry per keystroke. */
    const visitSearch = (next: Partial<PersonsFilters>) => {
        const visitId = ++latestSearchVisit.current;

        lastVisitedQuery.current = next.q ?? filters.q;

        router.get(
            PersonController.index({
                query: buildPersonsQuery({ ...filters, ...next }),
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setIsSearching(true),
                // A cancelled visit must not be treated as done: let the effect re-issue it.
                onCancel: () => {
                    if (visitId === latestSearchVisit.current) {
                        lastVisitedQuery.current = filters.q;
                    }
                },
                // Only the latest search visit owns the spinner.
                onFinish: () => {
                    if (visitId === latestSearchVisit.current) {
                        setIsSearching(false);
                    }
                },
            },
        );
    };

    /** View and sort only change how the loaded list is shown, so only `filters` is reloaded. */
    const visitDisplay = (next: Partial<PersonsFilters>) => {
        router.get(
            PersonController.index({
                query: buildPersonsQuery({ ...filters, ...next }),
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['filters'],
            },
        );
    };

    // History navigation changes `q` without a visit of ours: show it in the input.
    useEffect(() => {
        if (filters.q !== lastVisitedQuery.current) {
            lastVisitedQuery.current = filters.q;
            setSearch(filters.q);
        }
    }, [filters.q]);

    useEffect(() => {
        const trimmed = search.trim();

        if (trimmed === filters.q || trimmed === lastVisitedQuery.current) {
            return;
        }

        const timeoutId = setTimeout(() => visitSearch({ q: trimmed }), 300);

        return () => clearTimeout(timeoutId);
        // visitSearch closes over `filters`; only its display fields matter here.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.q, filters.view, filters.sort]);

    const clearSearch = () => {
        setSearch('');
        visitSearch({ q: '' });
    };

    const createTrigger = <Button>Dodaj osobę</Button>;

    return (
        <>
            <Head title="Osoby" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Osoby"
                        description="Osoby zapisane w Twoim drzewie genealogicznym"
                    />

                    <PersonCreateDialog
                        religions={religions}
                        trigger={createTrigger}
                    />
                </div>

                {(persons.length > 0 || filters.q !== '') && (
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <SearchField
                            value={search}
                            onChange={setSearch}
                            placeholder="Szukaj po imieniu lub nazwisku"
                            ariaLabel="Szukaj osoby"
                            busy={isSearching}
                            busyLabel="Wyszukiwanie"
                            maxLength={100}
                        />

                        <SegmentedToggle<PersonsView>
                            value={filters.view}
                            onValueChange={(view) => visitDisplay({ view })}
                            ariaLabel="Widok listy osób"
                            className="self-start"
                            items={[
                                {
                                    value: 'lista',
                                    label: 'Lista',
                                    icon: <LayoutListIcon />,
                                },
                                {
                                    value: 'tabela',
                                    label: 'Tabela',
                                    icon: <TableIcon />,
                                },
                            ]}
                        />
                    </div>
                )}

                <p className="sr-only" role="status" aria-live="polite">
                    {filters.q === ''
                        ? ''
                        : persons.length === 0
                          ? 'Brak wyników'
                          : `Znaleziono: ${peopleCount(persons.length)}`}
                </p>

                {persons.length === 0 && filters.q === '' ? (
                    <PersonsEmptyTree
                        action={
                            <PersonCreateDialog
                                religions={religions}
                                trigger={createTrigger}
                            />
                        }
                    />
                ) : persons.length === 0 ? (
                    <PersonsNoResults query={filters.q} onClear={clearSearch} />
                ) : (
                    <div
                        aria-busy={isSearching}
                        className={
                            isSearching
                                ? 'opacity-60 transition-opacity'
                                : 'transition-opacity'
                        }
                    >
                        {filters.view === 'tabela' ? (
                            <PersonsTable
                                persons={persons}
                                rootPersonId={rootPersonId}
                                sort={filters.sort}
                                onSortChange={(sort: PersonsSort) =>
                                    visitDisplay({ sort })
                                }
                            />
                        ) : (
                            <PersonsList
                                persons={persons}
                                rootPersonId={rootPersonId}
                            />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs,
};
