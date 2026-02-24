<?php

namespace App\Blueprint\Generators\Statements;

use Blueprint\Blueprint;
use Blueprint\Generators\Statements\NotificationGenerator;
use Blueprint\Models\Statements\SendStatement;

class CustomNotificationGenerator extends NotificationGenerator
{
    protected function getStatementPath(string $name): string
    {
        return Blueprint::appPath().'/Notifications/'.$name.'.php';
    }

    protected function populateStub(string $stub, SendStatement $sendStatement): string
    {
        $stub = str_replace('{{ namespace }}', config('blueprint.namespace').'\\Notifications', $stub);
        $stub = str_replace('{{ class }}', $sendStatement->mail(), $stub);
        $stub = str_replace('{{ properties }}', $this->populateConstructor('message', $sendStatement), $stub);

        return $stub;
    }
}
