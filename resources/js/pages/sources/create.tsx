import { Form, Head, Link } from '@inertiajs/react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SourceKindField } from '@/components/sources/source-kind-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as sourcesIndex } from '@/routes/sources';
import type { BreadcrumbItem, SourceKind } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Źródła', href: sourcesIndex() },
    { title: 'Dodaj źródło', href: SourceController.create() },
];

type Props = {
    kinds: { value: SourceKind; label: string }[];
};

export default function Create({ kinds }: Props) {
    return (
        <>
            <Head title="Dodaj źródło" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Dodaj źródło"
                    description="Prześlij skan aktu, zdjęcie nagrobka lub inny dokument"
                />

                <Form {...SourceController.store.form()} className="space-y-6">
                    {({ processing, errors, progress }) => (
                        <>
                            <SourceKindField
                                kinds={kinds}
                                error={errors.kind}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="file">Plik</Label>
                                <Input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
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

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Zapisz</Button>

                                <Button variant="outline" asChild>
                                    <Link href={sourcesIndex()}>Anuluj</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Create.layout = {
    breadcrumbs,
};
