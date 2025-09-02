<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function profile()
    {
        $user = Auth::user()->load([
            'package',
            'directs.package',
            'incomes'
        ]);

        // Calculate total income
        $totalIncome = $user->incomes->sum('amount');

        // Format user data
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'referralCode' => $user->referral_code, // Using user ID as referral code
            'package' => $user->package ? [
                'id' => $user->package->id,
                'name' => $user->package->name,
                'price' => number_format($user->package->price, 2)
            ] : null
        ];

        // Format directs data
        $directsData = $user->directs->map(function ($direct) {
            return [
                'id' => $direct->id,
                'name' => $direct->name,
                'email' => $direct->email,
                'package' => $direct->package ? [
                    'id' => $direct->package->id,
                    'name' => $direct->package->name,
                    'price' => number_format($direct->package->price, 2),
                    'level_unlock' => $direct->package->level_unlock
                ] : null
            ];
        });

        // Format incomes data
        $incomesData = $user->incomes->map(function ($income) {
            return [
                'type' => $income->type,
                'amount' => number_format($income->amount, 2),
                'remark' => $income->remark,
                'date' => $income->created_at->format('Y-m-d H:i:s')
            ];
        });

        return $this->successResponse([
            'user' => $userData,
            'directs' => $directsData,
            'total_income' => number_format($totalIncome, 2),
            'incomes' => $incomesData
        ], 'User profile details fetched successfully');
    }

    public function incomeHistory()
    {
        $user = Auth::user();

        // Get all incomes for the user with pagination
        $incomes = $user->incomes()
            ->orderBy('created_at', 'desc')
            ->paginate(20); // 20 records per page

        // Calculate total income
        $totalIncome = $user->incomes->sum('amount');

        // Calculate income by type
        $incomeByType = $user->incomes->groupBy('type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_amount' => number_format($group->sum('amount'), 2)
            ];
        });

        // Format income data
        $formattedIncomes = $incomes->map(function ($income) {
            return [
                'id' => $income->id,
                'type' => $income->type,
                'amount' => number_format($income->amount, 2),
                'remark' => $income->remark,
                'date' => $income->created_at->format('Y-m-d H:i:s'),
                'formatted_date' => $income->created_at->format('M d, Y'),
                'time' => $income->created_at->format('h:i A')
            ];
        });

        return $this->successResponse([
            'incomes' => $formattedIncomes,
            'pagination' => [
                'current_page' => $incomes->currentPage(),
                'last_page' => $incomes->lastPage(),
                'per_page' => $incomes->perPage(),
                'total' => $incomes->total(),
                'has_more_pages' => $incomes->hasMorePages()
            ],
            'summary' => [
                'total_income' => number_format($totalIncome, 2),
                'total_transactions' => $user->incomes->count(),
                'income_by_type' => $incomeByType
            ]
        ], 'Income history fetched successfully');
    }

    public function tree()
    {
        $user = Auth::user();

        $tree = $this->buildTree($user, 1, 10); // limit depth = 10 levels

        return $this->successResponse($tree, 'User tree fetched successfully');
    }

    private function buildTree(User $user, int $currentLevel, int $maxLevel)
    {
        if ($currentLevel > $maxLevel) {
            return [];
        }

        // Only take max 4 directs (4x4 matrix rule)
        $children = $user->directs()->take(4)->get();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'package' => $user->package ? $user->package->name : null,
            'level' => $currentLevel,
            'children' => $children->map(function ($child) use ($currentLevel, $maxLevel) {
                return $this->buildTree($child, $currentLevel + 1, $maxLevel);
            })
        ];
    }
}
