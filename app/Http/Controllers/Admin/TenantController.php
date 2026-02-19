<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use Illuminate\View\View;

class TenantController extends Controller
{
    /**
     * Display active tenants
     */
    public function index(): View
    {
        $tenants = Tenants::whereIn('status', ['active', 'pending'])
            ->with(['apartment'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('admin.TenantManagement.activeTenants', compact('tenants'));
    }

    /**
     * Display archived tenants
     */
    public function archived(): View
    {
        return view('admin.TenantManagement.archivedTenants');
    }

    /**
     * Display leave processing
     */
    public function leave($tenant): View
    {
        return view('admin.TenantManagement.leaveProcessing');
    }
}
