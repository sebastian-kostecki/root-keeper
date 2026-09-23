import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    prefix: 'birth' | 'death';
    errors: Partial<Record<string, string>>;
};

export function PartialDateFields({ prefix, errors }: Props) {
    return (
        <div className="grid gap-4">
            <div className="grid grid-cols-3 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}-day`}>Dzień</Label>
                    <Input
                        id={`${prefix}-day`}
                        type="number"
                        name={`${prefix}.day`}
                        min={1}
                        max={31}
                    />
                    <InputError message={errors[`${prefix}.day`]} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}-month`}>Miesiąc</Label>
                    <Input
                        id={`${prefix}-month`}
                        type="number"
                        name={`${prefix}.month`}
                        min={1}
                        max={12}
                    />
                    <InputError message={errors[`${prefix}.month`]} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}-year`}>Rok</Label>
                    <Input
                        id={`${prefix}-year`}
                        type="number"
                        name={`${prefix}.year`}
                        min={1000}
                    />
                    <InputError message={errors[`${prefix}.year`]} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-place`}>Miejsce</Label>
                <Input
                    id={`${prefix}-place`}
                    type="text"
                    name={`${prefix}.place`}
                    maxLength={255}
                />
                <InputError message={errors[`${prefix}.place`]} />
            </div>

            <div className="flex items-center space-x-3">
                <Checkbox
                    id={`${prefix}-is_approximate`}
                    name={`${prefix}.is_approximate`}
                    value="1"
                />
                <Label htmlFor={`${prefix}-is_approximate`}>
                    Data przybliżona (około)
                </Label>
            </div>
            <InputError message={errors[`${prefix}.is_approximate`]} />
        </div>
    );
}
