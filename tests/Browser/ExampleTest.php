<?php

use Laravel\Dusk\Browser;

test('home page loads', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->assertSee('Flood Watch');
    });
});
