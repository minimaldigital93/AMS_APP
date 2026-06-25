<?php

use App\Models\Theme;
use App\Services\Theme\ThemeService;
use Database\Seeders\ThemeSeeder;

beforeEach(function () {
    $this->seed(ThemeSeeder::class);
});

it('seeds the five premium themes', function () {
    expect(Theme::count())->toBe(5);
    expect(Theme::pluck('slug')->all())->toContain(
        'executive-black', 'carbon-gray', 'midnight-slate', 'platinum-silver', 'obsidian-black'
    );
});

it('shows the theme settings page to an admin', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->get(route('admin.settings.theme'))
        ->assertOk()
        ->assertSee('Executive Black')
        ->assertSee('Obsidian Black');
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
        ->putJson(route('admin.settings.theme.update'), ['theme' => 'obsidian-black'])
        ->assertOk()
        ->assertJson(['theme' => 'obsidian-black'])
        ->assertCookie(ThemeService::COOKIE, 'obsidian-black');

    expect($admin->fresh()->theme)->toBe('obsidian-black');
});

it('rejects an unknown theme slug', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.theme.update'), ['theme' => 'does-not-exist'])
        ->assertStatus(422);

    expect($admin->fresh()->theme)->toBeNull();
});

it('resolves the active slug from the authenticated user', function () {
    $admin = makeAdmin(['theme' => 'midnight-slate']);

    $this->actingAs($admin);

    expect(app(ThemeService::class)->currentSlug())->toBe('midnight-slate');
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
