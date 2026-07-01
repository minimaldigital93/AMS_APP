<?php

// In production the app lives under https://minimaldigital.dev/ams_app behind
// nginx, which injects X-Forwarded-Prefix: /ams_app. Any URL built in JS from a
// hardcoded root-relative path ("/admin/...") loses that prefix and dies at
// nginx's 404 catch-all — this is exactly how assign-tenant broke in the PWA.
// The assign-tenant modal now lives on the merged "Floors And Rooms" page
// (admin.floors.index); this pins its JS-facing base URL to the prefix-aware
// url() helper output.

it('keeps the forwarded prefix in the assign-tenant base URL (floors & rooms page)', function () {
    $admin = makeAdmin();

    $html = $this->actingAs($admin)
        ->withHeader('X-Forwarded-Prefix', '/ams_app')
        ->get(route('admin.floors.index'))
        ->assertOk()
        ->getContent();

    $base = rtrim(config('app.url'), '/');
    expect($html)
        ->toContain('data-assign-base="'.$base.'/ams_app/admin/apartments"');
});

it('keeps working without a forwarded prefix (direct access)', function () {
    $admin = makeAdmin();

    $html = $this->actingAs($admin)
        ->get(route('admin.floors.index'))
        ->assertOk()
        ->getContent();

    $base = rtrim(config('app.url'), '/');
    expect($html)->toContain('data-assign-base="'.$base.'/admin/apartments"');
});
