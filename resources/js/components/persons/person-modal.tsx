import { useState } from 'react';
import { FamilyTab } from '@/components/persons/family-tab';
import { OverviewTab } from '@/components/persons/overview-tab';
import { useSectionEditor } from '@/components/persons/section-editor';
import { SourcesTab } from '@/components/persons/sources-tab';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PersonPreviewPayload, Scan } from '@/types';

type Props = {
    preview: PersonPreviewPayload;
    availableScans?: Scan[];
};

function TabCount({ count }: { count: number }) {
    if (count === 0) {
        return null;
    }

    return (
        <span className="text-muted-foreground text-xs font-normal">
            {count}
        </span>
    );
}

/** Tabbed body of the person modal (view mode): Przegląd, Rodzina, Źródła. */
export function PersonModal({ preview, availableScans }: Props) {
    const { person } = preview;
    const { requestExit } = useSectionEditor();
    const [tab, setTab] = useState('przeglad');
    const [shownPersonId, setShownPersonId] = useState(person.id);

    if (shownPersonId !== person.id) {
        setShownPersonId(person.id);
        setTab('przeglad');
    }

    const familyCount =
        person.parents.length +
        person.marriages.length +
        person.children.length;

    const sourceCount = new Set(
        [
            ...(person.birth?.scans ?? []),
            ...(person.death?.scans ?? []),
            ...person.marriages.flatMap((marriage) => marriage.scans),
        ].map((scan) => scan.id),
    ).size;

    return (
        <Tabs
            value={tab}
            onValueChange={(value) => requestExit(() => setTab(value))}
            className="min-h-0 flex-1 gap-0"
        >
            <div className="border-b px-6 pb-3">
                <TabsList className="w-full sm:w-fit">
                    <TabsTrigger value="przeglad">Przegląd</TabsTrigger>
                    <TabsTrigger value="rodzina">
                        Rodzina
                        <TabCount count={familyCount} />
                    </TabsTrigger>
                    <TabsTrigger value="zrodla">
                        Źródła
                        <TabCount count={sourceCount} />
                    </TabsTrigger>
                </TabsList>
            </div>

            <TabsContent
                value="przeglad"
                className="min-h-0 flex-1 overflow-y-auto px-6 py-4"
            >
                <OverviewTab
                    preview={preview}
                    availableScans={availableScans}
                />
            </TabsContent>
            <TabsContent
                value="rodzina"
                className="min-h-0 flex-1 overflow-y-auto px-6 py-4"
            >
                <FamilyTab person={person} />
            </TabsContent>
            <TabsContent
                value="zrodla"
                className="min-h-0 flex-1 overflow-y-auto px-6 py-4"
            >
                <SourcesTab person={person} availableScans={availableScans} />
            </TabsContent>
        </Tabs>
    );
}
