<?php

// The PWA must be installable ("Add to Home Screen") on BOTH deployments: the
// root domain (https://ams.minimaldigital.dev/) and the sub-path one behind
// nginx (https://…/ams_app/). It broke on the root domain because the manifest
// and service worker hardcoded the /ams_app prefix, so every icon + the
// start_url 404'd and the page fell outside the manifest scope. These tests pin
// the files to prefix-agnostic paths that resolve against wherever they're served.

it('uses only relative paths in the PWA manifest so it adapts to any base path', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest)->not->toBeNull();
    expect($manifest['start_url'])->not->toStartWith('/');
    expect($manifest['scope'])->not->toStartWith('/');

    foreach ($manifest['icons'] as $icon) {
        expect($icon['src'])->not->toStartWith('/');
    }
});

it('does not hardcode the /ams_app prefix in the service worker', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->not->toContain('/ams_app/');
    expect($sw)->toContain("new URL('./', self.location)");
});

it('renders prefix-aware manifest + service worker links and no hardcoded scope', function () {
    $html = $this->withHeader('X-Forwarded-Prefix', '/ams_app')
        ->get(route('login'))
        ->assertOk()
        ->getContent();

    $base = rtrim(config('app.url'), '/');

    expect($html)
        ->toContain('rel="manifest" href="'.$base.'/ams_app/manifest.webmanifest"')
        ->toContain($base.'/ams_app/sw.js')
        ->not->toContain("{ scope: '/ams_app/' }");
});

it('renders root-relative links when no forwarded prefix is present', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    $base = rtrim(config('app.url'), '/');

    expect($html)->toContain('rel="manifest" href="'.$base.'/manifest.webmanifest"');
});
