<?php

use Illuminate\Support\Facades\Route;

test('register routes are not registered', function () {
    expect(Route::has('register'))->toBeFalse();
    expect(Route::has('register.store'))->toBeFalse();
});

test('GET /register returns 404', function () {
    $this->get('/register')->assertNotFound();
});

test('POST /register returns 404', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});
