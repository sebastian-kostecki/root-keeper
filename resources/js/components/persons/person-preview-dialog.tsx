import { usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormDialogContent } from '@/components/form-dialog';
import { PersonModal } from '@/components/persons/person-modal';
import { PersonModalHeader } from '@/components/persons/person-modal-header';
import { usePersonPreview } from '@/components/persons/person-preview-provider';
import {
    SectionEditorProvider,
    UnsavedChangesBar,
    useSectionEditor,
} from '@/components/persons/section-editor';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

/**
 * Modal hosted by the layout; open whenever the page carries a `personPreview`.
 */
export function PersonPreviewDialog() {
    const { personPreview } = usePage().props;

    if (personPreview === null) {
        return null;
    }

    return (
        <SectionEditorProvider personId={personPreview.person.id}>
            <PersonPreviewDialogContent />
        </SectionEditorProvider>
    );
}

function PersonPreviewDialogContent() {
    const { personPreview, availableSources } = usePage().props;
    const { stack, back, close } = usePersonPreview();
    const { isDirty, requestExit } = useSectionEditor();

    if (personPreview === null) {
        return null;
    }

    return (
        <Dialog
            open
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    requestExit(close);
                }
            }}
        >
            <FormDialogContent
                className="sm:h-[90vh]"
                onEscapeKeyDown={(event) => {
                    if (isDirty) {
                        event.preventDefault();
                        requestExit(close);
                    }
                }}
            >
                <PersonModalHeader
                    person={personPreview.person}
                    isRootPerson={personPreview.isRootPerson}
                    leading={
                        stack.length > 0 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="-ml-3 self-start"
                                onClick={() => requestExit(back)}
                            >
                                <ArrowLeft />
                                Wstecz
                            </Button>
                        ) : undefined
                    }
                />

                <PersonModal
                    preview={personPreview}
                    availableSources={availableSources}
                />

                <UnsavedChangesBar />
            </FormDialogContent>
        </Dialog>
    );
}
