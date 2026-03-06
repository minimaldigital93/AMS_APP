<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Models\Settings;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Priority: session > DB setting > config default
        $locale = session('locale');

        if (!$locale) {
            $locale = Settings::get('app_locale', config('app.locale'));
        }

        if (in_array($locale, ['en', 'km'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
