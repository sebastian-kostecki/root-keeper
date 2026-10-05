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

/**
 * Side panel hosted by the layout; open whenever the page carries a `personPreview`.
 */
export function PersonPanel() {
    const { personPreview, availableSources } = usePage().props;
    const { close } = usePersonPreview();
    const { requestExit } = useSectionEditor();

    if (personPreview === null) {
        return null;
    }

    const { person, isRootPerson } = personPreview;

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
