<?php

use App\Models\Theme;
use App\Services\Theme\ThemeService;
use Database\Seeders\ThemeSeeder;

beforeEach(function () {
    $this->seed(ThemeSeeder::class);
});

it('seeds the full premium theme catalog', function () {
    expect(Theme::count())->toBe(12);
    expect(Theme::pluck('slug')->all())->toEqualCanonicalizing([
        // The two originals + six premium style themes …
        'carbon-gray', 'platinum-silver', 'skeuomorphism', 'neomorphism',
        'glassmorphism', 'minimal', 'brutalism', 'bento',
        // … the retained light tints …
        'light-blue', 'light-green',
        // … the dark theme (Phase 7 U6) …
        'midnight',
        // … and the gradient KPI theme.
        'aurora',
    ]);
    // Midnight is the one dark theme; everything else stays light.
    expect(Theme::where('mode', 'dark')->pluck('slug')->all())->toBe(['midnight']);
    expect(Theme::where('mode', 'light')->count())->toBe(11);
});

it('emits structural tokens for the style themes', function () {
    // Style themes override radius/shadow; the originals inherit CSS defaults.
    $brutalism = Theme::where('slug', 'brutalism')->firstOrFail();
    expect($brutalism->tokens['--radius'])->toBe('0px');

    $skeuo = Theme::where('slug', 'skeuomorphism')->firstOrFail();
    expect($skeuo->tokens)->toHaveKey('--sidebar-text');

    // Seeder-only hints (prefixed "__") must never reach the token map.
    expect(collect(array_keys($skeuo->tokens))->filter(
        fn ($k) => str_starts_with($k, '__')
    ))->toBeEmpty();

    // Carbon Gray stays colour-only — no structural override leaked in.
    $carbon = Theme::where('slug', 'carbon-gray')->firstOrFail();
    expect($carbon->tokens)->not->toHaveKey('--radius');
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

it('has no /admin/settings/{key} wildcard to swallow settings sub-pages', function () {
    $admin = makeAdmin();

    // There used to be a GET /admin/settings/{key} JSON endpoint (nothing ever
    // called it) that shadowed every settings sub-page registered after it —
    // /admin/settings/theme returned {key:'theme'} instead of the page. It is
    // gone: an unknown segment must 404, not leak a setting value.
    $this->actingAs($admin)
        ->get('/admin/settings/company_name')
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.settings.theme'))
        ->assertOk()
        ->assertSee(__('messages.theme_choose'));
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

    // Left on whatever it had — here the theme every new user starts on.
    expect($admin->fresh()->theme)->toBe(Theme::SIGNUP_SLUG);
});

it('starts a newly created user on the signup theme', function () {
    expect(makeAdmin()->theme)->toBe('aurora')
        ->and(Theme::where('slug', Theme::SIGNUP_SLUG)->exists())->toBeTrue();
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
