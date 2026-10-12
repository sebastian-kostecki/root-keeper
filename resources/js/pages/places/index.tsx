import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { DiscardChangesDialog } from '@/components/discard-changes-dialog';
import Heading from '@/components/heading';
import { LocalitiesTable } from '@/components/localities/localities-table';
import { LocalityPanel } from '@/components/localities/locality-panel';
import { ParishesTable } from '@/components/parishes/parishes-table';
import { ParishPanel } from '@/components/parishes/parish-panel';
import {
    PlacesEmptyTree,
    PlacesNoResults,
} from '@/components/places/places-empty-state';
import { SearchField } from '@/components/search-field';
import { SegmentedToggle } from '@/components/segmented-toggle';
import type { SegmentedToggleItem } from '@/components/segmented-toggle';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import { filterLocalities, sortLocalities } from '@/lib/localities-index';
import { withPlacesQuery } from '@/lib/places-index';
import type { PlacesKind } from '@/lib/places-index';
import { filterParishes, sortParishes } from '@/lib/parishes-index';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { pluralPhrase } from '@/lib/plural';
import { index as placesIndex } from '@/routes/places';
import type { BreadcrumbItem, Locality, Parish, ReligionOption } from '@/types';

type Props = {
    localities: Locality[];
    parishes: Parish[];
    religions: ReligionOption[];
    filters: { q: string; kind: PlacesKind };
    localityPanel: { locality: Locality | null } | null;
    parishPanel: { parish: Parish | null } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Miejsca',
        href: placesIndex(),
    },
];

/** Splits a panel key such as `parish:3` into its panel and record id. */
function splitPanelKey(key: number | string): [string, string] {
    const [panel, id = ''] = String(key).split(':');

    return [panel, id];
}

