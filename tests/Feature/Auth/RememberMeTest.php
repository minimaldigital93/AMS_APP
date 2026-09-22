<?php

/**
 * "Remember me" as a USER experiences it — in a browser tab and in the
 * installed PWA — not as a checkbox that posts a flag.
 *
 * The flag was always honoured (LoginRequest passes it to Auth::attempt), the
 * recaller cookie was always issued, and the guard always resolved the user
 * back out of it. What was broken was the DOOR: `/` is the PWA's start_url
 * ("./?source=pwa") and the address browser users type, and it rendered the
 * login form unconditionally — so a remembered user was asked to sign in on
 * every cold launch while being, at that very moment, signed in.
 *
 * These tests walk the cookie by hand (issue it at /login, then present it on
 * a session-less request) because that is the only way to reproduce a browser
 * that was closed and reopened; actingAs() would hide the bug entirely.
 */

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;

/** Sign in with the box ticked and hand back the recaller cookie a browser would keep. */
function rememberedLogin(User $user): array
{
    $response = test()->post('/login', [
        'phone' => $user->phone,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $name = Auth::guard('web')->getRecallerName();
    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === $name);

    expect($cookie)->not->toBeNull('login issued no "remember me" cookie');

    return [$name, CookieValuePrefix::remove(decrypt($cookie->getValue(), false))];
}

/** Everything the browser keeps across a restart EXCEPT the session. */
function afterBrowserRestart(array $recaller)
{
    test()->flushSession();
    app('auth')->forgetGuards();

    return test()->withCookie($recaller[0], $recaller[1]);
}

function rememberMeAdmin(): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('admin');

    return $user;
}

beforeEach(function () {
    foreach (['superadmin', 'admin', 'supervisor', 'tenant'] as $role) {
        Spatie\Permission\Models\Role::findOrCreate($role, 'web');
    }
});

it('issues a long-lived remember cookie only when the box is ticked', function () {
    $user = rememberMeAdmin();
    $name = Auth::guard('web')->getRecallerName();

    $without = $this->post('/login', ['phone' => $user->phone, 'password' => 'password']);
    expect(collect($without->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === $name))->toBeNull();

    $this->post('/logout');

    [, $value] = rememberedLogin($user);
    expect($value)->toContain($user->fresh()->remember_token);
});

it('expires the remember cookie after the configured window, not Laravel\'s 400 days', function () {
    $user = rememberMeAdmin();
    $name = Auth::guard('web')->getRecallerName();

    $response = $this->post('/login', [
        'phone' => $user->phone, 'password' => 'password', 'remember' => 'on',
    ]);

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === $name);
    $days = ($cookie->getExpiresTime() - now()->timestamp) / 86400;

    // ~90 days. The assertion is a range because the cookie is stamped against
    // the wall clock a moment after now() is read here.
    expect($days)->toBeGreaterThan(89.9)->toBeLessThan(90.1);
    expect((int) config('auth.remember_duration'))->toBe(60 * 24 * 90);
});

it('honours a re-configured window', function () {
    config(['auth.remember_duration' => 60 * 24 * 7]);

    $user = rememberMeAdmin();
    $name = Auth::guard('web')->getRecallerName();

    $response = $this->post('/login', [
        'phone' => $user->phone, 'password' => 'password', 'remember' => 'on',
    ]);

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === $name);

    expect(($cookie->getExpiresTime() - now()->timestamp) / 86400)->toBeGreaterThan(6.9)->toBeLessThan(7.1);
});

it('signs a remembered user back in after the session is gone', function () {
    $user = rememberMeAdmin();
    $recaller = rememberedLogin($user);

    afterBrowserRestart($recaller)->get(route('admin.dashboard'));

    expect(Auth::guard('web')->id())->toBe($user->id);
});

it('never shows the login form to a signed-in user at / — the PWA start_url', function () {
    $user = rememberMeAdmin();

    // A live session.
    $this->actingAs($user)->get('/?source=pwa')->assertRedirect(route('dashboard'));

    // And a cold launch carrying only the remember cookie.
    $this->app['auth']->forgetGuards();
    $recaller = rememberedLogin($user);

    afterBrowserRestart($recaller)
        ->get('/?source=pwa')
        ->assertRedirect(route('dashboard'));
});

it('still shows the login form at / to a guest', function () {
    $this->get('/')->assertOk()->assertSee('name="remember"', false);
});

it('does not ping-pong / and /dashboard for a user with no role', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user)->get('/')->assertOk()->assertSee('name="remember"', false);
});

it('drops the remember cookie on logout so the next launch asks again', function () {
    $user = rememberMeAdmin();
    $recaller = rememberedLogin($user);

    afterBrowserRestart($recaller)->post('/logout')->assertCookieExpired($recaller[0]);

    // The token is cycled server-side too, so a copy of the cookie taken off
    // a stolen device stops working the moment its owner signs out.
    expect($user->fresh()->remember_token)->not->toBe(explode('|', $recaller[1])[1]);
});
