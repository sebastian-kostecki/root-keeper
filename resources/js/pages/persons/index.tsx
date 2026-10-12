import { Head, Link, router, usePage } from '@inertiajs/react';
import { LayoutListIcon, TableIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    PersonsEmptyTree,
    PersonsNoResults,
} from '@/components/persons/persons-empty-state';
import { PersonsList } from '@/components/persons/persons-list';
import { PersonsTable } from '@/components/persons/persons-table';
import { SearchField } from '@/components/search-field';
import { SegmentedToggle } from '@/components/segmented-toggle';
import { AddPersonPanel } from '@/components/tree/add-person-panel';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { resolvePersonsAddTarget } from '@/lib/add-person-target';
import { peopleCount, withPersonsQuery } from '@/lib/persons-index';
import type {
    PersonsFilters,
    PersonsSort,
    PersonsView,
} from '@/lib/persons-index';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { failureHandlers } from '@/lib/visit-failure';
import type { VisitError } from '@/lib/visit-failure';
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

const SEARCH_ERROR =
    'Nie udało się wyszukać — pokazane wyniki mogą być nieaktualne.';
const VIEW_ERROR = 'Nie udało się zmienić widoku.';

export default function Index({
    persons,
    genders,
    religions,
    rootPersonId,
    filters,
}: Props) {
    const { url } = usePage();
    const add = new URL(url, 'http://localhost').searchParams.get('add');
    const { hrefFor } = useSidePanelNavigation('add');
    const [search, setSearch] = useState(filters.q);
    const [isSearching, setIsSearching] = useState(false);
    const [visitError, setVisitError] = useState<VisitError | null>(null);
    const lastVisitedQuery = useRef(filters.q);
    const latestSearchVisit = useRef(0);
    /** The debounced search visit still waiting to fire, if any. */
    const pendingSearch = useRef<ReturnType<typeof setTimeout> | null>(null);
    const trimmedSearch = search.trim();

    /** The address of the list now, not a render-time copy: a debounced visit fires later, after a panel may have opened or closed. */
    const liveAddress = () => {
        const { pathname, search: liveSearch, hash } = window.location;

        return `${pathname}${liveSearch}${hash}`;
    };

    /** Full reload with the search visit options: no history entry per keystroke. Panel keys stay in the address. */
    const visitSearch = (next: Partial<PersonsFilters>) => {
        const visitId = ++latestSearchVisit.current;

        lastVisitedQuery.current = next.q ?? filters.q;

        router.get(
            withPersonsQuery(liveAddress(), { ...filters, ...next }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => {
                    setIsSearching(true);
                    setVisitError(null);
                },
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
                ...failureHandlers(
                    SEARCH_ERROR,
                    () => visitSearch(next),
                    setVisitError,
                    () => visitId === latestSearchVisit.current,
                ),
            },
        );
    };

    /** View and sort only change how the loaded list is shown, so only `filters` is reloaded. */
    const visitDisplay = (next: Partial<PersonsFilters>) => {
        router.get(
            withPersonsQuery(liveAddress(), { ...filters, ...next }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['filters'],
                onStart: () => setVisitError(null),
                ...failureHandlers(
                    VIEW_ERROR,
                    () => visitDisplay(next),
                    setVisitError,
                ),
            },
        );
    };

    /** "Dodaj osobę": a search still waiting for its debounce goes in the same visit, which it would otherwise cancel. */
    const openCreatePanel = (event: MouseEvent<Element>) => {
        const isModifiedClick =
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

        if (
            event.defaultPrevented ||
            isModifiedClick ||
            pendingSearch.current === null
        ) {
            return;
        }

        event.preventDefault();
        clearTimeout(pendingSearch.current);
        pendingSearch.current = null;
        lastVisitedQuery.current = trimmedSearch;

        router.visit(
            withPersonsQuery(hrefFor('person'), {
                ...filters,
                q: trimmedSearch,
            }),
            {
                replace: true,
                preserveState: true,
                preserveScroll: true,
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

        const timeoutId = setTimeout(() => {
            pendingSearch.current = null;
            visitSearch({ q: trimmed });
        }, 300);

        pendingSearch.current = timeoutId;

        return () => {
            clearTimeout(timeoutId);
            pendingSearch.current = null;
        };
        // visitSearch closes over `filters`; only its display fields matter here.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.q, filters.view, filters.sort]);

    const clearSearch = () => {
        setSearch('');
        visitSearch({ q: '' });
    };

    const createLink = (
        <Button asChild>
            <Link
                href={hrefFor('person')}
                {...panelVisitOptions}
                onClick={openCreatePanel}
            >
                Dodaj osobę
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Osoby" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="page"
                        title="Osoby"
                        description="Osoby zapisane w drzewie genealogicznym"
                    />

                    {createLink}
                </div>

                {(persons.length > 0 || filters.q !== '') && (
                    <div
                        role="search"
                        className="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
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
                            iconOnlyBelowSm
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

                {visitError !== null && (
                    <div className="flex flex-wrap items-center gap-3">
                        <InputError role="alert" message={visitError.message} />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => visitError.retry()}
                        >
                            Spróbuj ponownie
                        </Button>
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
                    <PersonsEmptyTree action={createLink} />
                ) : (
                    <div
                        aria-busy={isSearching}
                        className={
                            isSearching
                                ? 'opacity-60 transition-opacity'
                                : 'transition-opacity'
                        }
                    >
                        {persons.length === 0 ? (
                            <PersonsNoResults
                                query={filters.q}
                                onClear={clearSearch}
                            />
                        ) : filters.view === 'tabela' ? (
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

            <AddPersonPanel
                value={add}
                target={resolvePersonsAddTarget(add)}
                genders={genders}
                religions={religions}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs,
};
