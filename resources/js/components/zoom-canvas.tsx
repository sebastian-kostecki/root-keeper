import { useEffect, useRef, useState } from 'react';
import type { FocusEvent, PointerEvent, ReactNode, RefObject } from 'react';
import { TransformComponent, TransformWrapper } from 'react-zoom-pan-pinch';
import type { ReactZoomPanPinchContentRef } from 'react-zoom-pan-pinch';
import { Button } from '@/components/ui/button';
import { chooseFitView, TREE_FIT_FLOOR, TREE_FIT_MAX } from '@/lib/fit-view';
import { cn } from '@/lib/utils';

/** Marker class: panning cannot start on an element that has it. */
export const ZOOM_NO_PAN_CLASS = 'zoom-no-pan';

const WRAPPER_CLASS = 'zoom-canvas-wrapper';
const CONTENT_CLASS = 'zoom-canvas-content';
const DRAG_THRESHOLD = 5;

type Props = {
    children: ReactNode;
    /** Element the start view centres on when the whole content does not fit at the floor scale. */
    anchorRef: RefObject<HTMLElement | null>;
    /** Lowest start scale before the view anchors instead of showing the whole content. */
    floor?: number;
    /** Highest start scale, so a small content does not blow up on a large screen. */
    max?: number;
    /** Adds zoom buttons, Ctrl+wheel and pinch zoom, all within `[floor, max]`. Off by default. */
    zoomControls?: boolean;
    className?: string;
};

/**
 * Pan area that fills its parent; give the parent a definite height. Without
 * `zoomControls` there is no manual zoom: the scale depends on the screen only.
 * With it, the buttons, Ctrl+wheel and pinch zoom within `[floor, max]`.
 * Starts fitted (whole content, or anchored when that would be too small) and
 * fits again on resize until the author pans or zooms the view. "Dopasuj"
 * fits again. Remount it with a `key` to fit from scratch.
 */
export function ZoomCanvas({
    children,
    anchorRef,
    floor = TREE_FIT_FLOOR,
    max = TREE_FIT_MAX,
    zoomControls = false,
    className,
}: Props) {
    const controlsRef = useRef<ReactZoomPanPinchContentRef>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const movedRef = useRef(false);
    const fittedRef = useRef(false);
    const fitRef = useRef<(() => void) | null>(null);
    const dragRef = useRef({ x: 0, y: 0, down: false, dragged: false });
    const [ready, setReady] = useState(false);

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
            });

            if (!instance || !view) {
                return;
            }

            let x =
                (wrapper.clientWidth - content.offsetWidth * view.scale) / 2;
            let y =
                (wrapper.clientHeight - content.offsetHeight * view.scale) / 2;
            const anchor = anchorRef.current;

            if (view.kind === 'anchor' && anchor) {
                const current = instance.state.scale;
                const contentBox = content.getBoundingClientRect();
                const anchorBox = anchor.getBoundingClientRect();
                const anchorX =
                    (anchorBox.left + anchorBox.width / 2 - contentBox.left) /
                    current;
                const anchorY =
                    (anchorBox.top + anchorBox.height / 2 - contentBox.top) /
                    current;

                x = wrapper.clientWidth / 2 - anchorX * view.scale;
                y = wrapper.clientHeight / 2 - anchorY * view.scale;
            }

            void controlsRef.current?.setTransform(x, y, view.scale, 0);

            if (!fittedRef.current) {
                fittedRef.current = true;
                movedRef.current = false;
                setReady(true);
            }
        };

        fitRef.current = fit;

        let frame = 0;
        const schedule = () => {
            cancelAnimationFrame(frame);
            // After the library has aligned to its bounds on resize.
            frame = requestAnimationFrame(() => {
                if (!fittedRef.current || !movedRef.current) {
                    fit();
                }
            });
        };

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
        };
    }, [anchorRef, floor, max]);

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

        const frame = wrapper.getBoundingClientRect();
        const box = target.getBoundingClientRect();
        const margin = 16;
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
                        className={ZOOM_NO_PAN_CLASS}
                        onClick={() => zoomByButton('in')}
                    >
                        Powiększ
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className={ZOOM_NO_PAN_CLASS}
                        onClick={() => zoomByButton('out')}
                    >
                        Pomniejsz
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className={ZOOM_NO_PAN_CLASS}
                        onClick={fitAgain}
                    >
                        Dopasuj
                    </Button>
                </div>
            )}
            <TransformWrapper
                ref={controlsRef}
                initialScale={1}
                minScale={floor}
                maxScale={max}
                wheel={
                    zoomControls
                        ? { activationKeys: ['Control'] }
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
                    {children}
                </TransformComponent>
            </TransformWrapper>
        </div>
    );
}
