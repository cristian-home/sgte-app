<?php

namespace Tests\Feature\Support;

use App\Support\Tz;
use Illuminate\Http\Request;

test('operation returns config value', function (): void {
    config(['app.operation_tz' => 'America/Bogota']);

    expect(Tz::operation())->toBe('America/Bogota');
});

test('viewer falls back to operation when request has no attribute', function (): void {
    config(['app.operation_tz' => 'America/Bogota']);
    $request = Request::create('/');

    expect(Tz::viewer($request))->toBe('America/Bogota');
});

test('viewer reads request attribute when set to a valid IANA TZ', function (): void {
    config(['app.operation_tz' => 'America/Bogota']);
    $request = Request::create('/');
    $request->attributes->set('viewer_tz', 'Europe/Madrid');

    expect(Tz::viewer($request))->toBe('Europe/Madrid');
});

test('viewer ignores invalid attribute and falls back', function (): void {
    config(['app.operation_tz' => 'America/Bogota']);
    $request = Request::create('/');
    $request->attributes->set('viewer_tz', 'Mars/Olympus_Mons');

    expect(Tz::viewer($request))->toBe('America/Bogota');
});

test('for accepts a string TZ', function (): void {
    expect(Tz::for('America/New_York'))->toBe('America/New_York');
    expect(Tz::for(''))->toBe(Tz::operation());
    expect(Tz::for('not/a/zone'))->toBe(Tz::operation());
});

test('for accepts an object with a timezone property', function (): void {
    $obj = (object) ['timezone' => 'Europe/Madrid'];

    expect(Tz::for($obj))->toBe('Europe/Madrid');
});

test('for falls back when timezone is missing or invalid', function (): void {
    config(['app.operation_tz' => 'America/Bogota']);
    expect(Tz::for((object) []))->toBe('America/Bogota');
    expect(Tz::for((object) ['timezone' => 'bogus']))->toBe('America/Bogota');
});

test('startOfDayInTzAsUtc projects midnight in the given TZ', function (): void {
    $instant = Tz::startOfDayInTzAsUtc('2026-05-08', 'America/Bogota');

    expect($instant->utc()->toIso8601String())->toBe('2026-05-08T05:00:00+00:00');
});

test('endOfDayInTzAsUtc returns next-day midnight (half-open interval)', function (): void {
    $instant = Tz::endOfDayInTzAsUtc('2026-05-08', 'America/Bogota');

    expect($instant->utc()->toIso8601String())->toBe('2026-05-09T05:00:00+00:00');
});

test('nowIn returns CarbonImmutable in the requested TZ', function (): void {
    $now = Tz::nowIn('Asia/Tokyo');

    expect($now->getTimezone()->getName())->toBe('Asia/Tokyo');
});
