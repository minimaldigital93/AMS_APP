<?php

/**
 * Admin → Settings is a list of links now: every card of the old single form
 * lives on its own page, and each page posts its own fields back to the same
 * updateBatch() route. Splitting a form across pages is a Blade edit with no
 * controller to catch a mistake, and a field dropped on the way out is a
 * setting nobody can change again — so every page is rendered here and every
 * input counted, against $defaultSettings rather than a hand-written list.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
});

/** Where each $defaultSettings category is edited now. */
function sectionRoutes(): array
{
    return [
        'company' => 'admin.settings.company',
        'owner' => 'admin.settings.owner',
        'billing' => 'admin.settings.billing',
        // The one card small enough to have stayed on the index.
        'system' => 'admin.settings.index',
    ];
}

it('lists every section as a link, in order', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->getContent();

    $rows = [
        __('messages.general_settings') => route('admin.settings.general'),
        __('messages.company_information') => route('admin.settings.company'),
        __('messages.owner_information') => route('admin.settings.owner'),
        __('messages.billing_late_fee_settings') => route('admin.settings.billing'),
        __('messages.expense_categories') => route('admin.settings.expense_categories'),
        __('messages.default_utility_prices') => route('admin.settings.utility_prices'),
        __('messages.payment_settings') => route('admin.settings.payment'),
        __('messages.payment_qr_code') => route('admin.settings.payment_qr'),
        // A card of its own: the rows above are how this business bills its
        // TENANTS; this one is the account's own plan.
        __('messages.billing_subscription') => route('admin.billing.index'),
        // The one card small enough to stay on the page.
        __('messages.system_preferences') => null,
    ];

    $at = -1;
    foreach ($rows as $label => $url) {
        $pos = strpos($html, e($label));
        expect($pos)->not->toBeFalse("missing row: {$label}")
            ->and($pos)->toBeGreaterThan($at, "out of order: {$label}");
        $at = $pos;

        // toContain() takes needles, not a message — assert the boolean so the
        // failure can name the row.
        if ($url !== null) {
            expect(str_contains($html, 'href="'.e($url).'"'))
                ->toBeTrue("row does not link anywhere: {$label}");
        }
    }
});

it('ships every settings field exactly once, on the page that owns it', function () {
    $defaults = app(App\Http\Controllers\Admin\SettingsController::class)
        ->index()->getData()['defaultSettings'];

    expect(array_keys($defaults))->toBe(array_keys(sectionRoutes()));

    $pages = [];
    foreach (sectionRoutes() as $category => $route) {
        $pages[$category] = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();
    }

    foreach ($defaults as $category => $fields) {
        foreach ($fields as $key => $default) {
            foreach ($pages as $onCategory => $html) {
                expect(substr_count($html, 'id="'.$key.'"'))
                    ->toBe($onCategory === $category ? 1 : 0, "{$key} on the {$onCategory} page");
            }
        }
    }

    // A field that is no longer anywhere is the failure this test exists for.
    expect(array_merge(...array_values($defaults)))
        ->toHaveKeys(['company_name', 'owner_gender', 'late_fee_percent', 'billing_cycle_day', 'khr_exchange_rate'])
        // The utility prices have their own page and their own controller.
        ->not->toHaveKey('utility_electricity_price')
        ->not->toHaveKey('utility_meter_auto_calc');
});

it('puts the logo inside the company page, above the company name', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('admin.settings.company'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'name="company_logo"'))->toBe(1)
        ->and(strpos($html, 'name="company_logo"'))
        ->toBeLessThan(strpos($html, 'id="company_name"'));
});

it('keeps the QR uploader and its account name on the QR page alone', function () {
    $qr = $this->actingAs($this->admin)->get(route('admin.settings.payment_qr'))->assertOk()->getContent();
    $index = $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()->getContent();

    expect(substr_count($qr, 'name="khqr_image"'))->toBe(1)
        ->and(substr_count($qr, 'name="khqr_account_name"'))->toBe(1)
        ->and($index)->not->toContain('name="khqr_image"')
        ->and($index)->not->toContain('name="khqr_account_name"');
});

it('saves one section without blanking the others', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.updateBatch'), ['settings' => ['company_name' => 'Sokha Residence']])
        ->assertRedirect(route('admin.settings.index'));

    // The owner page posts owner keys only — the company name must survive it.
    $this->actingAs($this->admin)
        ->put(route('admin.settings.updateBatch'), ['settings' => ['owner_name' => 'Chan Sophea']])
        ->assertRedirect(route('admin.settings.index'));

    auth()->login($this->admin);
    expect(settings('company_name'))->toBe('Sokha Residence')
        ->and(settings('owner_name'))->toBe('Chan Sophea');
});

it('lights System Settings in the nav on every settings page, payment and billing included', function () {
    // Payment Settings used to be a second nav entry beside System Settings,
    // which is why both patterns carried a not-payment exclusion. It is a row
    // on the index now, so the nav offers one way in and lights on all of them.
    // Billing & Subscription followed it in — same rule, same assertion.
    foreach (['admin.settings.index', 'admin.settings.payment_qr', 'admin.settings.payment', 'admin.billing.index'] as $route) {
        $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

        $active = (bool) preg_match(
            '/href="'.preg_quote(e(route('admin.settings.index')), '/').'"[^>]*\bactive\b/',
            $html
        );

        expect($active)->toBeTrue("System Settings is not lit on {$route}")
            ->and(substr_count($html, 'href="'.e(route('admin.settings.payment')).'"'))
            // Once: the row on the index page. Never a nav entry of its own.
            ->toBe($route === 'admin.settings.index' ? 1 : 0, "stray Payment Settings link on {$route}");
    }
});

it('gives Billing & Subscription no nav entry of its own, on either nav surface', function () {
    // The sidebar had a Billing link beside System Settings and the phone's
    // More sheet had a second one; both are gone, so the row on the settings
    // index is the only way in. A page with two entry points is how the two
    // drift into disagreeing about which one is lit.
    foreach (['admin.dashboard', 'admin.settings.index', 'admin.billing.index'] as $route) {
        $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

        expect(substr_count($html, 'href="'.e(route('admin.billing.index')).'"'))
            ->toBe($route === 'admin.settings.index' ? 1 : 0, "stray Billing link on {$route}");
    }
});

it('offers a lapsed admin no way back to Settings from billing', function () {
    // EnsureSubscriptionActive bounces every settings route to this page, so a
    // back arrow would return them to where they already stand. Renewing is
    // the way out.
    $sub = App\Models\Subscription::where('account_id', $this->admin->id)->firstOrFail();
    $sub->forceFill(['status' => 'expired', 'expires_at' => now()->subDay()])->save();

    // The back arrow specifically — the sidebar and the phone's More sheet both
    // link to Settings on every page, so a raw href count would only measure
    // the layout.
    $backArrow = '/href="'.preg_quote(e(route('admin.settings.index')), '/').'"[^>]*aria-label="'.preg_quote(e(__('messages.back')), '/').'"/';

    $lapsed = $this->actingAs($this->admin)->get(route('admin.billing.index'))->assertOk()->getContent();
    expect($lapsed)->toContain(__('messages.subscription_blocked_title'));
    expect((bool) preg_match($backArrow, $lapsed))->toBeFalse();

    // …and it IS there once the subscription is live, or the settings row would
    // open a page with no way back.
    $sub->forceFill(['status' => 'active', 'expires_at' => now()->addMonth()])->save();
    $active = $this->actingAs($this->admin)->get(route('admin.billing.index'))->assertOk()->getContent();
    expect((bool) preg_match($backArrow, $active))->toBeTrue();
});
