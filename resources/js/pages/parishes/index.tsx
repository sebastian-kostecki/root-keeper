import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { DiscardChangesDialog } from '@/components/discard-changes-dialog';
import Heading from '@/components/heading';
import {
    ParishesEmptyTree,
    ParishesNoResults,
} from '@/components/parishes/parishes-empty-state';
import { ParishesTable } from '@/components/parishes/parishes-table';
import { ParishPanel } from '@/components/parishes/parish-panel';
import { SearchField } from '@/components/search-field';
import { Button } from '@/components/ui/button';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import {
    filterParishes,
    sortParishes,
    withParishesSearch,
} from '@/lib/parishes-index';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { pluralPhrase } from '@/lib/plural';
import { index as parishesIndex } from '@/routes/parishes';
import type {
    BreadcrumbItem,
    LocalityOption,
    Parish,
    ReligionOption,
} from '@/types';

type Props = {
    parishes: Parish[];
    religions: ReligionOption[];
    localities: LocalityOption[];
    filters: { q: string };
    parishPanel: { parish: Parish | null } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Parafie',
        href: parishesIndex(),
    },
];

export default function Index({
    parishes,
    religions,
    localities,
    filters,
    parishPanel,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const lastVisitedQuery = useRef(filters.q);
    /** The debounced search visit still waiting to fire, if any. */
    const pendingSearch = useRef<ReturnType<typeof setTimeout> | null>(null);
    const trimmedSearch = search.trim();
    const { hrefFor, open, close } = useSidePanelNavigation('parish');

    const openId = parishPanel?.parish?.id ?? null;
    /** Panel key of the record shown now: its id, 'new', or null when the panel is closed. */
    const openKey =
        parishPanel === null ? null : (parishPanel.parish?.id ?? 'new');
    const { setIsDirty, guard, guardLink, discardDialog } =
        useUnsavedChangesGuard({ openKey, open });

    const visit = (q: string, replace = false) => {
        lastVisitedQuery.current = q;

        // From the live address, not a render-time copy: the debounced visit may fire after a panel opened.
        const { pathname, search, hash } = window.location;

        router.get(
            withParishesSearch(`${pathname}${search}${hash}`, q),
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

        router.visit(withParishesSearch(hrefFor(id), trimmedSearch), {
            ...panelVisitOptions,
            only: [...panelVisitOptions.only, 'filters'],
        });
    };

    const visible = sortParishes(filterParishes(parishes, trimmedSearch));
    const committedCount = filterParishes(parishes, filters.q).length;

    const statusText =
        filters.q === ''
            ? ''
            : committedCount === 0
              ? 'Brak wyników'
              : `Znaleziono: ${committedCount} ${pluralPhrase(committedCount, 'parafia', 'parafie', 'parafii')}`;

    const addButton = (
        <Button asChild>
            <Link
                href={hrefFor('new')}
                {...panelVisitOptions}
                onClick={(event) => openFromList(event, 'new')}
            >
                Dodaj parafię
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Parafie" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="page"
                        title="Parafie"
                        description="Spis parafii Twojego drzewa"
                    />

                    {addButton}
                </div>

                {parishes.length > 0 && (
                    <div role="search">
                        <SearchField
                            value={search}
                            onChange={setSearch}
                            placeholder="Szukaj po nazwie lub siedzibie"
                            ariaLabel="Szukaj parafii"
                            maxLength={100}
                        />
                    </div>
                )}

                <p className="sr-only" role="status" aria-live="polite">
                    {statusText}
                </p>

                {parishes.length === 0 ? (
                    <ParishesEmptyTree action={addButton} />
                ) : visible.length === 0 ? (
                    <ParishesNoResults
                        query={trimmedSearch}
                        onClear={() => {
                            setSearch('');
                            visit('');
                        }}
                    />
                ) : (
                    <ParishesTable
                        parishes={visible}
                        openId={openId}
                        hrefFor={hrefFor}
                        onRowClick={openFromList}
                    />
                )}
            </div>

            {parishPanel !== null && (
                <ParishPanel
                    parish={parishPanel.parish}
                    parishes={parishes}
                    religions={religions}
                    localities={localities}
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
