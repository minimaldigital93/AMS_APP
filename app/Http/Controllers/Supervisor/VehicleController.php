<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Shared\VehicleController as SharedVehicleController;

class VehicleController extends SharedVehicleController
{
    protected function panel(): string
    {
        return 'supervisor';
    }
}
