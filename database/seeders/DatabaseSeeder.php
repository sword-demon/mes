<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemSeeder::class,
            OrganizationSeeder::class,
            // Product and production depend on materials, so material data runs first.
            MaterialSeeder::class,
            ProductSeeder::class,
            ProcessSeeder::class,
            ProductionSeeder::class,
            QualitySeeder::class,
            EquipmentSeeder::class,
            TraceabilitySeeder::class,
        ]);

        (new DemoDataConsistencyChecker)->assertConsistent();
    }
}
