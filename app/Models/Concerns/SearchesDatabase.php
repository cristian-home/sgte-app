<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

trait SearchesDatabase
{
    /**
     * Return columns to search. Supports:
     * - Local column: 'column_name'
     * - Related column (dot notation): 'relation.column_name'
     * - Composite columns (array): ['relation.column_a', 'relation.column_b']
     *
     * Composite columns are concatenated with spaces, enabling multi-field matching.
     *
     * @return array<int, string|array<int, string>>
     */
    abstract public function searchableColumns(): array;

    /**
     * Override in model to customize fuzzy match sensitivity (0.0–1.0).
     * Lower = more permissive, higher = stricter.
     */
    public function searchSimilarityThreshold(): float
    {
        return 0.3;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '' || empty($this->searchableColumns())) {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $this->applyTrigramSearch($query, $term);
        }

        // SQLite/MySQL fallback — plain substring match
        return $query->where(function (Builder $q) use ($term) {
            foreach ($this->searchableColumns() as $entry) {
                if (is_array($entry)) {
                    $this->addCompositeLikeCondition($q, $entry, $term);
                } elseif (str_contains($entry, '.')) {
                    [$relation, $field] = explode('.', $entry, 2);
                    $q->orWhereHas($relation, fn (Builder $sub) => $sub->where($field, 'LIKE', "%{$term}%"));
                } else {
                    $q->orWhere($entry, 'LIKE', "%{$term}%");
                }
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function applyTrigramSearch(Builder $query, string $term): Builder
    {
        $columns = $this->searchableColumns();
        $threshold = $this->searchSimilarityThreshold();

        return $query->where(function (Builder $q) use ($term, $columns, $threshold) {
            $grammar = $q->getGrammar();

            foreach ($columns as $entry) {
                if (is_array($entry)) {
                    $this->addCompositeTrigramCondition($q, $entry, $term, $threshold);
                } elseif (str_contains($entry, '.')) {
                    [$relation, $field] = explode('.', $entry, 2);
                    $q->orWhereHas($relation, function (Builder $sub) use ($term, $field, $threshold) {
                        $wrapped = $sub->getGrammar()->wrap($field);
                        $sub->where(function (Builder $inner) use ($term, $field, $wrapped, $threshold) {
                            $inner->where($field, 'ILIKE', "%{$term}%")
                                ->orWhereRaw(
                                    "word_similarity(?, coalesce({$wrapped}, '')) >= ?",
                                    [$term, $threshold]
                                );
                        });
                    });
                } else {
                    $wrapped = $grammar->wrap($entry);
                    $q->orWhere($entry, 'ILIKE', "%{$term}%");
                    $q->orWhereRaw(
                        "word_similarity(?, coalesce({$wrapped}, '')) >= ?",
                        [$term, $threshold]
                    );
                }
            }
        });
    }

    /**
     * Parse composite columns into a relation (if any) and bare field names.
     *
     * @param  array<int, string>  $columns
     * @return array{string|null, array<int, string>}
     */
    private function parseCompositeColumns(array $columns): array
    {
        $relation = null;
        $fields = [];

        foreach ($columns as $column) {
            if (str_contains($column, '.')) {
                [$rel, $field] = explode('.', $column, 2);
                $relation ??= $rel;
                $fields[] = $field;
            } else {
                $fields[] = $column;
            }
        }

        return [$relation, $fields];
    }

    /**
     * Build a SQL expression that concatenates fields with spaces.
     *
     * @param  array<int, string>  $fields
     */
    private function buildConcatExpression(Grammar $grammar, array $fields): string
    {
        $parts = array_map(
            fn (string $field) => "coalesce({$grammar->wrap($field)}, '')",
            $fields
        );

        return implode(" || ' ' || ", $parts);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addCompositeLikeCondition(Builder $query, array $columns, string $term): void
    {
        [$relation, $fields] = $this->parseCompositeColumns($columns);

        if ($relation) {
            $query->orWhereHas($relation, function (Builder $sub) use ($fields, $term) {
                $concat = $this->buildConcatExpression($sub->getGrammar(), $fields);
                $sub->whereRaw("({$concat}) LIKE ?", ["%{$term}%"]);
            });
        } else {
            $concat = $this->buildConcatExpression($query->getGrammar(), $fields);
            $query->orWhereRaw("({$concat}) LIKE ?", ["%{$term}%"]);
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addCompositeTrigramCondition(Builder $query, array $columns, string $term, float $threshold): void
    {
        [$relation, $fields] = $this->parseCompositeColumns($columns);

        if ($relation) {
            $query->orWhereHas($relation, function (Builder $sub) use ($fields, $term, $threshold) {
                $concat = $this->buildConcatExpression($sub->getGrammar(), $fields);
                $sub->where(function (Builder $inner) use ($concat, $term, $threshold) {
                    $inner->whereRaw("({$concat}) ILIKE ?", ["%{$term}%"])
                        ->orWhereRaw("word_similarity(?, {$concat}) >= ?", [$term, $threshold]);
                });
            });
        } else {
            $concat = $this->buildConcatExpression($query->getGrammar(), $fields);
            $query->orWhereRaw("({$concat}) ILIKE ?", ["%{$term}%"]);
            $query->orWhereRaw("word_similarity(?, {$concat}) >= ?", [$term, $threshold]);
        }
    }

    /**
     * Search with results ordered by relevance (best similarity score first).
     * On non-PostgreSQL drivers, falls back to search() without ordering.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearchWithRelevance(Builder $query, string $term): Builder
    {
        $this->scopeSearch($query, $term);

        $term = trim($term);

        if ($term === '' || empty($this->searchableColumns()) || $query->getConnection()->getDriverName() !== 'pgsql') {
            return $query;
        }

        return $this->addRelevanceOrdering($query, $term);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function addRelevanceOrdering(Builder $query, string $term): Builder
    {
        $grammar = $query->getGrammar();
        $scores = [];
        $bindings = [];

        foreach ($this->searchableColumns() as $entry) {
            if (is_array($entry)) {
                [$relation, $fields] = $this->parseCompositeColumns($entry);

                if ($relation) {
                    $scores[] = $this->buildRelationScoreExpression($grammar, $relation, $fields);
                } else {
                    $concat = $this->buildConcatExpression($grammar, $fields);
                    $scores[] = "word_similarity(?, {$concat})";
                }

                $bindings[] = $term;
            } elseif (str_contains($entry, '.')) {
                [$relation, $field] = explode('.', $entry, 2);
                $scores[] = $this->buildRelationScoreExpression($grammar, $relation, [$field]);
                $bindings[] = $term;
            } else {
                $wrapped = $grammar->wrap($entry);
                $scores[] = "word_similarity(?, coalesce({$wrapped}, ''))";
                $bindings[] = $term;
            }
        }

        $greatest = 'GREATEST('.implode(', ', $scores).')';

        return $query->orderByRaw("{$greatest} DESC", $bindings);
    }

    /**
     * Build a correlated subquery that computes the word_similarity score
     * for columns on a BelongsTo relationship.
     *
     * @param  array<int, string>  $fields
     */
    private function buildRelationScoreExpression(Grammar $grammar, string $relation, array $fields): string
    {
        $belongsTo = $this->$relation();
        $relatedTable = $grammar->wrapTable($belongsTo->getRelated()->getTable());
        $ownerKey = $grammar->wrap($belongsTo->getOwnerKeyName());
        $foreignKey = $grammar->wrap($belongsTo->getForeignKeyName());
        $parentTable = $grammar->wrapTable($this->getTable());

        $concat = $this->buildConcatExpression($grammar, $fields);

        return "coalesce((SELECT word_similarity(?, {$concat}) FROM {$relatedTable} WHERE {$relatedTable}.{$ownerKey} = {$parentTable}.{$foreignKey}), 0)";
    }
}
