import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { DiscardChangesDialog } from '@/components/discard-changes-dialog';
import Heading from '@/components/heading';
import {
    LocalitiesEmptyTree,
    LocalitiesNoResults,
} from '@/components/localities/localities-empty-state';
import { LocalitiesTable } from '@/components/localities/localities-table';
import { LocalityPanel } from '@/components/localities/locality-panel';
import { SearchField } from '@/components/search-field';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import {
    filterLocalities,
    sortLocalities,
    withLocalitiesSearch,
} from '@/lib/localities-index';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { pluralPhrase } from '@/lib/plural';
import { index as localitiesIndex } from '@/routes/localities';
import type { BreadcrumbItem, Locality } from '@/types';

type Props = {
    localities: Locality[];
    filters: { q: string };
    localityPanel: { locality: Locality | null } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Miejscowości',
        href: localitiesIndex(),
    },
];

export default function Index({ localities, filters, localityPanel }: Props) {
    const [search, setSearch] = useState(filters.q);
    const lastVisitedQuery = useRef(filters.q);
    /** The debounced search visit still waiting to fire, if any. */
    const pendingSearch = useRef<ReturnType<typeof setTimeout> | null>(null);
    const trimmedSearch = search.trim();
    const { hrefFor, open, close } = useSidePanelNavigation('locality');

    const openId = localityPanel?.locality?.id ?? null;
    /** Panel key of the record shown now: its id, 'new', or null when the panel is closed. */
    const openKey =
        localityPanel === null ? null : (localityPanel.locality?.id ?? 'new');
    const { setIsDirty, guard, guardLink, discardDialog } =
        useUnsavedChangesGuard({ openKey, open });

    const visit = (q: string, replace = false) => {
        lastVisitedQuery.current = q;

        // From the live address, not a render-time copy: the debounced visit may fire after a panel opened.
        const { pathname, search, hash } = window.location;

        router.get(
            withLocalitiesSearch(`${pathname}${search}${hash}`, q),
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

    /** Opens a record from the list; a search still waiting for its debounce goes in the same visit, which it would otherwise cancel. */
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

        router.visit(withLocalitiesSearch(hrefFor(id), trimmedSearch), {
            ...panelVisitOptions,
            only: [...panelVisitOptions.only, 'filters'],
        });
    };

    const visible = sortLocalities(filterLocalities(localities, trimmedSearch));
    const committedCount = filterLocalities(localities, filters.q).length;

    const statusText =
        filters.q === ''
            ? ''
            : committedCount === 0
              ? 'Brak wyników'
              : `Znaleziono: ${committedCount} ${pluralPhrase(committedCount, 'miejscowość', 'miejscowości', 'miejscowości')}`;

    const addButton = (
        <Button asChild>
            <Link
                href={hrefFor('new')}
                {...panelVisitOptions}
                onClick={(event) => openFromList(event, 'new')}
            >
                Dodaj miejscowość
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Miejscowości" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="page"
                        title="Miejscowości"
                        description="Spis miejscowości Twojego drzewa"
                    />

                    {addButton}
                </div>

                {localities.length > 0 && (
                    <div role="search">
                        <SearchField
                            value={search}
                            onChange={setSearch}
                            placeholder="Szukaj po nazwie"
                            ariaLabel="Szukaj miejscowości"
                            maxLength={100}
                        />
                    </div>
                )}

                <p className="sr-only" role="status" aria-live="polite">
                    {statusText}
                </p>

                {localities.length === 0 ? (
                    <LocalitiesEmptyTree action={addButton} />
                ) : visible.length === 0 ? (
                    <LocalitiesNoResults
                        query={trimmedSearch}
                        onClear={() => {
                            setSearch('');
                            visit('');
                        }}
                    />
                ) : (
                    <LocalitiesTable
                        localities={visible}
                        openId={openId}
                        hrefFor={hrefFor}
                        onRowClick={openFromList}
                    />
                )}
            </div>

            {localityPanel !== null && (
                <LocalityPanel
                    locality={localityPanel.locality}
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
