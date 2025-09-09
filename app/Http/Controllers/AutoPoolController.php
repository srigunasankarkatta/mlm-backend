<?php

namespace App\Http\Controllers;

use App\Services\AutoPool\AutoPoolService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutoPoolController extends Controller
{
    use ApiResponseTrait;

    protected $autoPoolService;

    public function __construct(AutoPoolService $autoPoolService)
    {
        $this->autoPoolService = $autoPoolService;
    }

    /**
     * Get Auto Pool statistics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics()
    {
        try {
            $stats = $this->autoPoolService->getAutoPoolStatistics();

            return $this->successResponse($stats, 'Auto Pool statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Auto Pool completions
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompletions(Request $request)
    {
        try {
            $query = \App\Models\GroupCompletion::with(['user', 'autoPoolLevel']);

            // Apply filters
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('level')) {
                $query->where('auto_pool_level', $request->level);
            }

            if ($request->has('bonus_paid')) {
                $query->where('bonus_paid', $request->bonus_paid);
            }

            if ($request->has('from_date')) {
                $query->whereDate('completed_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('completed_at', '<=', $request->to_date);
            }

            $perPage = $request->get('per_page', 15);
            $completions = $query->orderBy('completed_at', 'desc')->paginate($perPage);

            $formattedCompletions = $completions->getCollection()->map(function ($completion) {
                return [
                    'id' => $completion->id,
                    'user_id' => $completion->user_id,
                    'user_name' => $completion->user->name,
                    'user_email' => $completion->user->email,
                    'level' => $completion->auto_pool_level,
                    'level_name' => $completion->autoPoolLevel?->name ?? "Level {$completion->auto_pool_level}",
                    'group_size' => $completion->group_size,
                    'directs_count' => $completion->directs_count,
                    'total_network_size' => $completion->total_network_size,
                    'bonus_amount' => number_format($completion->bonus_amount, 2),
                    'bonus_paid' => $completion->bonus_paid,
                    'completed_at' => $completion->completed_at->format('Y-m-d H:i:s'),
                    'formatted_date' => $completion->completed_at->format('M d, Y'),
                    'completion_details' => $completion->completion_details
                ];
            });

            return $this->successResponse([
                'completions' => $formattedCompletions,
                'pagination' => [
                    'current_page' => $completions->currentPage(),
                    'last_page' => $completions->lastPage(),
                    'per_page' => $completions->perPage(),
                    'total' => $completions->total(),
                    'from' => $completions->firstItem(),
                    'to' => $completions->lastItem(),
                    'has_more_pages' => $completions->hasMorePages()
                ]
            ], 'Auto Pool completions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool completions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Auto Pool bonuses
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBonuses(Request $request)
    {
        try {
            $query = \App\Models\AutoPoolBonus::with(['user', 'groupCompletion', 'autoPoolLevel']);

            // Apply filters
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('level')) {
                $query->where('auto_pool_level', $request->level);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $perPage = $request->get('per_page', 15);
            $bonuses = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $formattedBonuses = $bonuses->getCollection()->map(function ($bonus) {
                return [
                    'id' => $bonus->id,
                    'user_id' => $bonus->user_id,
                    'user_name' => $bonus->user->name,
                    'user_email' => $bonus->user->email,
                    'level' => $bonus->auto_pool_level,
                    'level_name' => $bonus->autoPoolLevel?->name ?? "Level {$bonus->auto_pool_level}",
                    'amount' => number_format($bonus->amount, 2),
                    'status' => $bonus->status,
                    'status_display' => $bonus->getStatusDisplayName(),
                    'paid_at' => $bonus->getPaidDate(),
                    'formatted_paid_date' => $bonus->getFormattedPaidDate(),
                    'payment_reference' => $bonus->payment_reference,
                    'notes' => $bonus->notes,
                    'created_at' => $bonus->created_at->format('Y-m-d H:i:s'),
                    'formatted_date' => $bonus->created_at->format('M d, Y'),
                    'metadata' => $bonus->metadata
                ];
            });

            return $this->successResponse([
                'bonuses' => $formattedBonuses,
                'pagination' => [
                    'current_page' => $bonuses->currentPage(),
                    'last_page' => $bonuses->lastPage(),
                    'per_page' => $bonuses->perPage(),
                    'total' => $bonuses->total(),
                    'from' => $bonuses->firstItem(),
                    'to' => $bonuses->lastItem(),
                    'has_more_pages' => $bonuses->hasMorePages()
                ]
            ], 'Auto Pool bonuses retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Auto Pool bonuses: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Auto Pool levels
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
                        'id' => $level->id,
                        'level' => $level->level,
                        'name' => $level->name,
                        'bonus_amount' => number_format($level->bonus_amount, 2),
                        'required_package_id' => $level->required_package_id,
                        'required_directs' => $level->required_directs,
                        'required_group_size' => $level->required_group_size,
                        'is_active' => $level->is_active,
                        'description' => $level->description,
                        'sort_order' => $level->sort_order,
                        'display_name' => $level->getDisplayName(),
                        'completions_count' => $level->groupCompletions()->count(),
                        'total_bonuses_paid' => number_format($level->autoPoolBonuses()->paid()->sum('amount'), 2)
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
     * Process Auto Pool completions for all users
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function processAllCompletions()
    {
        try {
            $results = $this->autoPoolService->processAllAutoPoolCompletions();

            return $this->successResponse([
                'processed_users' => count($results),
                'results' => $results
            ], 'Auto Pool completions processed for all users');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process Auto Pool completions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process Auto Pool completions for specific user
     * 
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function processUserCompletions($userId)
    {
        try {
            $user = \App\Models\User::findOrFail($userId);
            $results = $this->autoPoolService->processAutoPoolCompletions($user);

            return $this->successResponse([
                'user_id' => $userId,
                'user_name' => $user->name,
                'processed' => count($results),
                'results' => $results
            ], 'Auto Pool completions processed for user');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process Auto Pool completions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's Auto Pool status
     * 
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserStatus($userId)
    {
        try {
            $user = \App\Models\User::findOrFail($userId);
            $status = $this->autoPoolService->getUserAutoPoolStatus($user);

            return $this->successResponse($status, 'User Auto Pool status retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user Auto Pool status: ' . $e->getMessage(), 500);
        }
    }
}
