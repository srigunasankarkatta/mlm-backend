<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\AutoPool\AutoPoolService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerAutoPoolController extends Controller
{
    use ApiResponseTrait;

    protected $autoPoolService;

    public function __construct(AutoPoolService $autoPoolService)
    {
        $this->autoPoolService = $autoPoolService;
    }

    /**
     * Get user's Auto Pool status
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $status = $this->autoPoolService->getUserAutoPoolStatus($user);

            return $this->successResponse($status, 'Auto Pool status retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's Auto Pool completions
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompletions()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $completions = $user->groupCompletions()
                ->with('autoPoolLevel')
                ->orderBy('completed_at', 'desc')
                ->get()
                ->map(function ($completion) {
                    return [
                        'id' => $completion->id,
                        'level' => $completion->auto_pool_level,
                        'level_name' => $completion->autoPoolLevel?->name ?? "Level {$completion->auto_pool_level}",
                        'group_size' => $completion->group_size,
                        'bonus_amount' => number_format($completion->bonus_amount, 2),
                        'bonus_paid' => $completion->bonus_paid,
                        'completed_at' => $completion->completed_at->format('Y-m-d H:i:s'),
                        'formatted_date' => $completion->completed_at->format('M d, Y'),
                        'completion_details' => $completion->completion_details
                    ];
                });

            return $this->successResponse([
                'completions' => $completions,
                'total_completions' => $completions->count(),
                'total_earnings' => number_format($user->total_auto_pool_earnings, 2)
            ], 'Auto Pool completions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool completions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's Auto Pool bonuses
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBonuses()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $bonuses = $user->autoPoolBonuses()
                ->with('groupCompletion')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($bonus) {
                    return [
                        'id' => $bonus->id,
                        'level' => $bonus->auto_pool_level,
                        'amount' => number_format($bonus->amount, 2),
                        'status' => $bonus->status,
                        'status_display' => $bonus->getStatusDisplayName(),
                        'paid_at' => $bonus->getPaidDate(),
                        'formatted_paid_date' => $bonus->getFormattedPaidDate(),
                        'payment_reference' => $bonus->payment_reference,
                        'notes' => $bonus->notes,
                        'created_at' => $bonus->created_at->format('Y-m-d H:i:s'),
                        'formatted_date' => $bonus->created_at->format('M d, Y')
                    ];
                });

            return $this->successResponse([
                'bonuses' => $bonuses,
                'total_bonuses' => $bonuses->count(),
                'total_paid' => number_format($bonuses->where('status', 'paid')->sum('amount'), 2),
                'total_pending' => number_format($bonuses->where('status', 'pending')->sum('amount'), 2)
            ], 'Auto Pool bonuses retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool bonuses: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Auto Pool levels and requirements
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLevels()
    {
        try {
            $levels = \App\Models\AutoPoolLevel::active()
                ->ordered()
                ->get()
                ->map(function ($level) {
                    return [
                        'level' => $level->level,
                        'name' => $level->name,
                        'bonus_amount' => number_format($level->bonus_amount, 2),
                        'required_package_id' => $level->required_package_id,
                        'required_directs' => $level->required_directs,
                        'required_group_size' => $level->required_group_size,
                        'description' => $level->description,
                        'display_name' => $level->getDisplayName()
                    ];
                });

            return $this->successResponse([
                'levels' => $levels,
                'total_levels' => $levels->count()
            ], 'Auto Pool levels retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool levels: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Auto Pool dashboard data
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboard()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $status = $this->autoPoolService->getUserAutoPoolStatus($user);

            $dashboard = [
                'current_level' => $user->auto_pool_level,
                'total_completions' => $user->group_completion_count,
                'total_earnings' => number_format($user->total_auto_pool_earnings, 2),
                'last_completion' => $user->last_group_completion_at?->format('M d, Y'),
                'network_stats' => [
                    'directs_count' => $status['network_stats']['directs_count'],
                    'total_network_size' => $status['network_stats']['total_network_size'],
                    'package_distribution' => $status['network_stats']['package_distribution']
                ],
                'next_target' => $status['next_target'],
                'recent_completions' => $user->groupCompletions()
                    ->orderBy('completed_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($completion) {
                        return [
                            'level' => $completion->auto_pool_level,
                            'group_size' => $completion->group_size,
                            'bonus_amount' => number_format($completion->bonus_amount, 2),
                            'completed_at' => $completion->completed_at->format('M d, Y')
                        ];
                    })
            ];

            return $this->successResponse($dashboard, 'Auto Pool dashboard data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process Auto Pool completions for current user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function processCompletions()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $results = $this->autoPoolService->processAutoPoolCompletions($user);

            return $this->successResponse([
                'processed' => count($results),
                'results' => $results
            ], 'Auto Pool completions processed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process Auto Pool completions: ' . $e->getMessage(), 500);
        }
    }
}
