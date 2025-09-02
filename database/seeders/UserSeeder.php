<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Package;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // Ensure packages exist
        Package::firstOrCreate(['id' => 1], ['name' => 'Package-1', 'price' => 20, 'level_unlock' => 1]);
        Package::firstOrCreate(['id' => 2], ['name' => 'Package-2', 'price' => 40, 'level_unlock' => 2]);
        Package::firstOrCreate(['id' => 3], ['name' => 'Package-3', 'price' => 60, 'level_unlock' => 3]);

        // Create root admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'sponsor_id' => null,
                'package_id' => 1,
            ]
        );
        $admin->assignRole('admin');

        // Create root customer
        $root = User::firstOrCreate(
            ['email' => 'root@example.com'],
            [
                'name' => 'Root User',
                'password' => Hash::make('password'),
                'sponsor_id' => null,
                'package_id' => 3,
            ]
        );
        $root->assignRole('customer');

        // Generate MLM tree (~50 users)
        $created = 0;
        $this->generateChildren($root, 1, 5, 50, $created);
    }

    private function generateChildren(User $sponsor, int $currentLevel, int $maxLevel, int $maxUsers, int &$created)
    {
        if ($currentLevel > $maxLevel || $created >= $maxUsers) {
            return;
        }

        for ($i = 1; $i <= 4; $i++) {
            if ($created >= $maxUsers) break;

            $created++;

            $user = User::create([
                'name'       => "User L{$currentLevel}-{$sponsor->id}-{$i}",
                'email'      => "user{$currentLevel}{$sponsor->id}{$i}@example.com",
                'password'   => Hash::make('password'),
                'sponsor_id' => $sponsor->id,
                'package_id' => rand(1, 3),
            ]);
            $user->assignRole('customer');

            // Trigger incomes
            app(\App\Http\Controllers\IncomeController::class)->distribute($user);

            $this->generateChildren($user, $currentLevel + 1, $maxLevel, $maxUsers, $created);
        }
    }
}
