<?php

namespace App\Blueprint\Generators;

use Blueprint\Generators\ControllerGenerator;
use Blueprint\Models\Controller;
use Blueprint\Models\Statements\QueryStatement;
use Blueprint\Models\Statements\SendStatement;
use Illuminate\Support\Str;

class CustomControllerGenerator extends ControllerGenerator
{
    protected function populateStub(string $stub, Controller $controller): string
    {
        $this->ensureNotificationImports($controller);

        if ($this->hasIndexCollectionQuery($controller)) {
            $this->addImport($controller, 'Spatie\QueryBuilder\QueryBuilder');
        }

        $stub = parent::populateStub($stub, $controller);
        $stub = $this->normalizeNotificationNamespaces($stub);

        $modelName = Str::singular($controller->prefix());
        $modelClass = Str::studly($modelName);
        $stub = $this->replaceModelCollectionQueries($stub, $modelClass);

        return $stub;
    }

    protected function ensureNotificationImports(Controller $controller): void
    {
        foreach ($controller->methods() as $statements) {
            foreach ($statements as $statement) {
                if (! $statement instanceof SendStatement || ! $statement->isNotification()) {
                    continue;
                }

                $this->addImport($controller, config('blueprint.namespace').'\\Notifications\\'.$statement->mail());

                if ($statement->type() === SendStatement::TYPE_NOTIFICATION_WITH_FACADE) {
                    $this->addImport($controller, 'Illuminate\\Support\\Facades\\Notification');
                }
            }
        }
    }

    protected function hasIndexCollectionQuery(Controller $controller): bool
    {
        $indexStatements = $controller->methods()['index'] ?? [];

        foreach ($indexStatements as $statement) {
            if (! $statement instanceof QueryStatement) {
                continue;
            }

            if (in_array($statement->operation(), ['all', 'get', 'paginate'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeNotificationNamespaces(string $stub): string
    {
        $legacyImport = 'use '.config('blueprint.namespace').'\\Notification\\';
        $newImport = 'use '.config('blueprint.namespace').'\\Notifications\\';

        return str_replace($legacyImport, $newImport, $stub);
    }

    protected function replaceModelCollectionQueries(string $stub, string $modelClass): string
    {
        $modelPattern = preg_quote($modelClass, '/');

        $stub = preg_replace_callback(
            '/(\n[ \t]*)(\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*)'.$modelPattern.'::(?:all|get)\(\)\s*;/',
            fn (array $matches): string => $this->buildQueryBuilderReplacement($matches[1], $matches[2], $modelClass, false),
            $stub,
        ) ?? $stub;

        return preg_replace_callback(
            '/(\n[ \t]*)(\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*)'.$modelPattern.'::paginate\(\)\s*;/',
            fn (array $matches): string => $this->buildQueryBuilderReplacement($matches[1], $matches[2], $modelClass, true),
            $stub,
        ) ?? $stub;
    }

    protected function buildQueryBuilderReplacement(string $linePrefix, string $assignment, string $modelClass, bool $paginate): string
    {
        $operation = $paginate ? 'paginate' : 'get';

        return $linePrefix.$assignment."QueryBuilder::for({$modelClass}::class)"
            .$linePrefix.'    ->allowedFilters([])'
            .$linePrefix.'    ->allowedSorts([])'
            .$linePrefix."    ->{$operation}();";
    }
}
