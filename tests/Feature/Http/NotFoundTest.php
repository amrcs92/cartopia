<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the not-found page for unknown pages and missing products', function (string $url) {
    $this->get($url)->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('errors/NotFoundPage'));
})->with(['/no-such-page', '/products/no-such-product']);

it('keeps JSON 404s as JSON', function () {
    $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
});
