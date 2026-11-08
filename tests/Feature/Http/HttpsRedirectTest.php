<?php

// When the app's URL is https, a page opened over http is sent to the same
// page over https. Otherwise the admin panel's photo uploader fetches the
// https photo from an http page, the browser blocks it as another site, and
// the photo never finishes loading.

beforeEach(function () {
    config(['app.url' => 'https://cartopia.test']);
});

it('sends an http page to the same page over https', function (string $path) {
    $this->get('http://cartopia.test'.$path)
        ->assertStatus(301)
        ->assertRedirect(rtrim('https://cartopia.test'.$path, '/'));
})->with(['/', '/products?q=milk', '/admin/products/chocolate/edit']);

it('leaves https pages alone', function () {
    $this->get('https://cartopia.test/')->assertOk();
});

it('never redirects a POST, so provider webhooks still arrive', function () {
    $this->post('http://cartopia.test/webhooks/stripe')->assertStatus(400);
});

it('leaves http alone when the app itself runs on http', function () {
    config(['app.url' => 'http://localhost']);

    $this->get('http://localhost/')->assertOk();
});
