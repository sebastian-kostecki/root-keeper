import type { ReactNode } from 'react';
import { isUndescribed } from '@/lib/source-description';
import type { Source } from '@/types';

type Props = {
    source: Source;
    /** Rendered under the empty-state text, e.g. a „Dodaj opis” button. */
    emptyAction?: ReactNode;
};

function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="text-sm break-words">{children}</dd>
        </div>
    );
}

/** The description of a source, shown next to its image. */
export function SourceDetails({ source, emptyAction }: Props) {
    const sizeInMb = (source.size / (1024 * 1024)).toFixed(1);
    const dimensions =
        source.width !== null && source.height !== null
            ? `${source.width}×${source.height}`
            : 'brak danych';

    return (
        <div className="space-y-6">
            {isUndescribed(source) ? (
                <div className="space-y-3">
                    <p className="text-sm">To źródło nie ma jeszcze opisu.</p>
                    {emptyAction}
                </div>
            ) : null}

            <dl className="space-y-3">
                <Row label="Rodzaj">{source.kind_label}</Row>
                {source.record_type_label && (
                    <Row label="Typ aktu">{source.record_type_label}</Row>
                )}
                {source.entry_number && (
                    <Row label="Numer wpisu">{source.entry_number}</Row>
                )}
                {source.year !== null && <Row label="Rok">{source.year}</Row>}
                {source.provenance && (
                    <Row label="Pochodzenie">{source.provenance}</Row>
                )}
                {source.url && (
                    <Row label="Link">
                        <a
                            href={source.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            {hostOf(source.url)}
                        </a>
                    </Row>
                )}
                {source.note && (
                    <Row label="Notatka">
                        <span className="whitespace-pre-line">
                            {source.note}
                        </span>
                    </Row>
                )}
            </dl>

            <div className="space-y-3 border-t pt-4">
                <p className="text-sm font-medium">Plik</p>
                <dl className="space-y-3">
                    <Row label="Nazwa pliku">{source.original_name}</Row>
                    <Row label="Dodano">
                        {new Date(source.uploaded_at).toLocaleDateString(
                            'pl-PL',
                        )}
                    </Row>
                    <Row label="Rozmiar">{sizeInMb} MB</Row>
                    <Row label="Wymiary">{dimensions}</Row>
                </dl>
            </div>
        </div>
    );
}
