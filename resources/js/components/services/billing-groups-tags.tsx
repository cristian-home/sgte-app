import { CheckIcon } from 'lucide-react';
import {
    Tags,
    TagsContent,
    TagsEmpty,
    TagsGroup,
    TagsInput,
    TagsItem,
    TagsList,
    TagsTrigger,
    TagsValue,
} from '@/components/kibo-ui/tags';
import { BillingGroup, BillingGroupLabel } from '@/enums/BillingGroup';
import { cn } from '@/lib/utils';

interface BillingGroupsTagsProps {
    value: string[];
    onChange: (next: string[]) => void;
    id?: string;
    disabled?: boolean;
    invalid?: boolean;
}

export default function BillingGroupsTags({
    value,
    onChange,
    id,
    disabled,
    invalid,
}: BillingGroupsTagsProps) {
    const toggle = (group: BillingGroup) => {
        if (value.includes(group)) {
            onChange(value.filter((v) => v !== group));
        } else {
            onChange([...value, group]);
        }
    };

    const cases = Object.values(BillingGroup);

    return (
        <Tags>
            <TagsTrigger
                id={id}
                disabled={disabled}
                aria-invalid={invalid}
                className={cn(invalid && 'border-destructive')}
            >
                {value.map((v) => (
                    <TagsValue
                        key={v}
                        onRemove={
                            disabled
                                ? undefined
                                : () => toggle(v as BillingGroup)
                        }
                    >
                        {BillingGroupLabel[v as BillingGroup] ?? v}
                    </TagsValue>
                ))}
            </TagsTrigger>
            <TagsContent>
                <TagsInput placeholder="Buscar grupo..." />
                <TagsList>
                    <TagsEmpty />
                    <TagsGroup>
                        {cases.map((group) => {
                            const selected = value.includes(group);
                            return (
                                <TagsItem
                                    key={group}
                                    value={group}
                                    onSelect={() => toggle(group)}
                                >
                                    {BillingGroupLabel[group]}
                                    {selected && (
                                        <CheckIcon
                                            className="text-muted-foreground"
                                            size={14}
                                        />
                                    )}
                                </TagsItem>
                            );
                        })}
                    </TagsGroup>
                </TagsList>
            </TagsContent>
        </Tags>
    );
}