export default function Index({
    localities,
    parishes,
    religions,
    filters,
    localityPanel,
    parishPanel,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const lastVisitedQuery = useRef(filters.q);
    /** The debounced search visit still waiting to fire, if any. */
    const pendingSearch = useRef<ReturnType<typeof setTimeout> | null>(null);
    const trimmedSearch = search.trim();
    const localityNav = useSidePanelNavigation('locality');
    const parishNav = useSidePanelNavigation('parish');
    const isParishTab = filters.kind === 'parafie';

    const openedLocalityId = localityPanel?.locality?.id ?? null;
    const openedParishId = parishPanel?.parish?.id ?? null;
    /** Key of the record shown now (`locality:3`, `parish:new`), or null when no panel is open. */
    const openKey =
        localityPanel !== null
            ? `locality:${localityPanel.locality?.id ?? 'new'}`
            : parishPanel !== null
              ? `parish:${parishPanel.parish?.id ?? 'new'}`
              : null;

    const openByKey = (key: number | string) => {
        const [panel, id] = splitPanelKey(key);

        if (panel === 'parish') {
            parishNav.open(id);
        } else {
            localityNav.open(id);
        }
    };

    const { setIsDirty, guard, guardLink, discardDialog } =
        useUnsavedChangesGuard({ openKey, open: openByKey });

    const visit = (q: string, replace = false) => {
        lastVisitedQuery.current = q;

        // From the live address, not a render-time copy: the debounced visit may fire after a panel opened.
        const { pathname, search, hash } = window.location;

        router.get(
            withPlacesQuery(`${pathname}${search}${hash}`, { q }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace,
                only: ['filters'],
            },
        );
    };

    // History navigation changes `q` or `kind` without a visit of ours: show `q` in the input.
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

        const timeoutId = setTimeout(() => {
            pendingSearch.current = null;
            visit(trimmed, true);
        }, 300);

        pendingSearch.current = timeoutId;

        return () => {
            clearTimeout(timeoutId);
            pendingSearch.current = null;
        };
        // visit only reads refs and the router; the search drives the effect.
    }, [search]);

    /** Switches the list between the localities and the parishes; a search still waiting for its debounce goes in the same visit. */
    const switchKind = (kind: PlacesKind) => {
        const hasPendingSearch = pendingSearch.current !== null;

        if (hasPendingSearch) {
            clearTimeout(pendingSearch.current ?? undefined);
            pendingSearch.current = null;
            lastVisitedQuery.current = trimmedSearch;
        }

        const { pathname, search, hash } = window.location;
        const url = withPlacesQuery(
            `${pathname}${search}${hash}`,
            hasPendingSearch ? { kind, q: trimmedSearch } : { kind },
        );

        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['filters'],
            },
        );
    };

    /** Opens a record from the list; a search still waiting for its debounce goes in the same visit, which it would otherwise cancel. */
    const openFromList = (event: MouseEvent<Element>, key: string) => {
        guardLink(event, key);

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

        const [panel, id] = splitPanelKey(key);
        const href =
            panel === 'parish'
                ? parishNav.hrefFor(id)
                : localityNav.hrefFor(id);

        router.visit(withPlacesQuery(href, { q: trimmedSearch }), {
            ...panelVisitOptions,
            only: [...panelVisitOptions.only, 'filters'],
        });
    };

    const visibleLocalities = sortLocalities(
        filterLocalities(localities, trimmedSearch),
    );
    const visibleParishes = sortParishes(
        filterParishes(parishes, trimmedSearch),
    );

    const committedCount = isParishTab
        ? filterParishes(parishes, filters.q).length
        : filterLocalities(localities, filters.q).length;

    const statusText =
        filters.q === ''
            ? ''
            : committedCount === 0
              ? 'Brak wyników'
              : isParishTab
                ? `Znaleziono: ${committedCount} ${pluralPhrase(committedCount, 'parafia', 'parafie', 'parafii')}`
                : `Znaleziono: ${committedCount} ${pluralPhrase(committedCount, 'miejscowość', 'miejscowości', 'miejscowości')}`;

    const addButton = isParishTab ? (
        <Button asChild>
            <Link
                href={parishNav.hrefFor('new')}
                {...panelVisitOptions}
                onClick={(event) => openFromList(event, 'parish:new')}
            >
                Dodaj parafię
            </Link>
        </Button>
    ) : (
        <Button asChild>
            <Link
                href={localityNav.hrefFor('new')}
                {...panelVisitOptions}
                onClick={(event) => openFromList(event, 'locality:new')}
            >
                Dodaj miejscowość
            </Link>
        </Button>
    );

    const kindItems: SegmentedToggleItem<PlacesKind>[] = [
        {
            value: 'miejscowosci',
            label: 'Miejscowości',
            count: localities.length,
        },
        {
            value: 'parafie',
            label: 'Parafie',
            count: parishes.length,
        },
    ];

    const clearSearch = () => {
        setSearch('');
        visit('');
    };

    const listContent = isParishTab ? (
        parishes.length === 0 ? (
            <PlacesEmptyTree kind="parafie" action={addButton} />
        ) : visibleParishes.length === 0 ? (
            <PlacesNoResults
                kind="parafie"
                query={trimmedSearch}
                onClear={clearSearch}
            />
        ) : (
            <ParishesTable
                parishes={visibleParishes}
                openId={openedParishId}
                hrefFor={(id) => parishNav.hrefFor(id)}
                onRowClick={(event, id) => openFromList(event, `parish:${id}`)}
            />
        )
    ) : localities.length === 0 ? (
        <PlacesEmptyTree kind="miejscowosci" action={addButton} />
    ) : visibleLocalities.length === 0 ? (
        <PlacesNoResults
            kind="miejscowosci"
            query={trimmedSearch}
            onClear={clearSearch}
        />
    ) : (
        <LocalitiesTable
            localities={visibleLocalities}
            openId={openedLocalityId}
            hrefFor={(id) => localityNav.hrefFor(id)}
            onRowClick={(event, id) => openFromList(event, `locality:${id}`)}
        />
    );

    return (
        <>
            <Head title="Miejsca" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="page"
                        title="Miejsca"
                        description="Spis miejscowości i parafii Twojego drzewa"
                    />

                    {addButton}
                </div>

                <SegmentedToggle
                    value={filters.kind}
                    onValueChange={switchKind}
                    ariaLabel="Spis"
                    items={kindItems}
                />

                {(localities.length > 0 || parishes.length > 0) && (
                    <div role="search">
                        <SearchField
                            value={search}
                            onChange={setSearch}
                            placeholder="Szukaj po nazwie"
                            ariaLabel="Szukaj miejscowości i parafii"
                            maxLength={100}
                        />
                    </div>
                )}

                <p className="sr-only" role="status" aria-live="polite">
                    {statusText}
                </p>

                {listContent}
            </div>

            {localityPanel !== null && (
                <LocalityPanel
                    locality={localityPanel.locality}
                    localities={localities}
                    onRequestClose={() => guard(localityNav.close)}
                    onDirtyChange={setIsDirty}
                />
            )}

            {parishPanel !== null && (
                <ParishPanel
                    parish={parishPanel.parish}
                    parishes={parishes}
                    religions={religions}
                    localities={localities}
                    onRequestClose={() => guard(parishNav.close)}
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
