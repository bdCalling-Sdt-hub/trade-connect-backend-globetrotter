<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        try {
            $period = $request->query('period', 'weekly'); // Default to 'weekly' if not specified
            // Initialize date ranges based on period
            switch ($period) {
                case 'monthly':
                    $startDate = now()->startOfMonth();
                    $endDate = now()->endOfMonth();
                    break;
                case 'yearly':
                    $startDate = now()->startOfYear();
                    $endDate = now()->endOfYear();
                    break;
                case 'weekly':
                default:
                    $startDate = now()->startOfWeek();
                    $endDate = now()->endOfWeek();
                    break;
            }
            $activeUsersCount = User::where('status', 'active')->count();
            $totalTransactions = Wallet::count();
            $totalRevenue = Wallet::sum('amount');
            // Period-specific metrics
            $filteredUsersCount = User::where('status', 'active')->whereBetween('created_at', [$startDate, $endDate])->count();
            $filteredRevenue = Wallet::whereBetween('created_at', [$startDate, $endDate])->sum('amount');
            $filteredTransactions = Wallet::whereBetween('created_at', [$startDate, $endDate])->count();
            // Full period-based revenue data
            $weeklyRevenue = Wallet::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount');
            $monthlyRevenue = Wallet::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
            $yearlyRevenue = Wallet::whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])->sum('amount');
           
            $response = [
                'users' => [
                    'activeUsersCount' => $activeUsersCount,
                    'filteredUsersCount' => $filteredUsersCount,
                ],
                'revenue' => [
                    'totalRevenue' => $totalRevenue,
                    'filteredRevenue' => $filteredRevenue,
                    'weeklyRevenue' => $weeklyRevenue,
                    'monthlyRevenue' => $monthlyRevenue,
                    'yearlyRevenue' => $yearlyRevenue,
                ],
                'transactions' => [
                    'totalTransactions' => $totalTransactions,
                    'filteredTransactions' => $filteredTransactions,
                ],
                'activities' => [
                    $period => [
                        'userRegistrations' => $filteredUsersCount,
                        'transactions' => $filteredTransactions,
                        'revenue' => $filteredRevenue,
                    ],
                ]
            ];
            return $this->sendResponse($response, 'Dashboard data retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving dashboard data', ['error' => $e->getMessage()], 500);
        }
    }
}
