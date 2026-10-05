import { Head, Link, router } from '@inertiajs/react';
import { LayoutGridIcon, TableIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DiscardChangesDialog } from '@/components/discard-changes-dialog';
import Heading from '@/components/heading';
import { SearchField } from '@/components/search-field';
import { SegmentedToggle } from '@/components/segmented-toggle';
import { SourcePanel } from '@/components/sources/source-panel';
import {
    SourcesEmptyKind,
    SourcesEmptyTree,
    SourcesNoResults,
} from '@/components/sources/sources-empty-state';
import { SourcesGallery } from '@/components/sources/sources-gallery';
import { SourcesTable } from '@/components/sources/sources-table';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import { pluralPhrase } from '@/lib/plural';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { filterSources, withSourcesQuery } from '@/lib/sources-index';
import type {
    SourcesFilters,
    SourcesKindFilter,
    SourcesSort,
    SourcesView,
} from '@/lib/sources-index';
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
    sourcePanel: SourcePanelProps;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Źródła',
        href: sourcesIndex(),
    },
];

export default function Index({
    sources,
    kinds,
    recordTypes,
    filters,
    sourcePanel,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const lastVisitedQuery = useRef(filters.q);
    const trimmedSearch = search.trim();
    const { hrefFor, open, close } = useSidePanelNavigation('source');

    const openId = sourcePanel?.source?.id ?? null;
    /** Panel key of the record shown now: its id, 'new', or null when the panel is closed. */
    const openKey =
        sourcePanel === null ? null : (sourcePanel.source?.id ?? 'new');
    const { setIsDirty, guard, guardLink, discardDialog } =
        useUnsavedChangesGuard({ openKey, open });

    /** Every /sources visit goes through `withSourcesQuery`, so no control drops another's parameter or the panel key. */
    const visit = (next: Partial<SourcesFilters>, replace = false) => {
        const merged = { ...filters, q: trimmedSearch, ...next };

        lastVisitedQuery.current = merged.q;

        // From the live address, not a render-time copy: the debounced visit may fire after a panel opened.
        const { pathname, search: liveSearch, hash } = window.location;

        router.get(
            withSourcesQuery(`${pathname}${liveSearch}${hash}`, merged),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace,
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

        if (trimmed === lastVisitedQuery.current) {
            return;
        }

        const timeoutId = setTimeout(
            () =>
                visit(
                    { q: trimmed, kind: filters.kind, view: filters.view },
                    true,
                ),
            300,
        );

        return () => clearTimeout(timeoutId);
        // visit closes over `filters`; only its display fields matter here.
    }, [search, filters.q, filters.kind, filters.view, filters.sort]);

    const { visible, counts } = filterSources(
        sources,
        trimmedSearch,
        filters.kind,
    );

    const clearSearch = () => {
        setSearch('');
        visit({ q: '' });
    };

    // The status follows the committed query, so it is announced once per pause, not per keystroke.
    const committedCount = filterSources(sources, filters.q, filters.kind)
        .visible.length;

    const statusText =
        filters.q === '' && filters.kind === 'wszystkie'
            ? ''
            : committedCount === 0
              ? 'Brak wyników'
              : `Znaleziono: ${committedCount} ${pluralPhrase(committedCount, 'źródło', 'źródła', 'źródeł')}`;

    const addButton = (
        <Button asChild>
            <Link
                href={hrefFor('new')}
                {...panelVisitOptions}
                onClick={(event) => guardLink(event, 'new')}
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
                        description="Akty, zdjęcia nagrobków i inne dokumenty Twojego drzewa"
                    />

                    {addButton}
                </div>

                {sources.length > 0 && (
                    <div
                        role="search"
                        className="flex flex-wrap items-center gap-3"
                    >
                        <SearchField
                            value={search}
                            onChange={setSearch}
                            placeholder="Szukaj po nazwie"
                            ariaLabel="Szukaj źródła"
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
                            className="order-2 sm:order-3"
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
                )}

                <p className="sr-only" role="status" aria-live="polite">
                    {statusText}
                </p>

                {sources.length === 0 ? (
                    <SourcesEmptyTree action={addButton} />
                ) : visible.length === 0 ? (
                    counts.wszystkie > 0 && filters.kind !== 'wszystkie' ? (
                        <SourcesEmptyKind
                            kind={filters.kind}
                            query={trimmedSearch}
                            onShowAll={() => visit({ kind: 'wszystkie' })}
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
                        onSortChange={(sort: SourcesSort) => visit({ sort })}
                        openId={openId}
                        hrefFor={hrefFor}
                        onOpen={guardLink}
                    />
                ) : (
                    <SourcesGallery
                        sources={visible}
                        openId={openId}
                        hrefFor={hrefFor}
                        onOpen={guardLink}
                    />
                )}
            </div>

            {sourcePanel !== null && (
                <SourcePanel
                    source={sourcePanel.source}
                    confirms={sourcePanel.confirms}
                    kinds={kinds}
                    recordTypes={recordTypes}
                    onRequestClose={() => guard(close)}
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
