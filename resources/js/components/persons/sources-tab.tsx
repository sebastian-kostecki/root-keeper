import { SourceGroup } from '@/components/persons/source-group';
import type { PersonPreview, Source } from '@/types';

type Props = {
    person: PersonPreview;
    availableSources?: Source[];
};

export function SourcesTab({ person, availableSources }: Props) {
    return (
        <div className="space-y-4">
            {person.birth && (
                <SourceGroup
                    label="Urodzenie"
                    event={person.birth}
                    availableSources={availableSources}
                />
            )}

            {person.marriages.map((marriage) => (
                <SourceGroup
                    key={marriage.id}
                    label={`Małżeństwo z ${marriage.spouse?.display_name ?? 'nieznanym małżonkiem'}`}
                    event={marriage}
                    availableSources={availableSources}
                />
            ))}

            {person.death && (
                <SourceGroup
                    label="Zgon"
                    event={person.death}
                    availableSources={availableSources}
                />
            )}
        </div>
    );
}
