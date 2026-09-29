import { useLayoutEffect, useState } from 'react';
import type { RefObject } from 'react';

export type ScrollEdges = {
    canScrollLeft: boolean;
    canScrollRight: boolean;
};

/**
 * Horizontally centres the target inside its scroll container whenever `key`
 * changes, and reports whether content is hidden on either side.
 *
 * Sets `scrollLeft` directly instead of using `scrollIntoView`, which would
 * also scroll vertical ancestors and make the page jump.
 */
export function useCentreInScroll(
    containerRef: RefObject<HTMLElement | null>,
    targetRef: RefObject<HTMLElement | null>,
    key: string,
): ScrollEdges {
    const [edges, setEdges] = useState<ScrollEdges>({
        canScrollLeft: false,
        canScrollRight: false,
    });

    useLayoutEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const updateEdges = () => {
            const canScrollLeft = container.scrollLeft > 1;
            const canScrollRight =
                container.scrollLeft + container.clientWidth <
                container.scrollWidth - 1;

            setEdges((previous) =>
                previous.canScrollLeft === canScrollLeft &&
                previous.canScrollRight === canScrollRight
                    ? previous
                    : { canScrollLeft, canScrollRight },
            );
        };

        const target = targetRef.current;

        if (target && container.scrollWidth > container.clientWidth) {
            const containerRect = container.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            const targetOffset =
                targetRect.left - containerRect.left + container.scrollLeft;

            container.scrollLeft =
                targetOffset + targetRect.width / 2 - container.clientWidth / 2;
        }

        updateEdges();

        container.addEventListener('scroll', updateEdges, { passive: true });

        const observer = new ResizeObserver(updateEdges);
        observer.observe(container);

        return () => {
            container.removeEventListener('scroll', updateEdges);
            observer.disconnect();
        };
    }, [containerRef, targetRef, key]);

    return edges;
}
