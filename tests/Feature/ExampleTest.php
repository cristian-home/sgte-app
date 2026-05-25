<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(302);
    $response->assertRedirect(route('dashboard'));
});
