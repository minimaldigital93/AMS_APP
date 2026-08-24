<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Shared\VehicleController as SharedVehicleController;

class VehicleController extends SharedVehicleController
{
    protected function panel(): string
    {
        return 'admin';
    }
}
