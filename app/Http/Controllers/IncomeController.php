<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Income;

class IncomeController extends Controller {
    public function distribute(User $newUser) {
        $packageValue = $newUser->package->price;
        $sponsor = $newUser->sponsor;

        // Direct Income (only first 4 directs)
        if ($sponsor && $sponsor->package_id) {
            $directsCount = $sponsor->directs()->count();
            $percent = match($directsCount) {
                1 => 6, 2 => 9, 3 => 12, 4 => 15, default => 0
            };
            if ($percent > 0) {
                Income::create([
                    'user_id' => $sponsor->id,
                    'type' => 'direct',
                    'amount' => $packageValue * ($percent/100),
                    'remark' => "Direct from {$newUser->name}"
                ]);
            }
        }

        // Level Income (up to 10)
        $upline = $sponsor;
        $level = 2;
        $levelPercents = [2,3,4,5,6,7,8,9,10]; // configurable
        while ($upline && $level <= 10) {
            if ($upline->package && $upline->package->level_unlock >= $level) {
                Income::create([
                    'user_id'=>$upline->id,
                    'type'=>'level',
                    'amount'=>$packageValue * ($levelPercents[$level-2]/100),
                    'remark'=>"Level $level from {$newUser->name}"
                ]);
            }
            $upline = $upline->sponsor;
            $level++;
        }

        // Club Income (flat $0.5 for all uplines with Package-1+)
        $upline = $sponsor;
        while ($upline) {
            if ($upline->package_id) {
                Income::create([
                    'user_id'=>$upline->id,
                    'type'=>'club',
                    'amount'=>0.5,
                    'remark'=>"Club from {$newUser->name}"
                ]);
            }
            $upline = $upline->sponsor;
        }
    }
}
