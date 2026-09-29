import { usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogHeader,
} from '@/components/form-dialog';
import { PersonPreview } from '@/components/persons/person-preview';
import { usePersonPreview } from '@/components/persons/person-preview-provider';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

/**
 * Modal hosted by the layout; open whenever the page carries a `personPreview`.
 */
export function PersonPreviewDialog() {
    const { personPreview, availableScans } = usePage().props;
    const { stack, back, close } = usePersonPreview();

    if (personPreview === null) {
        return null;
    }

    const { person } = personPreview;
    const lifespan = [
        person.birth?.date && `ur. ${person.birth.date}`,
        person.death?.date && `zm. ${person.death.date}`,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <Dialog
            open
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    close();
                }
            }}
        >
            <FormDialogContent>
                <FormDialogHeader
                    title={person.display_name}
                    description={lifespan || 'Podgląd osoby'}
                    descriptionClassName={lifespan ? undefined : 'sr-only'}
                    leading={
                        stack.length > 0 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="-ml-3 self-start"
                                onClick={back}
                            >
                                <ArrowLeft />
                                Wstecz
                            </Button>
                        ) : undefined
                    }
                />

                <FormDialogBody>
                    <PersonPreview
                        preview={personPreview}
                        availableScans={availableScans}
                    />
                </FormDialogBody>
            </FormDialogContent>
        </Dialog>
    );
}
