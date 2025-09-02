<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UpdateReferralCodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::all()->each(function ($user) {
            if (!$user->referral_code) {
                $user->referral_code = 'REF' . strtoupper(substr(md5($user->id . time()), 0, 6));
                $user->save();
            }
        });

        $this->command->info('Updated referral codes for all users');

        // Show some examples
        $users = User::take(3)->get();
        foreach ($users as $user) {
            $this->command->info("User: {$user->email} - Referral Code: {$user->referral_code}");
        }
    }
}
