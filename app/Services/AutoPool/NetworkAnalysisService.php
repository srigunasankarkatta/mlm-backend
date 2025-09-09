<?php

namespace App\Services\AutoPool;

use App\Models\User;
use App\Models\AutoPoolLevel;
use Illuminate\Support\Facades\DB;

class NetworkAnalysisService
{
    /**
     * Analyze user's network and detect group completions
     */
    public function analyzeUserNetwork(User $user): array
    {
        $analysis = [
            'user_id' => $user->id,
            'directs_count' => 0,
            'total_network_size' => 0,
            'group_completions' => [],
            'eligible_levels' => [],
            'network_stats' => []
        ];

        // Count directs with Package-1 or higher
        $directsWithPackage = $user->directs()
            ->where('package_id', '>=', 1)
            ->get();

        $analysis['directs_count'] = $directsWithPackage->count();
        $analysis['total_network_size'] = $this->calculateTotalNetworkSize($user);

        // Check for group completions
        $analysis['group_completions'] = $this->detectGroupCompletions($user, $directsWithPackage);

        // Check eligible Auto Pool levels
        $analysis['eligible_levels'] = $this->getEligibleLevels($user);

        // Calculate network statistics
        $analysis['network_stats'] = $this->calculateNetworkStats($user);

        return $analysis;
    }

    /**
     * Calculate total network size (recursive)
     */
    private function calculateTotalNetworkSize(User $user, int $depth = 0, int $maxDepth = 10): int
    {
        if ($depth >= $maxDepth) {
            return 0;
        }

        $totalSize = 0;
        $directs = $user->directs()->where('package_id', '>=', 1)->get();

        foreach ($directs as $direct) {
            $totalSize += 1; // Count the direct
            $totalSize += $this->calculateTotalNetworkSize($direct, $depth + 1, $maxDepth);
        }

        return $totalSize;
    }

    /**
     * Detect group completions for Auto Pool levels
     */
    private function detectGroupCompletions(User $user, $directsWithPackage): array
    {
        $completions = [];
        $directsCount = $directsWithPackage->count();

        // Check 4-Star Club completion
        if ($directsCount >= 4) {
            $completions[] = [
                'level' => 4,
                'group_size' => $directsCount,
                'completed' => true,
                'bonus_amount' => 0.5
            ];
        }

        // Check 16-Star Club completion
        if ($directsCount >= 4) {
            $totalGroupSize = $this->calculateGroupSize($user, 4, 2); // 4 directs, each with 4 directs
            if ($totalGroupSize >= 16) {
                $completions[] = [
                    'level' => 16,
                    'group_size' => $totalGroupSize,
                    'completed' => true,
                    'bonus_amount' => 16.0
                ];
            }
        }

        // Check 64-Star Club completion
        if ($directsCount >= 4) {
            $totalGroupSize = $this->calculateGroupSize($user, 4, 3); // 4 directs, each with 4 directs, each with 4 directs
            if ($totalGroupSize >= 64) {
                $completions[] = [
                    'level' => 64,
                    'group_size' => $totalGroupSize,
                    'completed' => true,
                    'bonus_amount' => 64.0
                ];
            }
        }

        return $completions;
    }

    /**
     * Calculate group size for specific depth
     */
    private function calculateGroupSize(User $user, int $requiredDirects, int $depth): int
    {
        if ($depth <= 0) {
            return 0;
        }

        $directs = $user->directs()
            ->where('package_id', '>=', 1)
            ->take($requiredDirects)
            ->get();

        if ($directs->count() < $requiredDirects) {
            return 0;
        }

        $totalSize = $directs->count();

        foreach ($directs as $direct) {
            $totalSize += $this->calculateGroupSize($direct, $requiredDirects, $depth - 1);
        }

        return $totalSize;
    }

    /**
     * Get eligible Auto Pool levels for user
     */
    private function getEligibleLevels(User $user): array
    {
        $eligibleLevels = [];
        $autoPoolLevels = AutoPoolLevel::active()->ordered()->get();

        foreach ($autoPoolLevels as $level) {
            if ($level->isEligibleForUser($user)) {
                $eligibleLevels[] = [
                    'level' => $level->level,
                    'name' => $level->name,
                    'bonus_amount' => $level->bonus_amount,
                    'required_package_id' => $level->required_package_id,
                    'required_directs' => $level->required_directs,
                    'required_group_size' => $level->required_group_size
                ];
            }
        }

        return $eligibleLevels;
    }

    /**
     * Calculate detailed network statistics
     */
    private function calculateNetworkStats(User $user): array
    {
        $stats = [
            'total_directs' => $user->directs()->count(),
            'directs_with_package' => $user->directs()->where('package_id', '>=', 1)->count(),
            'total_network_size' => $this->calculateTotalNetworkSize($user),
            'package_distribution' => [],
            'level_distribution' => []
        ];

        // Package distribution
        $packageStats = $user->directs()
            ->select('package_id', DB::raw('count(*) as count'))
            ->where('package_id', '>=', 1)
            ->groupBy('package_id')
            ->get();

        foreach ($packageStats as $stat) {
            $stats['package_distribution'][] = [
                'package_id' => $stat->package_id,
                'count' => $stat->count
            ];
        }

        // Level distribution (depth analysis)
        $stats['level_distribution'] = $this->calculateLevelDistribution($user);

        return $stats;
    }

    /**
     * Calculate level distribution in network
     */
    private function calculateLevelDistribution(User $user, int $maxDepth = 5): array
    {
        $distribution = [];

        for ($level = 1; $level <= $maxDepth; $level++) {
            $count = $this->countUsersAtLevel($user, $level);
            if ($count > 0) {
                $distribution[] = [
                    'level' => $level,
                    'count' => $count
                ];
            }
        }

        return $distribution;
    }

    /**
     * Count users at specific level
     */
    private function countUsersAtLevel(User $user, int $level): int
    {
        if ($level <= 0) {
            return 0;
        }

        if ($level === 1) {
            return $user->directs()->where('package_id', '>=', 1)->count();
        }

        $count = 0;
        $directs = $user->directs()->where('package_id', '>=', 1)->get();

        foreach ($directs as $direct) {
            $count += $this->countUsersAtLevel($direct, $level - 1);
        }

        return $count;
    }

    /**
     * Check if user has completed specific Auto Pool level
     */
    public function hasCompletedLevel(User $user, int $level): bool
    {
        $completions = $this->detectGroupCompletions($user, $user->directs()->where('package_id', '>=', 1)->get());

        foreach ($completions as $completion) {
            if ($completion['level'] === $level && $completion['completed']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get completion details for specific level
     */
    public function getCompletionDetails(User $user, int $level): ?array
    {
        $completions = $this->detectGroupCompletions($user, $user->directs()->where('package_id', '>=', 1)->get());

        foreach ($completions as $completion) {
            if ($completion['level'] === $level) {
                return $completion;
            }
        }

        return null;
    }
}
