import { useEffect, useRef, useState } from 'react';
import type { FocusEvent, PointerEvent, ReactNode, RefObject } from 'react';
import { TransformComponent, TransformWrapper } from 'react-zoom-pan-pinch';
import type { ReactZoomPanPinchContentRef } from 'react-zoom-pan-pinch';
import { Button } from '@/components/ui/button';
import {
    ANCESTORS_WHEEL_STEP,
    chooseAnchor,
    chooseFitView,
    COLUMN_MIN_REM,
    fillHeightRem,
    fitColumnRem,
    TREE_FIT_FLOOR,
    TREE_FIT_MAX,
} from '@/lib/fit-view';
import { cn } from '@/lib/utils';

/** Marker class: panning cannot start on an element that has it. */
export const ZOOM_NO_PAN_CLASS = 'zoom-no-pan';

const WRAPPER_CLASS = 'zoom-canvas-wrapper';
const CONTENT_CLASS = 'zoom-canvas-content';
const DRAG_THRESHOLD = 5;

/** Space kept between an element and the wrapper edge, when anchoring or revealing. */
const EDGE_MARGIN = 16;

type Props = {
    /**
     * Content; a function gets the card column in rem, fitted to the area when
     * `columns` is set, and the height in rem to fill at the start scale (0
     * without `fillHeight`, and until the first measure).
     */
    children:
        | ReactNode
        | ((columnRem: number, fillHeightRem: number) => ReactNode);
    /** Cards in one row at the fitted column; without it the column stays at its minimum. */
    columns?: number;
    /** Padding around the content, in rem, that the fitted column leaves out. */
    paddingRem?: number;
    /** Smallest card column in rem; the default is the column of the other views. */
    columnMinRem?: number;
    /** Gives the content the height to fill at the start scale, so it fills the area. Off by default. */
    fillHeight?: boolean;
    /**
     * Element(s) the start view centres on when the whole content does not fit
     * at the floor scale. Several refs centre on the box that holds them all.
     */
    anchorRef: RefObject<HTMLElement | null> | RefObject<HTMLElement | null>[];
    /** Used instead of `anchorRef` when those do not fit the wrapper at the anchored scale. */
    fallbackAnchorRef?: RefObject<HTMLElement | null>;
    /** Element that is scrolled into view after each fit, while it is set. */
    revealRef?: RefObject<HTMLElement | null>;
    /** Schedules a reveal of `revealRef` when it changes; null or undefined does nothing. */
    revealKey?: string | null;
    /** Lowest start scale before the view anchors instead of showing the whole content. */
    floor?: number;
    /** Scale of the anchored start view; `floor` by default. */
    anchorScale?: number;
    /** Highest start scale, so a small content does not blow up on a large screen. */
    max?: number;
    /** Adds zoom buttons, wheel and pinch zoom, from the whole content (or `floor`, if lower) to `max`. Off by default. */
    zoomControls?: boolean;
    className?: string;
};

/** Shift that brings `box` inside `frame` with `margin` to each edge; zero when it already is. */
function revealOffset(
    frame: DOMRect,
    box: DOMRect,
    margin: number,
): { dx: number; dy: number } {
    let dx = 0;
    let dy = 0;

    if (box.left < frame.left + margin) {
        dx = frame.left + margin - box.left;
    } else if (box.right > frame.right - margin) {
        dx = frame.right - margin - box.right;
    }

    if (box.top < frame.top + margin) {
        dy = frame.top + margin - box.top;
    } else if (box.bottom > frame.bottom - margin) {
        dy = frame.bottom - margin - box.bottom;
    }

    return { dx, dy };
}

/**
 * Pan area that fills its parent; give the parent a definite height. Without
 * `zoomControls` there is no manual zoom: the scale depends on the screen only.
 * With it, the buttons, wheel and pinch zoom from the whole content (or `floor`,
 * if lower) to `max`. Starts fitted (whole content, or anchored when that would
 * be too small) and fits again on resize until the author pans or zooms the
 * view. "Dopasuj" fits again. Remount it with a `key` to fit from scratch.
 * With `columns`, the card column is part of the fit: it is computed from the
 * area width at each fit, so the cards fill the area at scale about 1.
 * With `fillHeight`, the content also gets the height to fill at that scale.
 */
