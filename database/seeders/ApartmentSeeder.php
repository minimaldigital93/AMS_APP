<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Floors;
use App\Models\Apartments;

class ApartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $floorsData = [
            'Ground Floor' => 80,
            'First Floor' => 70,
            'Second Floor' => 60,
            'Third Floor' => 50,
        ];

        foreach ($floorsData as $floorName => $monthlyRent) {
            // First, create or get the floor
            $floor = Floors::firstOrCreate([
                'floor_name' => $floorName,
            ], [
                'description' => $floorName . ' of the building',
            ]);

            // Then create 8 apartments for each floor
            for ($i = 1; $i <= 8; $i++) {
                $roomNumber = str_pad($i, 2, '0', STR_PAD_LEFT); // 01-08
                $apartmentNumber = $floorName . ' - ' . $roomNumber;
                
                Apartments::firstOrCreate([
                    'floor_id' => $floor->id,
                    'apartment_number' => $apartmentNumber,
                ], [
                    'monthly_rent' => $monthlyRent,
                    'status' => 'available',
                    'supervisor_id' => null,
                ]);
            }
        }
    }
}
