<?php

use Illuminate\Support\Facades\Route;

/**
 * The custom error pages must render self-contained (no app layout / compiled
 * asset / DB dependency) so they still work when those are the thing that broke.
 */
it('renders a branded 404 page', function () {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound();
    $response->assertSee('Page not found');
    $response->assertSee('Go to homepage');
});

it('renders a branded 403 page', function () {
    Route::get('/__test_403', fn () => abort(403));

    $this->get('/__test_403')
        ->assertForbidden()
        ->assertSee('Access denied');
});

it('renders a branded 500 page in production mode', function () {
    // Force the framework to use the error view rather than the debug trace.
    config(['app.debug' => false]);
    Route::get('/__test_500', fn () => throw new RuntimeException('boom'));

    $this->get('/__test_500')
        ->assertStatus(500)
        ->assertSee('Something went wrong');
});
