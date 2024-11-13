<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard()
    {
        try {
            $activeUsersCount = User::where('status', 'active')->count();
            $totalTransactions = Wallet::count();
            $totalRevenue = Wallet::sum('amount');
            $weeklyRevenue = Wallet::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('amount');
            $monthlyRevenue = Wallet::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
            $yearlyRevenue = Wallet::whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])
                ->sum('amount');
            $response = [
                'activeUsersCount' => $activeUsersCount,
                'totalTransactions' => $totalTransactions,
                'totalRevenue' => $totalRevenue,
                'weeklyRevenue' => $weeklyRevenue,
                'monthlyRevenue' => $monthlyRevenue,
                'yearlyRevenue' => $yearlyRevenue,
            ];
            return $this->sendResponse($response, 'Dashboard data retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving dashboard data', ['error' => $e->getMessage()], 500);
        }
    }
}
