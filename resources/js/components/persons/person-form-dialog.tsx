import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogFooter,
    FormDialogHeader,
} from '@/components/form-dialog';
import { PersonFormFields } from '@/components/persons/person-form-fields';
import { Button } from '@/components/ui/button';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import type { Gender, Religion } from '@/types';

type Props = {
    mode: 'create';
    genders: Gender[];
    religions: Religion[];
    trigger: React.ReactNode;
};

export function PersonFormDialog({ genders, religions, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const bodyRef = useRef<HTMLDivElement>(null);

    const scrollFirstErrorIntoView = () => {
        requestAnimationFrame(() => {
            bodyRef.current
                ?.querySelector('[data-slot="input-error"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <FormDialogContent>
                <FormDialogHeader
                    title="Dodaj osobę"
                    description="Podaj dane osoby, którą chcesz dodać do drzewa"
                />

                <Form
                    {...PersonController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={scrollFirstErrorIntoView}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormDialogBody ref={bodyRef}>
                                <PersonFormFields
                                    genders={genders}
                                    religions={religions}
                                    errors={errors}
                                />
                            </FormDialogBody>

                            <FormDialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setOpen(false)}
                                >
                                    Anuluj
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="create-person-button"
                                >
                                    Zapisz
                                </Button>
                            </FormDialogFooter>
                        </>
                    )}
                </Form>
            </FormDialogContent>
        </Dialog>
    );
}
