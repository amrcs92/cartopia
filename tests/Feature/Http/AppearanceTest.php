<?php

it('renders dark mode on the first paint when the cookie says dark', function () {
    $this->withUnencryptedCookie('appearance', 'dark')->get(route('home'))
        ->assertOk()->assertSee('class="dark"', false);
});

it('does not render dark mode when the cookie says light', function () {
    $this->withUnencryptedCookie('appearance', 'light')->get(route('home'))
        ->assertOk()->assertDontSee('class="dark"', false);
});
