<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Floors;

class FloorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $floors = [
            ['floor_name' => 'Ground Floor', 'description' => 'Ground Floor of the building'],
            ['floor_name' => 'First Floor', 'description' => 'First Floor of the building'],
            ['floor_name' => 'Second Floor', 'description' => 'Second Floor of the building'],
            ['floor_name' => 'Third Floor', 'description' => 'Third Floor of the building'],
        ];

        foreach ($floors as $floor) {
            Floors::firstOrCreate(
                ['floor_name' => $floor['floor_name']],
                ['description' => $floor['description']]
            );
        }
    }
}
