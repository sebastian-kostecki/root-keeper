import { Head, Link, router } from '@inertiajs/react';
import { LayoutGridIcon, TableIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { DiscardChangesDialog } from '@/components/discard-changes-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { isPlainClick } from '@/components/persons/section-editor';
import { SearchField } from '@/components/search-field';
import { SegmentedToggle } from '@/components/segmented-toggle';
import { SourcePanel } from '@/components/sources/source-panel';
import {
    SourcesEmptyKind,
    SourcesEmptyTree,
    SourcesFilteredNoResults,
    SourcesNoResults,
} from '@/components/sources/sources-empty-state';
import { SourcesFilterBar } from '@/components/sources/sources-filters';
import { SourcesGallery } from '@/components/sources/sources-gallery';
import { SourcesTable } from '@/components/sources/sources-table';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import { pluralPhrase } from '@/lib/plural';
import { panelVisitOptions } from '@/lib/side-panel-url';
import {
    filterOptionCounts,
    filterSources,
    halfCenturyPresets,
    hiddenFilterCount,
    isFiltering,
    parseYearInput,
    resolveFilters,
    resultCountText,
    withSourcesQuery,
    yearFieldsError,
} from '@/lib/sources-index';
import type {
    SourcesFilters,
    SourcesKindFilter,
    SourcesMissingCriterion,
    SourcesPlaceOptions,
    SourcesSort,
    SourcesView,
    YearFieldsError,
} from '@/lib/sources-index';
import { failureHandlers } from '@/lib/visit-failure';
import type { VisitError } from '@/lib/visit-failure';
import { index as sourcesIndex } from '@/routes/sources';
import type {
    BreadcrumbItem,
    IndexSource,
    SourceKind,
    SourcePanelProps,
    SourceRecordType,
} from '@/types';

type Props = {
    sources: IndexSource[];
    kinds: { value: SourceKind; label: string }[];
    recordTypes: { value: SourceRecordType; label: string }[];
    filters: SourcesFilters;
    placeOptions: SourcesPlaceOptions;
    archiveOptions: string[];
    sourcePanel: SourcePanelProps;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Źródła',
        href: sourcesIndex(),
    },
];

const VISIT_ERROR = 'Nie udało się zastosować filtrów.';

const missingNouns: Record<SourcesMissingCriterion, string> = {
    year: 'roku',
    place: 'miejsca',
    archive: 'archiwum',
};

/** „Ukryto 2 źródła bez roku lub miejsca”; the criteria are listed in the order they come in. */
function hiddenMessage(
    count: number,
    missing: SourcesMissingCriterion[],
): string {
    const nouns = missing.map((criterion) => missingNouns[criterion]);
    const last = nouns[nouns.length - 1];
    const joined =
        nouns.length > 1
            ? `${nouns.slice(0, -1).join(', ')} lub ${last}`
            : last;

    return `Ukryto ${count} ${pluralPhrase(count, 'źródło', 'źródła', 'źródeł')} bez ${joined}`;
}

function yearText(year: number | null): string {
    return year === null ? '' : String(year);
}

