<?php

namespace App\Services\Imports;

final readonly class HeaderCheck
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
    ) {}
}
