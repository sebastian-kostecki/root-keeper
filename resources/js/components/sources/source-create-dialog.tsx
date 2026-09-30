import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogFooter,
    FormDialogHeader,
} from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { SourceDescriptionFields } from '@/components/sources/source-description-fields';
import { SourceKindField } from '@/components/sources/source-kind-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SourceKind, SourceRecordType } from '@/types';

type Props = {
    trigger: React.ReactNode;
    kinds: { value: SourceKind; label: string }[];
    recordTypes: { value: SourceRecordType; label: string }[];
};

/** „Dodaj źródło”: a file and a kind are enough, the description is optional. */
export function SourceCreateDialog({ trigger, kinds, recordTypes }: Props) {
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState<SourceKind | ''>('');
    const [fileName, setFileName] = useState<string>();
    const bodyRef = useRef<HTMLDivElement>(null);

    const scrollFirstErrorIntoView = () => {
        requestAnimationFrame(() => {
            bodyRef.current
                ?.querySelector('[data-slot="input-error"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setKind('');
                setFileName(undefined);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <FormDialogContent>
                <FormDialogHeader
                    title="Dodaj źródło"
                    description="Prześlij skan aktu, zdjęcie nagrobka lub inny dokument"
                />

                <Form
                    {...SourceController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setKind('');
                        setFileName(undefined);
                    }}
                    onError={scrollFirstErrorIntoView}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <FormDialogBody ref={bodyRef}>
                                <div className="grid gap-2">
                                    <Label htmlFor="file">Plik</Label>
                                    <Input
                                        id="file"
                                        name="file"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(event) =>
                                            setFileName(
                                                event.target.files?.[0]?.name,
                                            )
                                        }
                                        autoFocus
                                    />
                                    <p className="text-muted-foreground text-sm">
                                        JPG, PNG lub WebP, do 20 MB
                                    </p>
                                    <InputError message={errors.file} />
                                </div>

                                {progress && (
                                    <div className="space-y-1">
                                        <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                            <div
                                                className="bg-primary h-full transition-all"
                                                style={{
                                                    width: `${progress.percentage ?? 0}%`,
                                                }}
                                            />
                                        </div>
                                        <p className="text-muted-foreground text-sm">
                                            {progress.percentage ?? 0}%
                                        </p>
                                    </div>
                                )}

                                <SourceKindField
                                    kinds={kinds}
                                    error={errors.kind}
                                    onValueChange={setKind}
                                />

                                <p className="text-sm font-medium">
                                    Opis (opcjonalnie)
                                </p>

                                <SourceDescriptionFields
                                    kind={kind}
                                    recordTypes={recordTypes}
                                    errors={errors}
                                    fileName={fileName}
                                />
                            </FormDialogBody>

                            <FormDialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Anuluj
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
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
