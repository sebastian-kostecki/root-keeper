import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { RemoveRelationDialog } from '@/components/persons/remove-relation-dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PersonPreview, Relative } from '@/types';

type Props = {
    kind: 'parent' | 'child';
    person: PersonPreview;
    relatives: Relative[];
};

export function RelativesCard({ kind, person, relatives }: Props) {
    const title = kind === 'parent' ? 'Rodzice' : 'Dzieci';

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {relatives.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {kind === 'parent'
                            ? 'Brak znanych rodziców. Dodasz ich na drzewie.'
                            : 'Brak znanych dzieci. Dodasz je na drzewie.'}
                    </p>
                ) : (
                    <div className="space-y-2">
                        {relatives.map((relative) => (
                            <div
                                key={relative.relation_id}
                                className="flex items-center justify-between gap-2"
                            >
                                <PersonPreviewLink
                                    personId={relative.id}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {relative.display_name}
                                    {(relative.birth_date ||
                                        relative.death_date) && (
                                        <span className="text-muted-foreground ml-2 text-sm">
                                            {[
                                                relative.birth_date &&
                                                    `ur. ${relative.birth_date}`,
                                                relative.death_date &&
                                                    `zm. ${relative.death_date}`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </PersonPreviewLink>

                                <RemoveRelationDialog
                                    relationId={relative.relation_id}
                                    parentName={
                                        kind === 'parent'
                                            ? relative.display_name
                                            : person.display_name
                                    }
                                    childName={
                                        kind === 'parent'
                                            ? person.display_name
                                            : relative.display_name
                                    }
                                    trigger={
                                        <button
                                            type="button"
                                            aria-label={`Usuń relację z ${relative.display_name}`}
                                            className="text-muted-foreground hover:text-destructive text-xs underline-offset-4 hover:underline"
                                        >
                                            Usuń relację
                                        </button>
                                    }
                                />
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
