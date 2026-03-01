<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait SearchesDatabase
{
    /**
     * @return array<int, string>
     */
    abstract public function searchableColumns(): array;

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
        $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->where(function (Builder $q) use ($term, $operator) {
            foreach ($this->searchableColumns() as $column) {
                $q->orWhere($column, $operator, "%{$term}%");
            }
        });
    }
}
