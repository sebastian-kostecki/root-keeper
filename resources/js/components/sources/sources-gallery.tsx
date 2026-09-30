import { Link } from '@inertiajs/react';
import { LinkIcon } from 'lucide-react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { formatSourceSummary } from '@/lib/source-description';
import { eventsLabel } from '@/lib/sources-index';
import type { IndexSource } from '@/types';

/** View A: cards in the server order (newest first); the gallery does not re-sort. */
export function SourcesGallery({ sources }: { sources: IndexSource[] }) {
    return (
        <ul className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
            {sources.map((source) => {
                const events = eventsLabel(source.events_count);

                return (
                    <li key={source.id} className="min-w-0">
                        <Link
                            href={SourceController.show(source.id)}
                            className="bg-card hover:bg-accent/50 focus-visible:ring-ring/50 flex h-full min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm transition-colors outline-none focus-visible:ring-[3px]"
                        >
                            <SourceThumbnail
                                source={source}
                                decorative
                                className="aspect-[4/3]"
                            />
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5 p-3">
                                <p
                                    className="line-clamp-2 text-sm font-medium break-words"
                                    title={source.display_name}
                                >
                                    {source.display_name}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {formatSourceSummary(source)}
                                </p>
                                {source.provenance && (
                                    <p
                                        className="text-muted-foreground truncate text-xs"
                                        title={source.provenance}
                                    >
                                        {source.provenance}
                                    </p>
                                )}
                                <p className="mt-auto flex items-center gap-1 pt-2 text-xs">
                                    {events !== null ? (
                                        <>
                                            <LinkIcon
                                                aria-hidden="true"
                                                className="size-3 shrink-0"
                                            />
                                            <span>potwierdza {events}</span>
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            bez powiązań
                                        </span>
                                    )}
                                </p>
                            </div>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}
