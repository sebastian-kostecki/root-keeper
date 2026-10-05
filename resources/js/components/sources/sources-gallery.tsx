import { Link } from '@inertiajs/react';
import { LinkIcon } from 'lucide-react';
import type { MouseEvent } from 'react';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { formatSourceSummary } from '@/lib/source-description';
import { eventsLabel } from '@/lib/sources-index';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { cn } from '@/lib/utils';
import type { IndexSource } from '@/types';

/** View A: cards in the server order (newest first); the gallery does not re-sort. */
export function SourcesGallery({
    sources,
    openId,
    hrefFor,
    onOpen,
}: {
    sources: IndexSource[];
    openId: number | null;
    hrefFor: (id: number) => string;
    onOpen: (event: MouseEvent<Element>, id: number) => void;
}) {
    return (
        <ul className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
            {sources.map((source) => {
                const events = eventsLabel(source.events_count);

                return (
                    <li key={source.id} className="min-w-0">
                        <Link
                            href={hrefFor(source.id)}
                            {...panelVisitOptions}
                            aria-current={
                                source.id === openId ? 'true' : undefined
                            }
                            data-state={
                                source.id === openId ? 'selected' : undefined
                            }
                            onClick={(event) => onOpen(event, source.id)}
                            className={cn(
                                'bg-card hover:bg-accent/50 focus-visible:ring-ring/50 flex h-full min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm transition-colors outline-none focus-visible:ring-[3px]',
                                'data-[state=selected]:border-primary data-[state=selected]:bg-accent data-[state=selected]:ring-primary data-[state=selected]:ring-2',
                            )}
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
                                {source.archive && (
                                    <p
                                        className="text-muted-foreground truncate text-xs"
                                        title={source.archive}
                                    >
                                        {source.archive}
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
