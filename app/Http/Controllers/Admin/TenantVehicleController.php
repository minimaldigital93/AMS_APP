<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Shared\TenantVehicleController as SharedTenantVehicleController;

class TenantVehicleController extends SharedTenantVehicleController
{
    protected function panel(): string
    {
        return 'admin';
    }
}
