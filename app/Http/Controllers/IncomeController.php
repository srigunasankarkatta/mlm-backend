<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Income;
use App\Services\WalletService;

class IncomeController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function distribute(User $newUser)
    {
        $packageValue = $newUser->package->price;
        $sponsor = $newUser->sponsor;

        // Direct Income (only first 4 directs)
        if ($sponsor && $sponsor->package_id) {
            $directsCount = $sponsor->directs()->count();
            $percent = match ($directsCount) {
                1 => 6,
                2 => 9,
                3 => 12,
                4 => 15,
                default => 0
            };
            if ($percent > 0) {
                $amount = $packageValue * ($percent / 100);
                $remark = "Direct from {$newUser->name}";

                // Create income record (existing system)
                Income::create([
                    'user_id' => $sponsor->id,
                    'type' => 'direct',
                    'amount' => $amount,
                    'remark' => $remark
                ]);

                // Credit to wallet (new system)
                $this->walletService->credit(
                    $sponsor->id,
                    \App\Models\Wallet::TYPE_EARNING,
                    $amount,
                    \App\Models\WalletTransaction::CATEGORY_DIRECT_INCOME,
                    $remark,
                    [
                        'from_user_id' => $newUser->id,
                        'from_user_name' => $newUser->name,
                        'package_value' => $packageValue,
                        'percentage' => $percent,
                        'direct_count' => $directsCount
                    ]
                );
            }
        }

        // Level Income (up to 10)
        $upline = $sponsor;
        $level = 2;
        $levelPercents = [2, 3, 4, 5, 6, 7, 8, 9, 10]; // configurable
        while ($upline && $level <= 10) {
            if ($upline->package && $upline->package->level_unlock >= $level) {
                $amount = $packageValue * ($levelPercents[$level - 2] / 100);
                $remark = "Level $level from {$newUser->name}";

                // Create income record (existing system)
                Income::create([
                    'user_id' => $upline->id,
                    'type' => 'level',
                    'amount' => $amount,
                    'remark' => $remark
                ]);

                // Credit to wallet (new system)
                $this->walletService->credit(
                    $upline->id,
                    \App\Models\Wallet::TYPE_EARNING,
                    $amount,
                    \App\Models\WalletTransaction::CATEGORY_LEVEL_INCOME,
                    $remark,
                    [
                        'from_user_id' => $newUser->id,
                        'from_user_name' => $newUser->name,
                        'package_value' => $packageValue,
                        'level' => $level,
                        'percentage' => $levelPercents[$level - 2]
                    ]
                );
            }
            $upline = $upline->sponsor;
            $level++;
        }

        // Club Income (flat $0.5 for all uplines with Package-1+)
        $upline = $sponsor;
        while ($upline) {
            if ($upline->package_id) {
                $amount = 0.5;
                $remark = "Club from {$newUser->name}";

                // Create income record (existing system)
                Income::create([
                    'user_id' => $upline->id,
                    'type' => 'club',
                    'amount' => $amount,
                    'remark' => $remark
                ]);

                // Credit to wallet (new system)
                $this->walletService->credit(
                    $upline->id,
                    \App\Models\Wallet::TYPE_EARNING,
                    $amount,
                    \App\Models\WalletTransaction::CATEGORY_CLUB_INCOME,
                    $remark,
                    [
                        'from_user_id' => $newUser->id,
                        'from_user_name' => $newUser->name,
                        'package_value' => $packageValue,
                        'club_amount' => $amount
                    ]
                );
            }
            $upline = $upline->sponsor;
        }
    }
}
