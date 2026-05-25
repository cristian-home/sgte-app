<?php

namespace App\Blueprint\Generators;

use Blueprint\Generators\PestTestGenerator;
use Blueprint\Models\Controller;

class CustomPestTestGenerator extends PestTestGenerator
{
    protected function populateStub(string $stub, Controller $controller): string
    {
        $stub = parent::populateStub($stub, $controller);

        $stub = str_replace("use function Pest\\Faker\\fake;\n", '', $stub);
        $stub = str_replace('\\Notification\\', '\\Notifications\\', $stub);

        return $stub;
    }
}
