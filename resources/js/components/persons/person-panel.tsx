import { usePage } from '@inertiajs/react';
import { PersonModal } from '@/components/persons/person-modal';
import {
    PersonModalHeader,
    PersonPanelActions,
    personLifespan,
} from '@/components/persons/person-modal-header';
import { usePersonPreview } from '@/components/persons/person-preview-provider';
import {
    UnsavedChangesBar,
    useSectionEditor,
} from '@/components/persons/section-editor';
import { SidePanel } from '@/components/side-panel';
import { centreHints } from '@/lib/centre-hints';
import type { FamilySheet } from '@/types';

/**
 * Side panel hosted by the layout; open whenever the page carries a `personPreview`.
 */
export function PersonPanel() {
    const props = usePage().props;
    const { personPreview, availableSources } = props;
    const { close } = usePersonPreview();
    const { requestExit } = useSectionEditor();

    if (personPreview === null) {
        return null;
    }

    const { person, isRootPerson } = personPreview;
    // Only the tree page passes a sheet; elsewhere there is nothing to re-centre.
    const sheet = 'sheet' in props ? (props.sheet as FamilySheet | null) : null;
    const hints = sheet ? centreHints(sheet) : null;

    return (
        <SidePanel
            variant="standard"
            title={person.display_name}
            description={personLifespan(person) ?? 'Podgląd osoby'}
            contentKey={person.id}
            onRequestClose={() => requestExit(close)}
            actions={
                <PersonPanelActions
                    person={person}
                    isRootPerson={isRootPerson}
                    hints={hints}
                />
            }
        >
            <PersonModalHeader person={person} isRootPerson={isRootPerson} />

            <PersonModal
                preview={personPreview}
                availableSources={availableSources}
            />

            <UnsavedChangesBar />
        </SidePanel>
    );
}
