import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import {
    cardFocus,
    cardInteractive,
    personCardSurface,
} from '@/components/tree/card-styles';
import { CardBody } from '@/components/tree/sheet-person-card';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { genderCard } from '@/lib/gender-styles';
import { cn } from '@/lib/utils';
import type { AncestorNode } from '@/types';

type Props = {
    node: AncestorNode;
    isFocus?: boolean;
};

/**
 * A card on the ancestors chart. The whole person card is a link to the
 * person's panel; a missing parent is a static placeholder.
 */
export function AncestorCard({ node, isFocus = false }: Props) {
    if (node.kind === 'unknown') {
        return <UnknownPersonCard label={node.label} />;
    }

    const { person, repeated } = node;

    return (
        <PersonPreviewLink
            personId={person.id}
            aria-current={isFocus ? 'true' : undefined}
            className={cn(
                personCardSurface,
                'text-card-foreground shadow-xs',
                genderCard[person.gender ?? 'unknown'],
                cardInteractive,
                isFocus && cardFocus,
            )}
        >
            <CardBody person={person} variant="ancestor">
                {repeated && (
                    <span className="text-tree-meta mt-1 block text-sm">
                        Występuje kilka razy
                    </span>
                )}
            </CardBody>
        </PersonPreviewLink>
    );
}
