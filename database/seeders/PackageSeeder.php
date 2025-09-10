<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'Package-1', 'price' => 20, 'level_unlock' => 1, 'direct_income_rate' => 6.0, 'level_income_rate' => 3.0, 'club_income_rate' => 2.5],
            ['name' => 'Package-2', 'price' => 40, 'level_unlock' => 2, 'direct_income_rate' => 7.0, 'level_income_rate' => 4.0, 'club_income_rate' => 3.0],
            ['name' => 'Package-3', 'price' => 60, 'level_unlock' => 3, 'direct_income_rate' => 8.0, 'level_income_rate' => 5.0, 'club_income_rate' => 3.5],
            ['name' => 'Package-4', 'price' => 80, 'level_unlock' => 4, 'direct_income_rate' => 9.0, 'level_income_rate' => 6.0, 'club_income_rate' => 4.0],
            ['name' => 'Package-5', 'price' => 100, 'level_unlock' => 5, 'direct_income_rate' => 10.0, 'level_income_rate' => 7.0, 'club_income_rate' => 4.5],
            ['name' => 'Package-6', 'price' => 150, 'level_unlock' => 6, 'direct_income_rate' => 11.0, 'level_income_rate' => 8.0, 'club_income_rate' => 5.0],
            ['name' => 'Package-7', 'price' => 200, 'level_unlock' => 7, 'direct_income_rate' => 12.0, 'level_income_rate' => 9.0, 'club_income_rate' => 5.5],
            ['name' => 'Package-8', 'price' => 300, 'level_unlock' => 8, 'direct_income_rate' => 13.0, 'level_income_rate' => 10.0, 'club_income_rate' => 6.0],
            ['name' => 'Package-9', 'price' => 500, 'level_unlock' => 9, 'direct_income_rate' => 14.0, 'level_income_rate' => 11.0, 'club_income_rate' => 6.5],
            ['name' => 'Package-10', 'price' => 1000, 'level_unlock' => 10, 'direct_income_rate' => 15.0, 'level_income_rate' => 12.0, 'club_income_rate' => 7.0],
        ];

        foreach ($packages as $packageData) {
            Package::create($packageData);
        }
    }
}