export default function Index({
    sources,
    kinds,
    recordTypes,
    filters: requestedFilters,
    placeOptions,
    archiveOptions,
    sourcePanel,
}: Props) {
    /** The filters that are actually applied: a place or archive missing from the options is blank. */
    const filters = resolveFilters(
        requestedFilters,
        placeOptions,
        archiveOptions,
    );
    const [search, setSearch] = useState(filters.q);
    /** The year fields hold text while typing; the committed years are in `filters`. */
    const [yearFrom, setYearFrom] = useState(yearText(filters.year_from));
    const [yearTo, setYearTo] = useState(yearText(filters.year_to));
    /** Shown year error: worked out from the typed text, appears 300 ms after the last keystroke. */
    const [yearError, setYearError] = useState<YearFieldsError | null>(null);
    const [visitError, setVisitError] = useState<VisitError | null>(null);
    const [isVisiting, setIsVisiting] = useState(false);
    const lastVisitedQuery = useRef(filters.q);
    const lastVisitedYears = useRef({
        year_from: filters.year_from,
        year_to: filters.year_to,
    });
    /** Numbers every visit of this page; only the latest one owns the spinner and the error. */
    const latestVisit = useRef(0);
    /** The debounced visit still waiting to fire, if any. */
    const pendingSearch = useRef<ReturnType<typeof setTimeout> | null>(null);
    const trimmedSearch = search.trim();
    const typedYears = {
        year_from: parseYearInput(yearFrom),
        year_to: parseYearInput(yearTo),
    };
    const { hrefFor, open, close } = useSidePanelNavigation('source');
    const { open: openPerson } = useSidePanelNavigation('person');

    const openId = sourcePanel?.source?.id ?? null;
    /** Panel key of the record shown now: its id, 'new', or null when the panel is closed. */
    const openKey =
        sourcePanel === null ? null : (sourcePanel.source?.id ?? 'new');
    const { setIsDirty, guard, guardLink, discardDialog } =
        useUnsavedChangesGuard({ openKey, open });

    /** Every /sources visit goes through `withSourcesQuery`, so no control drops another's parameter or the panel key. */
    const visit = (next: Partial<SourcesFilters>, replace = false) => {
        // This visit commits what is typed, so a debounce still waiting would only replay it with stale filters.
        if (pendingSearch.current !== null) {
            clearTimeout(pendingSearch.current);
            pendingSearch.current = null;
        }

        // Years come from the fields, so a visit from another control also commits the years typed so far.
        const merged = { ...filters, q: trimmedSearch, ...typedYears, ...next };

        lastVisitedQuery.current = merged.q;
        lastVisitedYears.current = {
            year_from: merged.year_from,
            year_to: merged.year_to,
        };

        // From the live address, not a render-time copy: the debounced visit may fire after a panel opened.
        const { pathname, search: liveSearch, hash } = window.location;
        const visitId = ++latestVisit.current;

        router.get(
            withSourcesQuery(`${pathname}${liveSearch}${hash}`, merged),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace,
                only: ['filters'],
                onStart: () => {
                    setIsVisiting(true);
                    setVisitError(null);
                },
                onFinish: () => {
                    if (visitId === latestVisit.current) {
                        setIsVisiting(false);
                    }
                },
                ...failureHandlers(
                    VISIT_ERROR,
                    () => visit(next, replace),
                    setVisitError,
                    () => visitId === latestVisit.current,
                ),
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

    // History navigation changes the years the same way.
    useEffect(() => {
        if (
            filters.year_from !== lastVisitedYears.current.year_from ||
            filters.year_to !== lastVisitedYears.current.year_to
        ) {
            lastVisitedYears.current = {
                year_from: filters.year_from,
                year_to: filters.year_to,
            };
            setYearFrom(yearText(filters.year_from));
            setYearTo(yearText(filters.year_to));
        }
    }, [filters.year_from, filters.year_to]);

    // Read from the typed text, not from a visit: „Wyczyść filtry” and history change the years too.
    useEffect(() => {
        const error = yearFieldsError(yearFrom, yearTo);

        if (error === null) {
            setYearError(null);

            return;
        }

        const timeoutId = setTimeout(() => setYearError(error), 300);

        return () => clearTimeout(timeoutId);
    }, [yearFrom, yearTo]);

    // Search and the year fields share one debounce, so the address gets one visit per pause.
    useEffect(() => {
        const trimmed = search.trim();
        const years = {
            year_from: parseYearInput(yearFrom),
            year_to: parseYearInput(yearTo),
        };

        if (
            trimmed === lastVisitedQuery.current &&
            years.year_from === lastVisitedYears.current.year_from &&
            years.year_to === lastVisitedYears.current.year_to
        ) {
            return;
        }

        const timeoutId = setTimeout(() => {
            pendingSearch.current = null;
            visit(
                {
                    q: trimmed,
                    ...years,
                    kind: filters.kind,
                    view: filters.view,
                },
                true,
            );
        }, 300);

        pendingSearch.current = timeoutId;

        return () => {
            clearTimeout(timeoutId);
            pendingSearch.current = null;
        };
        // visit closes over `filters`; every field it reads is listed so a waiting visit never restores a stale value.
    }, [
        search,
        yearFrom,
        yearTo,
        filters.q,
        filters.kind,
        filters.view,
        filters.sort,
        filters.place,
        filters.archive,
    ]);

    /** Opens a record from the list; a search or year still waiting for its debounce goes in the same visit, which it would otherwise cancel. */
    const openFromList = (event: MouseEvent<Element>, id: number | string) => {
        guardLink(event, id);

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
        lastVisitedYears.current = typedYears;

        router.visit(
            withSourcesQuery(hrefFor(id), {
                ...filters,
                q: trimmedSearch,
                ...typedYears,
            }),
            {
                ...panelVisitOptions,
                only: [...panelVisitOptions.only, 'filters'],
            },
        );
    };

    /** „Potwierdza” swaps this panel for the person's; unsaved form changes ask first. */
    const openPersonFromPanel = (event: MouseEvent, personId: number) => {
        if (event.defaultPrevented || !isPlainClick(event)) {
            return;
        }

        event.preventDefault();
        guard(() => openPerson(personId));
    };

    const { visible, counts, hidden } = filterSources(sources, {
        ...filters,
        q: trimmedSearch,
    });

    const clearSearch = () => {
        setSearch('');
        visit({ q: '' });
    };

    /** Clears the search, the years and every filter; the view and the sort stay. */
    const clearFilters = () => {
        setSearch('');
        setYearFrom('');
        setYearTo('');
        visit({
            q: '',
            kind: 'wszystkie',
            place: '',
            year_from: null,
            year_to: null,
            archive: '',
        });
    };

    // The result count follows the committed query, so it is announced once per pause, not per keystroke.
    const committedCount = filterSources(sources, filters).visible.length;

    // The counts next to the popover options follow the search and years typed so far, as the list does.
    // They can briefly differ from the committed status line until the debounce commits; that is intended.
    const searchFilters = { ...filters, q: trimmedSearch };
    const placeValues = [
        '',
        ...placeOptions.localities.map(
            (locality) => `miejscowosc-${locality.id}`,
        ),
        ...placeOptions.parishes.map((parish) => `parafia-${parish.id}`),
    ];
    const archiveValues = ['', ...archiveOptions];

    const hasFilterBeyondSearch =
        filters.kind !== 'wszystkie' ||
        filters.place !== '' ||
        filters.year_from !== null ||
        filters.year_to !== null ||
        filters.archive !== '';

    const isEmptyKind =
        visible.length === 0 &&
        counts.wszystkie > 0 &&
        filters.kind !== 'wszystkie';
    const hasOtherFilters = hiddenFilterCount(filters) > 0;
    // The empty state carries its own „Wyczyść filtry”, so the bar's would be a duplicate.
    const emptyStateClears =
        sources.length > 0 &&
        visible.length === 0 &&
        (isEmptyKind ? hasOtherFilters : hasFilterBeyondSearch);

    const addButton = (
        <Button asChild>
            <Link
                href={hrefFor('new')}
                {...panelVisitOptions}
                onClick={(event) => openFromList(event, 'new')}
            >
                Dodaj źródło
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Źródła" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="page"
                        title="Źródła"
                        description="Akty, zdjęcia nagrobków i inne dokumenty zapisane w drzewie genealogicznym"
                    />

                    {addButton}
                </div>

                {sources.length > 0 && (
                    <div role="search" className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <SearchField
                                value={search}
                                onChange={setSearch}
                                placeholder="Szukaj po nazwie"
                                ariaLabel="Szukaj źródła"
                                busy={isVisiting}
                                busyLabel="Wczytywanie"
                                maxLength={100}
                                className="order-1 min-w-0 basis-0"
                            />

                            <SegmentedToggle<SourcesKindFilter>
                                value={filters.kind}
                                onValueChange={(kind) => visit({ kind })}
                                ariaLabel="Rodzaj źródła"
                                className="order-3 w-full sm:order-2 sm:w-auto"
                                items={[
                                    {
                                        value: 'wszystkie',
                                        label: 'Wszystkie',
                                        count: counts.wszystkie,
                                    },
                                    {
                                        value: 'akty',
                                        label: 'Akty',
                                        count: counts.akty,
                                        disabled:
                                            counts.akty === 0 &&
                                            filters.kind !== 'akty',
                                    },
                                    {
                                        value: 'nagrobki',
                                        label: 'Nagrobki',
                                        count: counts.nagrobki,
                                        disabled:
                                            counts.nagrobki === 0 &&
                                            filters.kind !== 'nagrobki',
                                    },
                                    {
                                        value: 'inne',
                                        label: 'Inne',
                                        count: counts.inne,
                                        disabled:
                                            counts.inne === 0 &&
                                            filters.kind !== 'inne',
                                    },
                                ]}
                            />

                            <SegmentedToggle<SourcesView>
                                value={filters.view}
                                onValueChange={(view) => visit({ view })}
                                ariaLabel="Widok listy źródeł"
                                iconOnlyBelowSm
                                className="order-2 sm:order-3 sm:ml-auto"
                                items={[
                                    {
                                        value: 'galeria',
                                        label: 'Galeria',
                                        icon: <LayoutGridIcon />,
                                    },
                                    {
                                        value: 'tabela',
                                        label: 'Tabela',
                                        icon: <TableIcon />,
                                    },
                                ]}
                            />
                        </div>

                        <SourcesFilterBar
                            place={filters.place}
                            placeOptions={placeOptions}
                            archive={filters.archive}
                            archiveOptions={archiveOptions}
                            committedYears={{
                                from: filters.year_from,
                                to: filters.year_to,
                            }}
                            yearFrom={yearFrom}
                            yearTo={yearTo}
                            activeCount={hiddenFilterCount(filters)}
                            showClear={
                                isFiltering(filters) && !emptyStateClears
                            }
                            yearError={yearError}
                            placeCounts={filterOptionCounts(
                                sources,
                                searchFilters,
                                'place',
                                placeValues,
                            )}
                            archiveCounts={filterOptionCounts(
                                sources,
                                searchFilters,
                                'archive',
                                archiveValues,
                            )}
                            yearPresets={halfCenturyPresets(sources)}
                            resultText={resultCountText(
                                committedCount,
                                sources.length,
                                isFiltering(filters),
                            )}
                            onPlaceChange={(place) => visit({ place })}
                            onArchiveChange={(archive) => visit({ archive })}
                            onYearFromChange={setYearFrom}
                            onYearToChange={setYearTo}
                            onYearsClear={() => {
                                setYearFrom('');
                                setYearTo('');
                                visit({ year_from: null, year_to: null });
                            }}
                            onClear={clearFilters}
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

                {hidden.count > 0 && (
                    <p className="text-muted-foreground text-sm">
                        {hiddenMessage(hidden.count, hidden.missing)}
                    </p>
                )}

                {sources.length === 0 ? (
                    <SourcesEmptyTree action={addButton} />
                ) : (
                    <div aria-busy={isVisiting}>
                        {visible.length === 0 ? (
                            counts.wszystkie > 0 &&
                            filters.kind !== 'wszystkie' ? (
                                <SourcesEmptyKind
                                    kind={filters.kind}
                                    query={trimmedSearch}
                                    hasOtherFilters={hasOtherFilters}
                                    onShowAll={() =>
                                        visit({ kind: 'wszystkie' })
                                    }
                                    onClearFilters={clearFilters}
                                />
                            ) : hasFilterBeyondSearch ? (
                                <SourcesFilteredNoResults
                                    query={trimmedSearch}
                                    onClearFilters={clearFilters}
                                />
                            ) : (
                                <SourcesNoResults
                                    query={trimmedSearch}
                                    onClear={clearSearch}
                                />
                            )
                        ) : filters.view === 'tabela' ? (
                            <SourcesTable
                                sources={visible}
                                sort={filters.sort}
                                onSortChange={(sort: SourcesSort) =>
                                    visit({ sort })
                                }
                                openId={openId}
                                hrefFor={hrefFor}
                                onOpen={openFromList}
                            />
                        ) : (
                            <SourcesGallery
                                sources={visible}
                                openId={openId}
                                hrefFor={hrefFor}
                                onOpen={openFromList}
                            />
                        )}
                    </div>
                )}
            </div>

            {sourcePanel !== null && (
                <SourcePanel
                    source={sourcePanel.source}
                    confirms={sourcePanel.confirms}
                    kinds={kinds}
                    recordTypes={recordTypes}
                    parishes={sourcePanel.parishes}
                    archives={sourcePanel.archives}
                    localities={sourcePanel.localities}
                    religions={sourcePanel.religions}
                    onRequestClose={() => guard(close)}
                    onPersonLinkClick={openPersonFromPanel}
                    onDirtyChange={setIsDirty}
                />
            )}

            <DiscardChangesDialog {...discardDialog} />
        </>
    );
}

Index.layout = {
    breadcrumbs,
};
