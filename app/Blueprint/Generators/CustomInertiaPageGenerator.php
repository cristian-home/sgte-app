<?php

namespace App\Blueprint\Generators;

use Blueprint\Generators\Statements\InertiaPageGenerator;
use Blueprint\Models\Statements\InertiaStatement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class CustomInertiaPageGenerator extends InertiaPageGenerator
{
    protected function getStatementPath(string $view): string
    {
        $path = str_replace('.', '/', ltrim($view, '/'));

        return 'resources/js/pages/'.$path.$this->adapter['extension'];
    }

    protected function populateStub(string $stub, InertiaStatement $inertiaStatement): string
    {
        $stub = $this->getCustomStub();

        $data = $inertiaStatement->data();

        $propsType = '';
        $eslintDisable = '';

        if ($this->adapter['framework'] === 'vue') {
            $props = json_encode($data);
            $propsJson = json_encode($data);
        } elseif (! empty($data)) {
            $props = '{ '.implode(', ', $data).' }';
            $propsJson = $props;
            $types = array_map(fn ($item) => "{$item}: any", $data);
            $propsType = ': { '.implode('; ', $types).' }';
            $eslintDisable = "// eslint-disable-next-line @typescript-eslint/no-explicit-any\n";
        } else {
            // No props — emit no destructure at all to avoid `no-empty-pattern` ESLint rule
            $props = '';
            $propsJson = '{}';
        }

        // Use the full view as the component name (capitalized and stripped of periods/slashes)
        // e.g. post.index -> PostIndex
        $componentName = $this->adapter['framework'] === 'react' ? Str::studly(str_replace(['.', '/'], ' ', $inertiaStatement->view())) : null;

        // Ensure the view is formatted nicely for the Title
        $title = Str::title(str_replace(['.', '/'], ' ', ltrim($inertiaStatement->view(), '/')));

        [$routeImport, $routeHref] = $this->buildRoutePlaceholders($inertiaStatement->view());

        return str_replace([
            '{{ componentName }}',
            '{{ props }}',
            '{{ propsJson }}',
            '{{ propsType }}',
            '{{ eslintDisable }}',
            '{{ view }}',
            '{{ routeImport }}',
            '{{ routeHref }}',
        ], [
            $componentName,
            $props,
            $propsJson,
            $propsType,
            $eslintDisable,
            $title,
            $routeImport,
            $routeHref,
        ], $stub);
    }

    protected function getCustomStub(): string
    {
        $stubPath = base_path('stubs/blueprint/inertia.page.stub');

        if (File::exists($stubPath)) {
            return File::get($stubPath);
        }

        throw new RuntimeException("Custom React stub not found at: {$stubPath}");
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function buildRoutePlaceholders(string $view): array
    {
        $segments = array_values(array_filter(preg_split('/[\.\/]/', $view) ?: []));

        $routeName = $segments[0] ?? 'dashboard';
        $actionName = $segments[count($segments) - 1] ?? 'index';
        $routeVariable = Str::camel(str_replace('-', '_', $routeName));

        return [
            "import {$routeVariable} from '@/routes/{$routeName}';",
            "{$routeVariable}.{$actionName}().url",
        ];
    }
}
