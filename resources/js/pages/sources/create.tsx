import { Form, Head, Link } from '@inertiajs/react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as sourcesIndex } from '@/routes/sources';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Skany', href: sourcesIndex() },
    { title: 'Dodaj skan', href: SourceController.create() },
];

export default function Create() {
    return (
        <>
            <Head title="Dodaj skan" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Dodaj skan"
                    description="Prześlij zdjęcie lub skan dokumentu"
                />

                <Form {...SourceController.store.form()} className="space-y-6">
                    {({ processing, errors, progress }) => (
                        <>
                            <input
                                type="hidden"
                                name="kind"
                                value="metrical_record"
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
