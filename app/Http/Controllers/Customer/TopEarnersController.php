<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class TopEarnersController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get top earning customers
     */
    public function getTopEarners(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $period = $request->get('period', 'all'); // all, monthly, weekly, daily

            $query = User::with(['package', 'earningWallet'])
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'customer');
                })
                ->whereNotNull('package_id');

            // Apply period filter
            if ($period !== 'all') {
                $dateFilter = $this->getDateFilter($period);
                $query->whereHas('earningWallet.transactions', function ($q) use ($dateFilter) {
                    $q->where('created_at', '>=', $dateFilter)
                        ->where('type', 'credit')
                        ->whereIn('category', ['direct_income', 'level_income', 'club_income', 'auto_pool', 'bonus']);
                });
            }

            // Get users with their total earnings
            $topEarners = $query->get()->map(function ($user) use ($period) {
                $totalEarnings = $this->calculateUserEarnings($user, $period);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'package' => [
                        'id' => $user->package->id,
                        'name' => $user->package->name,
                        'level' => $user->package->level_unlock
                    ],
                    'total_earnings' => number_format($totalEarnings, 2),
                    'total_earnings_raw' => $totalEarnings,
                    'directs_count' => $user->directs()->count(),
                    'rank' => 0 // Will be set after sorting
                ];
            });

            // Sort by earnings and add rank
            $topEarners = $topEarners->sortByDesc('total_earnings_raw')->values();

            $rankedEarners = $topEarners->map(function ($earner, $index) {
                $earner['rank'] = $index + 1;
                return $earner;
            });

            // Apply limit
            $topEarners = $rankedEarners->take($limit);

            return $this->successResponse([
                'top_earners' => $topEarners,
                'period' => $period,
                'total_count' => $rankedEarners->count(),
                'showing' => $topEarners->count()
            ], 'Top earners fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch top earners: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's earnings for a specific period
     */
    private function calculateUserEarnings(User $user, string $period): float
    {
        $earningWallet = $user->earningWallet;

        if (!$earningWallet) {
            return 0.0;
        }

        $query = $earningWallet->transactions()
            ->where('type', 'credit')
            ->whereIn('category', ['direct_income', 'level_income', 'club_income', 'auto_pool', 'bonus']);

        if ($period !== 'all') {
            $dateFilter = $this->getDateFilter($period);
            $query->where('created_at', '>=', $dateFilter);
        }

        return $query->sum('amount');
    }

    /**
     * Get date filter for period
     */
    private function getDateFilter(string $period): string
    {
        switch ($period) {
            case 'daily':
                return now()->startOfDay()->toDateTimeString();
            case 'weekly':
                return now()->startOfWeek()->toDateTimeString();
            case 'monthly':
                return now()->startOfMonth()->toDateTimeString();
            default:
                return '1970-01-01 00:00:00';
        }
    }

    /**
     * Get leaderboard statistics
     */
    public function getLeaderboardStats(Request $request)
    {
        try {
            $totalCustomers = User::whereHas('roles', function ($q) {
                $q->where('name', 'customer');
            })->count();

            $totalEarnings = Wallet::where('type', 'earning')->sum('balance');

            $topEarner = User::with(['package', 'earningWallet'])
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'customer');
                })
                ->whereNotNull('package_id')
                ->get()
                ->map(function ($user) {
                    $totalEarnings = $this->calculateUserEarnings($user, 'all');
                    return [
                        'user' => $user,
                        'earnings' => $totalEarnings
                    ];
                })
                ->sortByDesc('earnings')
                ->first();

            return $this->successResponse([
                'total_customers' => $totalCustomers,
                'total_earnings' => number_format($totalEarnings, 2),
                'top_earner' => $topEarner ? [
                    'name' => $topEarner['user']->name,
                    'package' => $topEarner['user']->package->name,
                    'earnings' => number_format($topEarner['earnings'], 2)
                ] : null,
                'updated_at' => now()->format('Y-m-d H:i:s')
            ], 'Leaderboard statistics fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch leaderboard statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's rank in leaderboard
     */
    public function getUserRank(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            // Get all users with earnings
            $allUsers = User::with(['package', 'earningWallet'])
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'customer');
                })
                ->whereNotNull('package_id')
                ->get()
                ->map(function ($u) {
                    $totalEarnings = $this->calculateUserEarnings($u, 'all');
                    return [
                        'id' => $u->id,
                        'earnings' => $totalEarnings
                    ];
                })
                ->sortByDesc('earnings')
                ->values();

            // Find user's rank
            $userRank = $allUsers->search(function ($u) use ($user) {
                return $u['id'] === $user->id;
            });

            if ($userRank === false) {
                return $this->errorResponse('User not found in leaderboard', 404);
            }

            $userEarnings = $this->calculateUserEarnings($user, 'all');
            $totalUsers = $allUsers->count();

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'package' => $user->package ? $user->package->name : 'No Package',
                    'earnings' => number_format($userEarnings, 2),
                    'earnings_raw' => $userEarnings
                ],
                'rank' => $userRank + 1,
                'total_users' => $totalUsers,
                'percentile' => round((($totalUsers - $userRank) / $totalUsers) * 100, 2)
            ], 'User rank fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch user rank: ' . $e->getMessage(), 500);
        }
    }
}
