<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the tenant's settings page: language toggle and logout.
     */
    public function edit(): View
    {
        return view('tenant.settings');
    }
}
