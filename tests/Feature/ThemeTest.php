<?php

use App\Models\Theme;
use App\Services\Theme\ThemeService;
use Database\Seeders\ThemeSeeder;

beforeEach(function () {
    $this->seed(ThemeSeeder::class);
});

it('seeds the available light themes', function () {
    expect(Theme::count())->toBe(4);
    expect(Theme::pluck('slug')->all())->toEqualCanonicalizing(
        ['carbon-gray', 'platinum-silver', 'light-blue', 'light-green']
    );
    // All offered themes are light.
    expect(Theme::pluck('mode')->unique()->all())->toBe(['light']);
});

it('prunes themes that are no longer offered', function () {
    Theme::create([
        'slug' => 'obsidian-black', 'name' => 'Obsidian', 'mode' => 'dark',
        'tokens' => [], 'preview' => [], 'sort_order' => 99,
    ]);

    $this->seed(ThemeSeeder::class);

    expect(Theme::where('slug', 'obsidian-black')->exists())->toBeFalse();
});

it('shows the theme settings page to an admin', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->get(route('admin.settings.theme'))
        ->assertOk()
        ->assertSee('Carbon Gray')
        ->assertSee('Light Blue')
        ->assertSee('Light Green');
});

it('the theme route is not swallowed by the /admin/settings/{key} wildcard', function () {
    $admin = makeAdmin();

    // GET /admin/settings/theme must hit ThemeController@index (a full page),
    // not SettingsController@get (which returns JSON {key:'theme'}).
    $this->actingAs($admin)
        ->get(route('admin.settings.theme'))
        ->assertOk()
        ->assertSee('Live preview');
});

it('persists a chosen theme to the user and returns json + cookie', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.theme.update'), ['theme' => 'light-blue'])
        ->assertOk()
        ->assertJson(['theme' => 'light-blue'])
        ->assertCookie(ThemeService::COOKIE, 'light-blue');

    expect($admin->fresh()->theme)->toBe('light-blue');
});

it('rejects an unknown theme slug', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.theme.update'), ['theme' => 'does-not-exist'])
        ->assertStatus(422);

    expect($admin->fresh()->theme)->toBeNull();
});

it('resolves the active slug from the authenticated user', function () {
    $admin = makeAdmin(['theme' => 'light-green']);

    $this->actingAs($admin);

    expect(app(ThemeService::class)->currentSlug())->toBe('light-green');
});

it('falls back to the default theme for guests', function () {
    expect(app(ThemeService::class)->currentSlug())->toBe(Theme::DEFAULT_SLUG);
});

it('renders a token block for every theme in the stylesheet', function () {
    $css = app(ThemeService::class)->tokensCss();

    expect($css)->toContain(':root{');
    foreach (Theme::pluck('slug') as $slug) {
        expect($css)->toContain('[data-theme="'.$slug.'"]');
    }
});
