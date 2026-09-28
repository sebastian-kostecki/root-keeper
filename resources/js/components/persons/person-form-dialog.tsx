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
import type { Gender, PersonFormData, Religion } from '@/types';

type Props =
    | {
          mode: 'create';
          genders: Gender[];
          religions: Religion[];
          trigger: React.ReactNode;
      }
    | {
          mode: 'edit';
          person: PersonFormData;
          genders: Gender[];
          religions: Religion[];
          trigger: React.ReactNode;
      };

export function PersonFormDialog(props: Props) {
    const { genders, religions, trigger } = props;
    const person = props.mode === 'edit' ? props.person : undefined;
    const isEdit = props.mode === 'edit';
    const [open, setOpen] = useState(false);
    const bodyRef = useRef<HTMLDivElement>(null);

    const scrollFirstErrorIntoView = () => {
        requestAnimationFrame(() => {
            bodyRef.current
                ?.querySelector('[class*="text-red-600"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <FormDialogContent>
                <FormDialogHeader
                    title={isEdit ? 'Edytuj osobę' : 'Dodaj osobę'}
                    description={
                        isEdit
                            ? 'Zmień dane osoby'
                            : 'Podaj dane osoby, którą chcesz dodać do drzewa'
                    }
                />

                <Form
                    {...(person
                        ? PersonController.update.form(person.id)
                        : PersonController.store.form())}
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
                                    person={person}
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
                                    data-test={
                                        isEdit
                                            ? 'update-person-button'
                                            : 'create-person-button'
                                    }
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
