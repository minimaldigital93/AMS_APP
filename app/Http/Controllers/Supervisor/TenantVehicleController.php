<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Shared\TenantVehicleController as SharedTenantVehicleController;

class TenantVehicleController extends SharedTenantVehicleController
{
    protected function panel(): string
    {
        return 'supervisor';
    }
}
