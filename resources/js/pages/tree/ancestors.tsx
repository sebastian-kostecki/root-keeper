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
    chart: AncestorChartData;
};

/** Generation counts the switch offers; the server accepts the same range. */
const GENERATION_ITEMS = ['2', '3', '4', '5', '6'].map((value) => ({
    value,
    label: value,
}));

export default function Ancestors({ chart }: Props) {
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

                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-muted-foreground">Pokolenia</span>
                        <SegmentedToggle
                            value={String(chart.generations)}
                            onValueChange={changeGenerations}
                            ariaLabel="Pokolenia"
                            items={GENERATION_ITEMS}
                        />
                    </div>
                </div>

                <AncestorChart chart={chart} />
            </div>
        </>
    );
}
