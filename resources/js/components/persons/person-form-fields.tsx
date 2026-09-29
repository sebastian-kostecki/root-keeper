import Heading from '@/components/heading';
import {
    BirthFields,
    DeathFields,
    PersonalFields,
} from '@/components/persons/person-section-fields';
import { Separator } from '@/components/ui/separator';
import type { Gender, PersonFormData, Religion } from '@/types';

type Props = {
    genders: Gender[];
    religions: Religion[];
    errors: Partial<Record<string, string>>;
    person?: PersonFormData;
    /** Fixes the sex (value still submitted), e.g. by a tree slot. */
    lockedGender?: 'male' | 'female';
    /** Starting values for a new person. */
    defaults?: { last_name?: string | null };
};

/** The person form as one stack: the same section fields the modal cards use. */
export function PersonFormFields({
    religions,
    errors,
    person,
    lockedGender,
    defaults,
}: Props) {
    return (
        <>
            <PersonalFields
                religions={religions}
                errors={errors}
                person={person}
                lockedGender={lockedGender}
                defaults={defaults}
            />

            <Separator />

            <div className="space-y-4">
                <Heading variant="small" title="Urodzenie" />
                <BirthFields errors={errors} birth={person?.birth ?? null} />
            </div>

            <Separator />

            <div className="space-y-4">
                <Heading variant="small" title="Zgon" />
                <DeathFields
                    errors={errors}
                    death={person?.death ?? null}
                    isDeceased={person?.is_deceased ?? false}
                />
            </div>
        </>
    );
}
