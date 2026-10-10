import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import type { PersonPreview, TimelineEntry } from '@/types';

type Props = {
    person: PersonPreview;
};

function EventRow({ entry }: { entry: TimelineEntry }) {
    return (
        <li className="space-y-0.5 py-2.5">
            <p className="text-sm font-medium">
                {entry.label}
                {entry.relative !== null && (
                    <>
                        :{' '}
                        <PersonPreviewLink personId={entry.relative.id}>
                            {entry.relative.display_name}
                        </PersonPreviewLink>
                    </>
                )}
            </p>
            <p className="text-muted-foreground text-sm">
                {entry.type !== 'burial' && (
                    <span>{entry.date ?? 'data nieznana'}</span>
                )}
                {entry.place !== null && (
                    <span>
                        {entry.type !== 'burial' && ' · '}
                        {entry.place}
                    </span>
                )}
            </p>
        </li>
    );
}

/** Tab of the person panel listing the timeline as the server orders it. */
export function EventsTab({ person }: Props) {
    if (person.timeline.length === 0) {
        return <p className="text-muted-foreground text-sm">Brak wydarzeń.</p>;
    }

    return (
        <ul className="divide-y">
            {person.timeline.map((entry) => (
                <EventRow key={`${entry.type}-${entry.id}`} entry={entry} />
            ))}
        </ul>
    );
}
