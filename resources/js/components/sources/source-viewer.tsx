import { TransformComponent, TransformWrapper } from 'react-zoom-pan-pinch';
import { Button } from '@/components/ui/button';

type Props = {
    src: string;
    alt: string;
};

export function SourceViewer({ src, alt }: Props) {
    return (
        <TransformWrapper
            initialScale={1}
            minScale={0.5}
            maxScale={32}
            centerOnInit
        >
            {({ zoomIn, zoomOut, resetTransform }) => (
                <div className="space-y-2">
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => zoomIn()}
                        >
                            Powiększ
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => zoomOut()}
                        >
                            Pomniejsz
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => resetTransform()}
                        >
                            Dopasuj
                        </Button>
                    </div>

                    <div className="bg-muted h-[calc(100vh-12rem)] w-full overflow-hidden rounded-md">
                        <TransformComponent
                            wrapperStyle={{ width: '100%', height: '100%' }}
                            contentStyle={{ width: '100%', height: '100%' }}
                        >
                            <img
                                src={src}
                                alt={alt}
                                className="h-full w-full object-contain"
                            />
                        </TransformComponent>
                    </div>
                </div>
            )}
        </TransformWrapper>
    );
}
