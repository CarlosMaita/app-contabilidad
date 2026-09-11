<?php

it('redirects the root to login for guests', function () {
    $this->get('/')->assertRedirect(route('login'));
});
