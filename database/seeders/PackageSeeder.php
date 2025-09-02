<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder {
    public function run(): void {
        Package::create(['name'=>'Package-1','price'=>20,'level_unlock'=>1]);
        Package::create(['name'=>'Package-2','price'=>40,'level_unlock'=>2]);
        Package::create(['name'=>'Package-3','price'=>60,'level_unlock'=>3]);
        Package::create(['name'=>'Package-4','price'=>80,'level_unlock'=>4]);
        // … continue till Package-10
    }
}
