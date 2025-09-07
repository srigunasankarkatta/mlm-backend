<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\Package;
use App\Models\Income;
use Carbon\Carbon;

class DashboardService
{
    public function getDashboardData(): array
    {
        try {
            return [
                'overview' => $this->getOverviewStats(),
                'user_analytics' => $this->getUserAnalytics(),
                'package_analytics' => $this->getPackageAnalytics(),
                'income_analytics' => $this->getIncomeAnalytics(),
                'recent_activities' => $this->getRecentActivities(),
                'mlm_tree_stats' => $this->getMlmTreeStats(),
                'growth_metrics' => $this->getGrowthMetrics()
            ];
        } catch (\Exception $e) {
            // Return basic data if there's an error
            return [
                'overview' => [
                    'total_users' => User::count(),
                    'total_packages' => Package::count(),
                    'total_income_distributed' => '0.00',
                    'active_users' => User::whereNotNull('package_id')->count(),
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    private function getOverviewStats(): array
    {
        $totalUsers = User::count();
        $totalPackages = Package::count();
        $totalIncome = Income::sum('amount');
        $activeUsers = User::whereNotNull('package_id')->count();

        // Today's stats
        $todayUsers = User::whereDate('created_at', Carbon::today())->count();
        $todayIncome = Income::whereDate('created_at', Carbon::today())->sum('amount');

        // This month's stats
        $monthUsers = User::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
        $monthIncome = Income::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');

        return [
            'total_users' => $totalUsers,
            'total_packages' => $totalPackages,
            'total_income_distributed' => number_format($totalIncome, 2),
            'active_users' => $activeUsers,
            'inactive_users' => $totalUsers - $activeUsers,
            'today' => [
                'new_users' => $todayUsers,
                'income_distributed' => number_format($todayIncome, 2)
            ],
            'this_month' => [
                'new_users' => $monthUsers,
                'income_distributed' => number_format($monthIncome, 2)
            ]
        ];
    }

    private function getUserAnalytics(): array
    {
        try {
            $usersByRole = User::selectRaw('COUNT(*) as count')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->groupBy('roles.name')
                ->pluck('count', 'roles.name')
                ->toArray();
        } catch (\Exception $e) {
            $usersByRole = [];
        }

        try {
            $usersByPackage = User::selectRaw('package_id, COUNT(*) as count')
                ->whereNotNull('package_id')
                ->groupBy('package_id')
                ->with('package:id,name')
                ->get()
                ->map(function ($item) {
                    return [
                        'package_id' => $item->package_id,
                        'package_name' => $item->package?->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                });
        } catch (\Exception $e) {
            $usersByPackage = [];
        }

        $usersWithoutPackage = User::whereNull('package_id')->count();

        // Registration trend (last 30 days)
        $registrationTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = User::whereDate('created_at', $date)->count();
            $registrationTrend[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count
            ];
        }

        return [
            'by_role' => $usersByRole,
            'by_package' => $usersByPackage,
            'without_package' => $usersWithoutPackage,
            'registration_trend' => $registrationTrend
        ];
    }

    private function getPackageAnalytics(): array
    {
        try {
            $packageUsage = Package::withCount('users')
                ->orderBy('users_count', 'desc')
                ->get()
                ->map(function ($package) {
                    return [
                        'id' => $package->id,
                        'name' => $package->name,
                        'price' => number_format($package->price, 2),
                        'level_unlock' => $package->level_unlock,
                        'users_count' => $package->users_count,
                        'revenue' => number_format($package->price * $package->users_count, 2)
                    ];
                });
        } catch (\Exception $e) {
            $packageUsage = [];
        }

        $totalRevenue = Package::withCount('users')->get()
            ->sum(function ($package) {
                return $package->price * $package->users_count;
            });

        return [
            'package_usage' => $packageUsage,
            'total_revenue' => number_format($totalRevenue, 2)
        ];
    }

    private function getIncomeAnalytics(): array
    {
        $incomeByType = Income::selectRaw('type, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'count' => $item->count,
                    'total' => number_format($item->total, 2)
                ];
            });

        // Income trend (last 30 days)
        $incomeTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $total = Income::whereDate('created_at', $date)->sum('amount');
            $incomeTrend[] = [
                'date' => $date->format('Y-m-d'),
                'amount' => number_format($total, 2)
            ];
        }

        // Top earners
        $topEarners = User::withSum('incomes', 'amount')
            ->orderBy('incomes_sum_amount', 'desc')
            ->take(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_income' => number_format($user->incomes_sum_amount ?? 0, 2),
                    'package' => $user->package?->name
                ];
            });

