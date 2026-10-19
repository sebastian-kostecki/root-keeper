import clsx from 'clsx';
import type { ReactNode } from 'react';

type TimelineMarker = 'primary' | 'muted' | 'hollow';
type TimelineSegment = 'solid' | 'dashed';

type TimelineItemProps = {
    marker: TimelineMarker;
    /** Narrow column left of the line (year, age). */
    aside: ReactNode;
    /** Line from this marker to the next item; null on the last item. */
    segmentBelow: TimelineSegment | null;
    children: ReactNode;
};

const markerStyles: Record<TimelineMarker, string> = {
    primary: 'bg-primary',
    muted: 'bg-tree-line',
    hollow: 'bg-background border-line border-tree-line',
};

/** Vertical timeline: a list whose items hang on one line. */
export function Timeline({ children }: { children: ReactNode }) {
    return <ul>{children}</ul>;
}

/**
 * One timeline row. The line colours combine `border-line` with `border-tree-line`, so they go
 * through clsx — tailwind-merge (cn) would drop the colour.
 */
export function TimelineItem({
    marker,
    aside,
    segmentBelow,
    children,
}: TimelineItemProps) {
    // The gap sits inside the columns, not on the <li>, so the line column spans the whole row
    // and reaches the next marker.
    const gap = segmentBelow !== null && 'pb-4';

    return (
        <li className="flex gap-3">
            <div className={clsx('w-14 shrink-0 text-right tabular-nums', gap)}>
                {aside}
            </div>
            <div className="relative flex w-3 shrink-0 justify-center">
                <span
                    aria-hidden
                    className={clsx(
                        'relative z-10 mt-1.5 size-3 rounded-full',
                        markerStyles[marker],
                    )}
                />
                {segmentBelow !== null && (
                    <span
                        aria-hidden
                        className={clsx(
                            'border-l-line border-tree-line absolute top-4.5 -bottom-1.5 left-1/2 -translate-x-1/2',
                            segmentBelow === 'dashed' && 'border-dashed',
                        )}
                    />
                )}
            </div>
            <div className={clsx('min-w-0 flex-1 space-y-0.5', gap)}>
                {children}
            </div>
        </li>
    );
}
