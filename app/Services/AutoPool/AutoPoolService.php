<?php

namespace App\Services\AutoPool;

use App\Models\User;
use App\Models\AutoPoolLevel;
use App\Models\GroupCompletion;
use App\Models\AutoPoolBonus;
use App\Models\Income;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

class AutoPoolService
{
    protected $networkAnalysisService;
    protected $walletService;

    public function __construct(NetworkAnalysisService $networkAnalysisService, WalletService $walletService)
    {
        $this->networkAnalysisService = $networkAnalysisService;
        $this->walletService = $walletService;
    }

    /**
     * Process Auto Pool completions for a user
     */
    public function processAutoPoolCompletions(User $user): array
    {
        $results = [];
        $analysis = $this->networkAnalysisService->analyzeUserNetwork($user);

        foreach ($analysis['group_completions'] as $completion) {
            if ($completion['completed']) {
                $result = $this->processGroupCompletion($user, $completion);
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Process a specific group completion
     */
    public function processGroupCompletion(User $user, array $completion): array
    {
        $level = $completion['level'];
        $groupSize = $completion['group_size'];
        $bonusAmount = $completion['bonus_amount'];

        // Check if already completed
        $existingCompletion = GroupCompletion::where('user_id', $user->id)
            ->where('auto_pool_level', $level)
            ->first();

        if ($existingCompletion) {
            return [
                'success' => false,
                'message' => "Level {$level} already completed",
                'completion' => $existingCompletion
            ];
        }

        // Validate package requirement
        $autoPoolLevel = AutoPoolLevel::where('level', $level)->first();
        if (!$autoPoolLevel || !$autoPoolLevel->isEligibleForUser($user)) {
            return [
                'success' => false,
                'message' => "User not eligible for level {$level}",
                'completion' => null
            ];
        }

        try {
            DB::beginTransaction();

            // Create group completion record
            $groupCompletion = GroupCompletion::create([
                'user_id' => $user->id,
                'auto_pool_level' => $level,
                'group_size' => $groupSize,
                'directs_count' => $user->directs()->where('package_id', '>=', 1)->count(),
                'total_network_size' => $this->networkAnalysisService->analyzeUserNetwork($user)['total_network_size'],
                'bonus_amount' => $bonusAmount,
                'bonus_paid' => false,
                'completed_at' => now(),
                'completion_details' => [
                    'level_name' => $autoPoolLevel->name,
                    'required_directs' => $autoPoolLevel->required_directs,
                    'required_group_size' => $autoPoolLevel->required_group_size,
                    'actual_group_size' => $groupSize,
                    'completion_date' => now()->toISOString()
                ]
            ]);

            // Create Auto Pool bonus record
            $autoPoolBonus = AutoPoolBonus::create([
                'user_id' => $user->id,
                'group_completion_id' => $groupCompletion->id,
                'auto_pool_level' => $level,
                'amount' => $bonusAmount,
                'status' => AutoPoolBonus::STATUS_PENDING,
                'metadata' => [
                    'level_name' => $autoPoolLevel->name,
                    'group_size' => $groupSize,
                    'completion_id' => $groupCompletion->id
                ]
            ]);

            // Create income record
            $income = Income::create([
                'user_id' => $user->id,
                'type' => 'auto_pool',
                'amount' => $bonusAmount,
                'remark' => "Auto Pool {$autoPoolLevel->name} - Group Size: {$groupSize}"
            ]);

            // Credit to wallet
            $walletTransaction = $this->walletService->credit(
                $user->id,
                Wallet::TYPE_EARNING,
                $bonusAmount,
                WalletTransaction::CATEGORY_AUTO_POOL,
                "Auto Pool {$autoPoolLevel->name} bonus",
                [
                    'auto_pool_level' => $level,
                    'group_size' => $groupSize,
                    'completion_id' => $groupCompletion->id,
                    'bonus_id' => $autoPoolBonus->id,
                    'income_id' => $income->id
                ]
            );

            // Mark bonus as paid
            $autoPoolBonus->markAsPaid();
            $groupCompletion->markAsPaid();

            // Update user's Auto Pool stats
            $this->updateUserAutoPoolStats($user, $level, $bonusAmount);

            DB::commit();

            return [
                'success' => true,
                'message' => "Auto Pool level {$level} completed successfully",
                'completion' => $groupCompletion,
                'bonus' => $autoPoolBonus,
                'income' => $income,
                'wallet_transaction' => $walletTransaction
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => "Error processing Auto Pool completion: " . $e->getMessage(),
                'completion' => null
            ];
        }
    }

    /**
     * Update user's Auto Pool statistics
     */
    private function updateUserAutoPoolStats(User $user, int $level, float $bonusAmount): void
    {
        $user->update([
            'auto_pool_level' => max($user->auto_pool_level, $level),
            'group_completion_count' => $user->group_completion_count + 1,
            'last_group_completion_at' => now(),
            'total_auto_pool_earnings' => $user->total_auto_pool_earnings + $bonusAmount,
            'auto_pool_stats' => $this->calculateUserAutoPoolStats($user)
        ]);
    }

    /**
     * Calculate user's Auto Pool statistics
     */
    private function calculateUserAutoPoolStats(User $user): array
    {
        $completions = $user->groupCompletions()->get();
        $bonuses = $user->autoPoolBonuses()->get();

        return [
            'total_completions' => $completions->count(),
            'total_bonuses' => $bonuses->count(),
            'total_earnings' => $bonuses->sum('amount'),
            'highest_level' => $completions->max('auto_pool_level') ?? 0,
            'completion_levels' => $completions->pluck('auto_pool_level')->toArray(),
            'last_completion' => $user->last_group_completion_at?->toISOString(),
            'network_analysis' => $this->networkAnalysisService->analyzeUserNetwork($user)
        ];
    }

    /**
     * Get user's Auto Pool status
     */
    public function getUserAutoPoolStatus(User $user): array
    {
        $analysis = $this->networkAnalysisService->analyzeUserNetwork($user);
        $completions = $user->groupCompletions()->get();
        $bonuses = $user->autoPoolBonuses()->get();

        return [
            'user_id' => $user->id,
            'current_level' => $user->auto_pool_level,
            'total_completions' => $completions->count(),
            'total_earnings' => $bonuses->sum('amount'),
            'network_stats' => $analysis,
            'completions' => $completions->map(function ($completion) {
                return [
                    'level' => $completion->auto_pool_level,
                    'group_size' => $completion->group_size,
                    'bonus_amount' => $completion->bonus_amount,
                    'completed_at' => $completion->completed_at->toISOString(),
                    'bonus_paid' => $completion->bonus_paid
                ];
            }),
            'eligible_levels' => $analysis['eligible_levels'],
            'next_target' => $this->getNextTargetLevel($user)
        ];
    }

    /**
     * Get next target level for user
     */
    private function getNextTargetLevel(User $user): ?array
    {
        $autoPoolLevels = AutoPoolLevel::active()->ordered()->get();
        $completedLevels = $user->groupCompletions()->pluck('auto_pool_level')->toArray();

        foreach ($autoPoolLevels as $level) {
            if (!in_array($level->level, $completedLevels) && $level->isEligibleForUser($user)) {
                return [
                    'level' => $level->level,
                    'name' => $level->name,
                    'bonus_amount' => $level->bonus_amount,
                    'required_directs' => $level->required_directs,
                    'required_group_size' => $level->required_group_size,
                    'progress' => $this->calculateProgress($user, $level)
                ];
            }
        }

        return null;
    }

    /**
     * Calculate progress towards next level
     */
    private function calculateProgress(User $user, AutoPoolLevel $level): array
    {
        $analysis = $this->networkAnalysisService->analyzeUserNetwork($user);

        return [
            'current_directs' => $analysis['directs_count'],
            'required_directs' => $level->required_directs,
            'current_group_size' => $analysis['total_network_size'],
            'required_group_size' => $level->required_group_size,
            'directs_progress' => min(100, ($analysis['directs_count'] / $level->required_directs) * 100),
            'group_size_progress' => min(100, ($analysis['total_network_size'] / $level->required_group_size) * 100)
        ];
    }

    /**
     * Process Auto Pool completions for all users
     */
    public function processAllAutoPoolCompletions(): array
    {
        $results = [];
        $users = User::where('package_id', '>=', 1)->get();

        foreach ($users as $user) {
            $userResults = $this->processAutoPoolCompletions($user);
            if (!empty($userResults)) {
                $results[$user->id] = $userResults;
            }
        }

        return $results;
    }

    /**
     * Get Auto Pool statistics
     */
    public function getAutoPoolStatistics(): array
    {
        return [
            'total_completions' => GroupCompletion::count(),
            'total_bonuses_paid' => AutoPoolBonus::paid()->sum('amount'),
            'pending_bonuses' => AutoPoolBonus::pending()->sum('amount'),
            'completions_by_level' => GroupCompletion::selectRaw('auto_pool_level, COUNT(*) as count, SUM(bonus_amount) as total_amount')
                ->groupBy('auto_pool_level')
                ->get()
                ->keyBy('auto_pool_level'),
            'recent_completions' => GroupCompletion::with('user')
                ->orderBy('completed_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($completion) {
                    return [
                        'user_name' => $completion->user->name,
                        'level' => $completion->auto_pool_level,
                        'group_size' => $completion->group_size,
                        'bonus_amount' => $completion->bonus_amount,
                        'completed_at' => $completion->completed_at->toISOString()
                    ];
                })
        ];
    }
}