        return [
            'by_type' => $incomeByType,
            'trend' => $incomeTrend,
            'top_earners' => $topEarners
        ];
    }

    private function getRecentActivities(): array
    {
        $recentUsers = User::with(['package', 'sponsor'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'package' => $user->package?->name,
                    'sponsor' => $user->sponsor?->name,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s')
                ];
            });

        $recentIncomes = Income::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($income) {
                return [
                    'id' => $income->id,
                    'user_name' => $income->user->name,
                    'type' => $income->type,
                    'amount' => number_format($income->amount, 2),
                    'remark' => $income->remark,
                    'created_at' => $income->created_at->format('Y-m-d H:i:s')
                ];
            });

        return [
            'recent_users' => $recentUsers,
            'recent_incomes' => $recentIncomes
        ];
    }

    private function getMlmTreeStats(): array
    {
        // Get root users (users without sponsors)
        $rootUsers = User::whereNull('sponsor_id')->count();

        // Get users with directs
        $usersWithDirects = User::has('directs')->count();

        // Average directs per user
        $avgDirects = User::withCount('directs')->get()->avg('directs_count');

        // MLM tree depth analysis
        $treeDepth = $this->calculateTreeDepth();

        // Top performers by directs
        $topPerformers = User::withCount('directs')
            ->orderBy('directs_count', 'desc')
            ->take(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'directs_count' => $user->directs_count,
                    'package' => $user->package?->name ?? 'No Package'
                ];
            });

        return [
            'root_users' => $rootUsers,
            'users_with_directs' => $usersWithDirects,
            'average_directs' => number_format($avgDirects, 2),
            'tree_depth' => $treeDepth,
            'top_performers' => $topPerformers
        ];
    }

    private function getGrowthMetrics(): array
    {
        // User growth comparison (this month vs last month)
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;
        $lastMonth = Carbon::now()->subMonth()->month;
        $lastYear = Carbon::now()->subMonth()->year;

        $thisMonthUsers = User::whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->count();

        $lastMonthUsers = User::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastYear)
            ->count();

        $userGrowthRate = $lastMonthUsers > 0
            ? (($thisMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100
            : 0;

        // Income growth comparison
        $thisMonthIncome = Income::whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->sum('amount');

        $lastMonthIncome = Income::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastYear)
            ->sum('amount');

        $incomeGrowthRate = $lastMonthIncome > 0
            ? (($thisMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100
            : 0;

        return [
            'user_growth' => [
                'this_month' => $thisMonthUsers,
                'last_month' => $lastMonthUsers,
                'growth_rate' => number_format($userGrowthRate, 2) . '%'
            ],
            'income_growth' => [
                'this_month' => number_format($thisMonthIncome, 2),
                'last_month' => number_format($lastMonthIncome, 2),
                'growth_rate' => number_format($incomeGrowthRate, 2) . '%'
            ]
        ];
    }

    private function calculateTreeDepth(): array
    {
        $depths = [];

        // Get all root users and calculate their tree depth
        $rootUsers = User::whereNull('sponsor_id')->get();

        foreach ($rootUsers as $rootUser) {
            $depth = $this->getMaxDepth($rootUser, 1);
            $depths[] = $depth;
        }

        return [
            'max_depth' => !empty($depths) ? max($depths) : 0,
            'average_depth' => !empty($depths) ? number_format(array_sum($depths) / count($depths), 2) : 0
        ];
    }

    private function getMaxDepth(User $user, int $currentDepth): int
    {
        $children = $user->directs;

        if ($children->isEmpty()) {
            return $currentDepth;
        }

        $maxChildDepth = $currentDepth;
        foreach ($children as $child) {
            $childDepth = $this->getMaxDepth($child, $currentDepth + 1);
            $maxChildDepth = max($maxChildDepth, $childDepth);
        }

        return $maxChildDepth;
    }
}
