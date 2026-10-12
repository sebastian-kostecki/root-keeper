import { useState } from 'react';
import { EventsTab } from '@/components/persons/events-tab';
import { FamilyTab } from '@/components/persons/family-tab';
import { NoteTab } from '@/components/persons/note-tab';
import { OverviewTab } from '@/components/persons/overview-tab';
import { useSectionEditor } from '@/components/persons/section-editor';
import { SourcesTab } from '@/components/persons/sources-tab';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PersonPreviewPayload, Source } from '@/types';

type Props = {
    preview: PersonPreviewPayload;
    availableSources?: Source[];
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

/** Tabbed body of the person panel (view mode): Przegląd, Rodzina, Źródła, Wydarzenia, Notatka. */
export function PersonModal({ preview, availableSources }: Props) {
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
            ...(person.birth?.sources ?? []),
            ...(person.death?.sources ?? []),
            ...person.marriages.flatMap((marriage) => marriage.sources),
        ].map((source) => source.id),
    ).size;

    const hasNote = Boolean(person.note?.trim());

    return (
        <Tabs
            value={tab}
            onValueChange={(value) => requestExit(() => setTab(value))}
            className="gap-0"
        >
            <div className="border-b pb-3">
                <div className="-m-1 overflow-x-auto p-1">
                    <TabsList className="w-full sm:w-fit">
                        <TabsTrigger value="przeglad" className="shrink-0">
                            Przegląd
                        </TabsTrigger>
                        <TabsTrigger value="rodzina" className="shrink-0">
                            Rodzina
                            <TabCount count={familyCount} />
                        </TabsTrigger>
                        <TabsTrigger value="zrodla" className="shrink-0">
                            Źródła
                            <TabCount count={sourceCount} />
                        </TabsTrigger>
                        <TabsTrigger value="wydarzenia" className="shrink-0">
                            Wydarzenia
                        </TabsTrigger>
                        <TabsTrigger value="notatka" className="shrink-0">
                            Notatka
                            {hasNote && (
                                <>
                                    <span
                                        aria-hidden
                                        className="bg-primary size-1.5 rounded-full"
                                    />
                                    <span className="sr-only">
                                        (jest notatka)
                                    </span>
                                </>
                            )}
                        </TabsTrigger>
                    </TabsList>
                </div>
            </div>

            <TabsContent value="przeglad" className="py-4">
                <OverviewTab
                    preview={preview}
                    availableSources={availableSources}
                />
            </TabsContent>
            <TabsContent value="rodzina" className="py-4">
                <FamilyTab person={person} />
            </TabsContent>
            <TabsContent value="zrodla" className="py-4">
                <SourcesTab
                    person={person}
                    availableSources={availableSources}
                />
            </TabsContent>
            <TabsContent value="wydarzenia" className="py-4">
                <EventsTab person={person} />
            </TabsContent>
            <TabsContent value="notatka" className="py-4">
                <NoteTab person={person} />
            </TabsContent>
        </Tabs>
    );
}
