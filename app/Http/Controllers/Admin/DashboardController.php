<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Get comprehensive dashboard data for admin
     */
    public function index()
    {
        try {
            $dashboardData = $this->dashboardService->getDashboardData();

            return $this->successResponse(
                $dashboardData,
                'Dashboard data fetched successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch dashboard data: ' . $e->getMessage(), 500);
        }
    }
}