export function ZoomCanvas({
    children,
    columns,
    paddingRem = 0,
    columnMinRem,
    fillHeight = false,
    anchorRef,
    fallbackAnchorRef,
    revealRef,
    revealKey,
    floor = TREE_FIT_FLOOR,
    anchorScale = floor,
    max = TREE_FIT_MAX,
    zoomControls = false,
    className,
}: Props) {
    const controlsRef = useRef<ReactZoomPanPinchContentRef>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const movedRef = useRef(false);
    const fittedRef = useRef(false);
    const fitRef = useRef<(() => boolean) | null>(null);
    const scheduleRef = useRef<(() => void) | null>(null);
    const pendingRevealRef = useRef(false);
    const revealRefLatest = useRef(revealRef);
    const dragRef = useRef({ x: 0, y: 0, down: false, dragged: false });
    const [ready, setReady] = useState(false);
    const [columnRem, setColumnRem] = useState(COLUMN_MIN_REM);
    const columnRemRef = useRef(COLUMN_MIN_REM);
    const [fillRem, setFillRem] = useState(0);
    const fillRemRef = useRef(0);
    const [wholeScale, setWholeScale] = useState(floor);

    useEffect(() => {
        revealRefLatest.current = revealRef;
    });

    useEffect(() => {
        const container = containerRef.current;
        const wrapper = container?.querySelector<HTMLElement>(
            `.${WRAPPER_CLASS}`,
        );
        const content = container?.querySelector<HTMLElement>(
            `.${CONTENT_CLASS}`,
        );

        if (!wrapper || !content) {
            return;
        }

        const anchors = Array.isArray(anchorRef) ? anchorRef : [anchorRef];

        /** Box holding every given element, or null while none of them is laid out. */
        const boxOf = (elements: (HTMLElement | null)[]): DOMRect | null => {
            const boxes = elements
                .filter((element) => element !== null)
                .map((element) => element.getBoundingClientRect());

            if (boxes.length === 0) {
                return null;
            }

            const left = Math.min(...boxes.map((box) => box.left));
            const right = Math.max(...boxes.map((box) => box.right));
            const top = Math.min(...boxes.map((box) => box.top));
            const bottom = Math.max(...boxes.map((box) => box.bottom));

            return new DOMRect(left, top, right - left, bottom - top);
        };

        /**
         * Brings the column in line with the area width. Returns false when the
         * column changed: the content resize then runs the fit on a later pass.
         */
        const settleColumn = (): boolean => {
            if (columns === undefined) {
                return true;
            }

            const next = fitColumnRem({
                areaWidth: wrapper.clientWidth,
                rootFontSize: parseFloat(
                    getComputedStyle(document.documentElement).fontSize,
                ),
                columns,
                paddingRem,
                minRem: columnMinRem,
            });

            if (next === columnRemRef.current) {
                return true;
            }

            columnRemRef.current = next;
            setColumnRem(next);

            return false;
        };

        /**
         * Brings the height to fill in line with the area and the laid-out
         * content width. Returns false when it changed: the content resize then
         * runs the fit on a later pass.
         */
        const settleHeight = (): boolean => {
            if (!fillHeight) {
                return true;
            }

            const next = fillHeightRem({
                areaWidth: wrapper.clientWidth,
                areaHeight: wrapper.clientHeight,
                contentWidth: content.offsetWidth,
                rootFontSize: parseFloat(
                    getComputedStyle(document.documentElement).fontSize,
                ),
                floor,
                max,
            });

            if (next === fillRemRef.current) {
                return true;
            }

            fillRemRef.current = next;
            setFillRem(next);

            return false;
        };

        /** Zoom out reaches the whole content even when the start is anchored. */
        const measureWhole = () => {
            if (content.offsetWidth > 0 && content.offsetHeight > 0) {
                setWholeScale(
                    Math.min(
                        wrapper.clientWidth / content.offsetWidth,
                        wrapper.clientHeight / content.offsetHeight,
                    ),
                );
            }
        };

        const fit = () => {
            const instance = controlsRef.current?.instance;
            const view = chooseFitView({
                viewport: {
                    width: wrapper.clientWidth,
                    height: wrapper.clientHeight,
                },
                content: {
                    width: content.offsetWidth,
                    height: content.offsetHeight,
                },
                floor,
                max,
                anchorScale,
            });

            if (!instance || !view) {
                return;
            }

            measureWhole();

            let x =
                (wrapper.clientWidth - content.offsetWidth * view.scale) / 2;
            let y =
                (wrapper.clientHeight - content.offsetHeight * view.scale) / 2;

            if (view.kind === 'anchor') {
                const current = instance.state.scale;
                const primary = anchors.map((anchor) => anchor.current);
                let anchorBox = boxOf(primary);

                // Sum of the primary widths at the target scale, against the wrapper.
                if (anchorBox !== null && fallbackAnchorRef?.current) {
                    const primaryWidth =
                        primary.reduce(
                            (sum, element) =>
                                sum +
                                (element?.getBoundingClientRect().width ?? 0),
                            0,
                        ) *
                        (view.scale / current);

                    if (
                        chooseAnchor(
                            primaryWidth,
                            wrapper.clientWidth,
                            EDGE_MARGIN,
                        ) === 'fallback'
                    ) {
                        anchorBox = boxOf([fallbackAnchorRef.current]);
                    }
                }

                if (anchorBox !== null) {
                    const contentBox = content.getBoundingClientRect();
                    const anchorX =
                        (anchorBox.left +
                            anchorBox.width / 2 -
                            contentBox.left) /
                        current;
                    const anchorY =
                        (anchorBox.top +
                            anchorBox.height / 2 -
                            contentBox.top) /
                        current;

                    x = wrapper.clientWidth / 2 - anchorX * view.scale;
                    y = wrapper.clientHeight / 2 - anchorY * view.scale;
                }
            }

            void controlsRef.current?.setTransform(x, y, view.scale, 0);

            if (!fittedRef.current) {
                fittedRef.current = true;
                movedRef.current = false;
                setReady(true);
            }
        };

        /** Minimal shift that brings the element into the frame; leaves `movedRef` as it is. */
        const reveal = (target: HTMLElement) => {
            const instance = controlsRef.current?.instance;

            if (!instance) {
                return;
            }

            const { dx, dy } = revealOffset(
                wrapper.getBoundingClientRect(),
                target.getBoundingClientRect(),
                EDGE_MARGIN,
            );

            if (dx !== 0 || dy !== 0) {
                const { positionX, positionY, scale } = instance.state;

                void controlsRef.current?.setTransform(
                    positionX + dx,
                    positionY + dy,
                    scale,
                    0,
                );
            }
        };

        /**
         * Column, then height (which needs the laid-out content width), then the
         * fit: a pass that changes the column or the height leaves the rest to
         * the next one.
         */
        const refit = (): boolean => {
            if (!settleColumn() || !settleHeight()) {
                return false;
            }

            fit();

            return true;
        };

        fitRef.current = refit;

        let frame = 0;
        const schedule = () => {
            cancelAnimationFrame(frame);
            // After the library has aligned to its bounds on resize. A pending
            // reveal runs after the fit of the same pass, so the fit cannot undo it.
            frame = requestAnimationFrame(() => {
                if (fittedRef.current && movedRef.current) {
                    // The manual view stays, but zoom out still reaches the whole content at the new size.
                    measureWhole();
                } else if (!refit()) {
                    return;
                }

                const target = revealRefLatest.current?.current;

                if (pendingRevealRef.current || target) {
                    pendingRevealRef.current = false;

                    if (target) {
                        reveal(target);
                    }
                }
            });
        };
        scheduleRef.current = schedule;

        /** Find-in-page or scroll-into-view scrolls the clipped wrapper natively: turn that into a pan. */
        const onScroll = () => {
            const { scrollLeft, scrollTop } = wrapper;
            const instance = controlsRef.current?.instance;

            if ((scrollLeft === 0 && scrollTop === 0) || !instance) {
                return;
            }

            wrapper.scrollLeft = 0;
            wrapper.scrollTop = 0;

            const { positionX, positionY, scale } = instance.state;

            movedRef.current = true;
            void controlsRef.current?.setTransform(
                positionX - scrollLeft,
                positionY - scrollTop,
                scale,
                0,
            );
        };

        const observer = new ResizeObserver(schedule);
        observer.observe(wrapper);
        observer.observe(content);
        wrapper.addEventListener('scroll', onScroll);
        schedule();

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            wrapper.removeEventListener('scroll', onScroll);
            fitRef.current = null;
            scheduleRef.current = null;
        };
    }, [
        anchorRef,
        fallbackAnchorRef,
        floor,
        anchorScale,
        max,
        columns,
        paddingRem,
        columnMinRem,
        fillHeight,
    ]);

    // A new column or height to fill does not always resize the content (rows
    // already at their maximum), so the pass that runs the fit is scheduled here,
    // after the commit, instead of waiting for a resize that may never come. It
    // cannot loop: the next pass finds both values settled and fits.
    useEffect(() => {
        scheduleRef.current?.();
    }, [columnRem, fillRem]);

    // Separate effect: a change of the revealed slot must not detach the observer above.
    useEffect(() => {
        if (revealKey === undefined || revealKey === null) {
            return;
        }

        pendingRevealRef.current = true;
        scheduleRef.current?.();
    }, [revealKey]);

    const markMoved = () => {
        movedRef.current = true;
    };

    /** Manual zoom keeps the author's view: a later resize no longer refits it. */
    const zoomByButton = (direction: 'in' | 'out') => {
        markMoved();

        if (direction === 'in') {
            void controlsRef.current?.zoomIn();
        } else {
            void controlsRef.current?.zoomOut();
        }
    };

    /** "Dopasuj" runs the same fit as the start view. */
    const fitAgain = () => {
        movedRef.current = false;
        fitRef.current?.();
    };

    /** A press that jitters under the drag threshold is a click, not a pan. */
    const onPanning = () => {
        if (dragRef.current.dragged) {
            markMoved();
        }
    };

    /** Portalled menus and dialogs bubble through this tree; only the canvas itself pans. */
    const isInsideCanvas = (target: EventTarget) => {
        const wrapper = controlsRef.current?.instance.wrapperComponent;

        return target instanceof Node && wrapper?.contains(target) === true;
    };

    const onPointerDownCapture = (event: PointerEvent) => {
        if (!isInsideCanvas(event.target)) {
            return;
        }

        dragRef.current = {
            x: event.clientX,
            y: event.clientY,
            down: true,
            dragged: false,
        };
    };

    const onPointerMoveCapture = (event: PointerEvent) => {
        if (!isInsideCanvas(event.target)) {
            return;
        }

        const drag = dragRef.current;

        if (
            drag.down &&
            Math.hypot(event.clientX - drag.x, event.clientY - drag.y) >
                DRAG_THRESHOLD
        ) {
            drag.dragged = true;
        }
    };

    const onPointerUpCapture = () => {
        dragRef.current.down = false;
    };

    /** A click that ends a pan must not open a card or slot under the pointer. */
    const onClickCapture = (event: React.MouseEvent) => {
        // Keyboard clicks (detail 0) never end a pan.
        if (event.detail === 0 || !isInsideCanvas(event.target)) {
            return;
        }

        if (dragRef.current.dragged) {
            dragRef.current.dragged = false;
            event.preventDefault();
            event.stopPropagation();
        }
    };

    /** Tab to an element outside the frame: undo the native scroll and pan it into view. */
    const onFocus = (event: FocusEvent) => {
        const instance = controlsRef.current?.instance;
        const wrapper = instance?.wrapperComponent;
        const target = event.target;

        // React focus events also bubble from portals (menus, dialogs).
        if (
            !instance ||
            !wrapper ||
            !(target instanceof HTMLElement) ||
            !wrapper.contains(target)
        ) {
            return;
        }

        wrapper.scrollLeft = 0;
        wrapper.scrollTop = 0;

        const { dx, dy } = revealOffset(
            wrapper.getBoundingClientRect(),
            target.getBoundingClientRect(),
            EDGE_MARGIN,
        );

        if (dx !== 0 || dy !== 0) {
            const { positionX, positionY, scale } = instance.state;

            markMoved();
            void controlsRef.current?.setTransform(
                positionX + dx,
                positionY + dy,
                scale,
                0,
            );
        }
    };

    return (
        <div
            ref={containerRef}
            className={cn(
                'relative min-h-64 flex-1 overflow-hidden',
                className,
            )}
            onPointerDownCapture={onPointerDownCapture}
            onPointerMoveCapture={onPointerMoveCapture}
            onPointerUpCapture={onPointerUpCapture}
            onPointerCancelCapture={onPointerUpCapture}
            onClickCapture={onClickCapture}
            onFocus={onFocus}
        >
            {zoomControls && (
                <div className="absolute top-2 right-2 z-10 flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => zoomByButton('in')}
                    >
                        Powiększ
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => zoomByButton('out')}
                    >
                        Pomniejsz
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={fitAgain}
                    >
                        Dopasuj
                    </Button>
                </div>
            )}
            <TransformWrapper
                ref={controlsRef}
                initialScale={1}
                minScale={zoomControls ? Math.min(floor, wholeScale) : floor}
                maxScale={max}
                wheel={
                    zoomControls
                        ? { step: ANCESTORS_WHEEL_STEP }
                        : { disabled: true }
                }
                pinch={{ disabled: !zoomControls }}
                doubleClick={{ disabled: true }}
                panning={{ excluded: [ZOOM_NO_PAN_CLASS] }}
                onPanning={onPanning}
                onWheelStart={markMoved}
                onPinchStart={markMoved}
            >
                <TransformComponent
                    wrapperClass={WRAPPER_CLASS}
                    contentClass={CONTENT_CLASS}
                    wrapperStyle={{ width: '100%', height: '100%' }}
                    contentStyle={{ visibility: ready ? 'visible' : 'hidden' }}
                >
                    {typeof children === 'function'
                        ? children(columnRem, fillHeight ? fillRem : 0)
                        : children}
                </TransformComponent>
            </TransformWrapper>
        </div>
    );
}
