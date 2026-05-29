<?php

it('redirects root to the dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});
