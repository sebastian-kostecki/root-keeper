import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MarriageController from '@/actions/App/Http/Controllers/MarriageController';
import { PartialDateFields } from '@/components/persons/partial-date-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Marriage } from '@/types';

type Props = {
    marriage: Marriage;
    trigger: React.ReactNode;
};

export function MarriageFormDialog({ marriage, trigger }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Edytuj małżeństwo</DialogTitle>

                <Form
                    {...MarriageController.update.form(marriage.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogDescription>
                                Podaj datę i miejsce ślubu.
                            </DialogDescription>

                            <p className="text-sm">
                                {marriage.spouse?.display_name ??
                                    'Nieznany małżonek'}
                            </p>

                            <PartialDateFields
                                prefix="marriage"
                                errors={errors}
                                defaultValue={{
                                    ...marriage.date_parts,
                                    place: marriage.place,
                                }}
                                defaultChecked={
                                    marriage.date_parts.is_approximate
                                }
                            />

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setOpen(false)}
                                >
                                    Anuluj
                                </Button>

                                <Button type="submit" disabled={processing}>
                                    Zapisz
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
