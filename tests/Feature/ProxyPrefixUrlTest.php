<?php

// In production the app lives under https://minimaldigital.dev/ams_app behind
// nginx, which injects X-Forwarded-Prefix: /ams_app. Any URL built in JS from a
// hardcoded root-relative path ("/admin/...") loses that prefix and dies at
// nginx's 404 catch-all — this is exactly how assign-tenant broke in the PWA.
// These tests pin the JS-facing URLs to the prefix-aware url() helper output.

it('keeps the forwarded prefix in the assign-tenant and leave URLs (admin apartments page)', function () {
    $admin = makeAdmin();

    $html = $this->actingAs($admin)
        ->withHeader('X-Forwarded-Prefix', '/ams_app')
        ->get(route('admin.apartments.index'))
        ->assertOk()
        ->getContent();

    $base = rtrim(config('app.url'), '/');
    expect($html)
        ->toContain('data-assign-base="'.$base.'/ams_app/admin/apartments"')
        ->toContain('/ams_app/admin/tenants/${tenantId}/leave');
});

it('keeps working without a forwarded prefix (direct access)', function () {
    $admin = makeAdmin();

    $html = $this->actingAs($admin)
        ->get(route('admin.apartments.index'))
        ->assertOk()
        ->getContent();

    $base = rtrim(config('app.url'), '/');
    expect($html)->toContain('data-assign-base="'.$base.'/admin/apartments"');
});
