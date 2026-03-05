<?php

use Laravel\Dusk\Browser;

test('login page loads', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertSee('Iniciar');
    });
});
