import { Head, router } from '@inertiajs/react';
import { LayoutListIcon, SearchIcon, TableIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/heading';
import { PersonCreateDialog } from '@/components/persons/person-create-dialog';
import {
    PersonsEmptyTree,
    PersonsNoResults,
} from '@/components/persons/persons-empty-state';
import { PersonsList } from '@/components/persons/persons-list';
import { PersonsTable } from '@/components/persons/persons-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { buildPersonsQuery } from '@/lib/persons-index';
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

    /** Full reload with the search visit options: no history entry per keystroke. */
    const visitSearch = (next: Partial<PersonsFilters>) => {
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
                onFinish: () => setIsSearching(false),
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

    useEffect(() => {
        const trimmed = search.trim();

        if (trimmed === filters.q) {
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(
                PersonController.index({
                    query: buildPersonsQuery({
                        q: trimmed,
                        view: filters.view,
                        sort: filters.sort,
                    }),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => setIsSearching(true),
                    onFinish: () => setIsSearching(false),
                },
            );
        }, 300);

        return () => clearTimeout(timeoutId);
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
                        <div className="relative flex-1">
                            {isSearching ? (
                                <Spinner
                                    aria-label="Wyszukiwanie"
                                    className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2"
                                />
                            ) : (
                                <SearchIcon
                                    aria-hidden="true"
                                    className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                />
                            )}
                            <Input
                                type="search"
                                placeholder="Szukaj po imieniu lub nazwisku"
                                aria-label="Szukaj osoby"
                                maxLength={100}
                                className="pl-9"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>

                        <ToggleGroup
                            type="single"
                            variant="outline"
                            aria-label="Widok listy osób"
                            value={filters.view}
                            onValueChange={(value) => {
                                if (value !== '') {
                                    visitDisplay({
                                        view: value as PersonsView,
                                    });
                                }
                            }}
                            className="self-start"
                        >
                            <ToggleGroupItem value="lista" className="px-3">
                                <LayoutListIcon />
                                Lista
                            </ToggleGroupItem>
                            <ToggleGroupItem value="tabela" className="px-3">
                                <TableIcon />
                                Tabela
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                )}

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
