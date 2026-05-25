export type FilterOption = {
    value: string;
    label: string;
    icon?: React.ComponentType<{ className?: string }>;
};

export type FilterDefinition = {
    /** Spatie QueryBuilder filter name (matches `filter[name]=...` in URL). */
    name: string;
    /** Display label for the filter button. */
    label: string;
    /** Available options to choose from. */
    options: FilterOption[];
};
