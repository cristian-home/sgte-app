<?php

describe('Octane Installation', function () {
    test('laravel/octane is in composer.json require section', function () {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        expect($composer['require'])->toHaveKey('laravel/octane');
    });

    test('config/octane.php exists', function () {
        expect(file_exists(config_path('octane.php')))->toBeTrue();
    });

    test('octane server is configured as frankenphp', function () {
        expect(config('octane.server'))->toBe('frankenphp');
    });
});

describe('Production Docker Files', function () {
    test('Dockerfile exists with multi-stage build', function () {
        $dockerfile = file_get_contents(base_path('docker/production/Dockerfile'));

        expect($dockerfile)
            ->toContain('FROM node:')
            ->toContain('FROM composer:')
            ->toContain('FROM dunglas/frankenphp:')
            ->toContain('npm run build:ssr')
            ->toContain('composer install --no-dev')
            ->toContain('config:cache');
    });

    test('supervisord.conf contains all four programs', function () {
        $supervisord = file_get_contents(base_path('docker/production/supervisord.conf'));

        expect($supervisord)
            ->toContain('[program:octane]')
            ->toContain('[program:horizon]')
            ->toContain('[program:reverb]')
            ->toContain('[program:ssr]');
    });

    test('start-container exists and is executable', function () {
        $path = base_path('docker/production/start-container');

        expect(file_exists($path))->toBeTrue();
        expect(is_executable($path))->toBeTrue();
    });

    test('compose.staging.yaml contains all supporting services', function () {
        $compose = file_get_contents(base_path('compose.staging.yaml'));

        expect($compose)
            ->toContain('pgsql:')
            ->toContain('redis:')
            ->toContain('typesense:')
            ->toContain('minio:')
            ->toContain('mailpit:');
    });

    test('.dockerignore excludes dev artifacts', function () {
        $dockerignore = file_get_contents(base_path('.dockerignore'));

        expect($dockerignore)
            ->toContain('node_modules')
            ->toContain('vendor')
            ->toContain('.git');
    });
});
