<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AutoPoolLevel;

class AutoPoolLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            [
                'level' => 4,
                'name' => '4-Star Club',
                'bonus_amount' => 0.50,
                'required_package_id' => 1,
                'required_directs' => 4,
                'required_group_size' => 4,
                'is_active' => true,
                'description' => 'Complete 4 directs with Package-1',
                'sort_order' => 1
            ],
            [
                'level' => 16,
                'name' => '16-Star Club',
                'bonus_amount' => 16.00,
                'required_package_id' => 2,
                'required_directs' => 4,
                'required_group_size' => 16,
                'is_active' => true,
                'description' => 'Complete 16 total group members (4 directs each with 4 directs)',
                'sort_order' => 2
            ],
            [
                'level' => 64,
                'name' => '64-Star Club',
                'bonus_amount' => 64.00,
                'required_package_id' => 3,
                'required_directs' => 4,
                'required_group_size' => 64,
                'is_active' => true,
                'description' => 'Complete 64 total group members (4 directs each with 4 directs each with 4 directs)',
                'sort_order' => 3
            ],
            [
                'level' => 256,
                'name' => '256-Star Club',
                'bonus_amount' => 256.00,
                'required_package_id' => 3,
                'required_directs' => 4,
                'required_group_size' => 256,
                'is_active' => true,
                'description' => 'Complete 256 total group members',
                'sort_order' => 4
            ],
            [
                'level' => 1024,
                'name' => '1024-Star Club',
                'bonus_amount' => 1024.00,
                'required_package_id' => 3,
                'required_directs' => 4,
                'required_group_size' => 1024,
                'is_active' => true,
                'description' => 'Complete 1024 total group members',
                'sort_order' => 5
            ]
        ];

        foreach ($levels as $level) {
            AutoPoolLevel::updateOrCreate(
                ['level' => $level['level']],
                $level
            );
        }
    }
}
