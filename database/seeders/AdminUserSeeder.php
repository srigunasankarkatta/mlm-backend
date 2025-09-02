<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@mlm.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'sponsor_id' => null,
                'package_id' => null,
            ]
        );

        // Assign admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && !$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Create test customer user
        $customer = User::firstOrCreate(
            ['email' => 'customer@mlm.com'],
            [
                'name' => 'Test Customer',
                'password' => Hash::make('customer123'),
                'sponsor_id' => null,
                'package_id' => 1, // Assuming package with ID 1 exists
            ]
        );

        // Assign customer role
        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole && !$customer->hasRole('customer')) {
            $customer->assignRole('customer');
        }

        $this->command->info('Admin and Customer users created successfully!');
        $this->command->info('Admin: admin@mlm.com / admin123');
        $this->command->info('Customer: customer@mlm.com / customer123');
    }
}
