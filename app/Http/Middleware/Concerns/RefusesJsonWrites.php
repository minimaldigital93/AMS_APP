<?php

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Say no in a status the caller can act on.
 *
 * The write gates on the revenue-expense groups refuse by REDIRECTING — to the
 * fiscal-period form, to the month's close page — which is the right answer for
 * a browser navigation and the wrong one for the Add-Charge modal. That modal
 * posts with `fetch()`, whose default `redirect: 'follow'` fetches the landing
 * page and hands the caller a 200: `res.ok` is true, the page reloads, and the
 * charge the operator entered is simply not on the bill. The flash is consumed
 * by the fetch, so nothing on screen ever says why.
 *
 * An XHR asking for JSON gets the same sentence as a status it can read.
 */
trait RefusesJsonWrites
{
    protected function jsonRefusal(Request $request, string $message): ?JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : null;
    }
}
