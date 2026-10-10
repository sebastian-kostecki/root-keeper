import { Head, setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    ancestors as treeAncestors,
    index as treeIndex,
    show as treeShow,
} from '@/routes/tree';
import type { AncestorChart } from '@/types';

type Props = {
    chart: AncestorChart;
};

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
    });

    return (
        <>
            <Head title={`Przodkowie: ${chart.focus.display_name}`} />

            <div className="space-y-6">
                <Heading title={chart.focus.display_name} variant="page" />
            </div>
        </>
    );
}
