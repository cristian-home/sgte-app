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
import { cn } from '@/lib/utils';

export interface BillingGroupOption {
    id: number;
    code: string;
    name: string;
    active?: boolean;
}

interface BillingGroupsTagsProps {
    value: number[];
    onChange: (next: number[]) => void;
    /**
     * Lista de grupos disponibles. En edit-mode el padre debería
     * concatenar los grupos actualmente asociados al servicio (aunque
     * estén inactivos) con los grupos activos del catálogo, deduplicando
     * por id — así el usuario puede destildar un grupo desactivado.
     */
    options: BillingGroupOption[];
    id?: string;
    disabled?: boolean;
    invalid?: boolean;
}

export default function BillingGroupsTags({
    value,
    onChange,
    options,
    id,
    disabled,
    invalid,
}: BillingGroupsTagsProps) {
    const toggle = (groupId: number) => {
        if (value.includes(groupId)) {
            onChange(value.filter((v) => v !== groupId));
        } else {
            onChange([...value, groupId]);
        }
    };

    const optionsById = new Map(options.map((o) => [o.id, o]));

    return (
        <Tags>
            <TagsTrigger
                id={id}
                disabled={disabled}
                aria-invalid={invalid}
                className={cn(invalid && 'border-destructive')}
            >
                {value.map((v) => {
                    const opt = optionsById.get(v);
                    return (
                        <TagsValue
                            key={v}
                            onRemove={disabled ? undefined : () => toggle(v)}
                        >
                            {opt?.name ?? `#${v}`}
                            {opt && opt.active === false && (
                                <span className="ml-1 text-xs text-muted-foreground italic">
                                    (inactivo)
                                </span>
                            )}
                        </TagsValue>
                    );
                })}
            </TagsTrigger>
            <TagsContent>
                <TagsInput placeholder="Buscar grupo..." />
                <TagsList>
                    <TagsEmpty />
                    <TagsGroup>
                        {options.map((group) => {
                            const selected = value.includes(group.id);
                            return (
                                <TagsItem
                                    key={group.id}
                                    value={group.name}
                                    onSelect={() => toggle(group.id)}
                                >
                                    {group.name}
                                    {group.active === false && (
                                        <span className="ml-2 text-xs text-muted-foreground italic">
                                            (inactivo)
                                        </span>
                                    )}
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
