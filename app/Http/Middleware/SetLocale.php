<?php

namespace App\Http\Middleware;

use App\Models\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Priority: session > DB setting > config default
        $locale = session('locale');

        if (! $locale) {
            $locale = Settings::get('app_locale', config('app.locale'));
        }

        if (in_array($locale, ['en', 'km'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
