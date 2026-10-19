import { Head, router, setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import { SegmentedToggle } from '@/components/segmented-toggle';
import { AncestorChart } from '@/components/tree/ancestor-chart';
import {
    ancestors as treeAncestors,
    index as treeIndex,
    show as treeShow,
} from '@/routes/tree';
import type { AncestorChart as AncestorChartData } from '@/types';

type Props = {
    ancestorChart: AncestorChartData;
};

/** Smallest generation count the switch offers; the server accepts the same. */
const MIN_GENERATIONS = 2;

export default function Ancestors({ ancestorChart: chart }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Drzewo', href: treeIndex() },
            { title: chart.focus.display_name, href: treeShow(chart.focus.id) },
            {
                title: 'Przodkowie',
                href: treeAncestors(chart.focus.id, {
                    query: { generations: chart.generations },
                }),
            },
        ],
        fillHeight: true,
    });

    /** Counts from 2 up to what the data allows; the range comes from the server (`available_generations`). */
    const generationItems = Array.from(
        {
            length: Math.max(
                0,
                chart.available_generations - MIN_GENERATIONS + 1,
            ),
        },
        (_, index) => {
            const value = String(MIN_GENERATIONS + index);

            return { value, label: value };
        },
    );

    /** Keeps the open panel and any other query of the address, only the generations change. */
    const changeGenerations = (generations: string) => {
        const query = Object.fromEntries(
            new URLSearchParams(window.location.search),
        );

        router.visit(
            treeAncestors.url(chart.focus.id, {
                query: { ...query, generations },
            }),
        );
    };

    return (
        <>
            <Head title={`Przodkowie: ${chart.focus.display_name}`} />

            <div className="flex min-h-0 flex-1 flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading title={chart.focus.display_name} variant="page" />

                    {generationItems.length > 1 && (
                        <div className="flex max-w-full min-w-0 items-center gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Pokolenia
                            </span>
                            {/* Up to 19 options: a narrow screen scrolls the switch, not the page; the padding keeps the focus ring inside. */}
                            <div className="-m-1 min-w-0 overflow-x-auto p-1">
                                <SegmentedToggle
                                    value={String(chart.generations)}
                                    onValueChange={changeGenerations}
                                    ariaLabel="Pokolenia"
                                    items={generationItems}
                                />
                            </div>
                        </div>
                    )}
                </div>

                <AncestorChart chart={chart} />
            </div>
        </>
    );
}
