<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        try {
            $activeUsersCount = User::where('status', 'active')->count();
            $totalTransactions = Wallet::count();
            $totalRevenue = Wallet::sum('amount');
            $yearlyRevenue = Wallet::whereYear('created_at', now()->year)->sum('amount');
            $totalProducts = Product::where('status', 'approved')->count();
            $pendingProducts = Product::where('status', 'pending')->count();
            $canceledProducts = Product::where('status', 'canceled')->count();
            $pendingOrders = Order::where('status', 'pending')->count();
            $monthlyRevenue = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyRevenue[] = [
                    'month' => now()->setMonth($month)->format('M'),
                    'revenue' => Wallet::whereMonth('created_at', $month)
                                        ->whereYear('created_at', now()->year)
                                        ->sum('amount') ?? 0,
                ];
            }
            $response = [
                'users' => [
                    'activeUsersCount' => $activeUsersCount ?? 0,
                ],
                'products' => [
                    'totalProducts' => $totalProducts ?? 0,
                    'pendingProducts' => $pendingProducts ?? 0,
                    'canceledProducts' => $canceledProducts ?? 0,
                ],
                'revenue' => [
                    'totalRevenue' => $totalRevenue ?? 0,
                ],
                'transactions' => [
                    'totalTransactions' => $totalTransactions ?? 0,
                ],
                'orders' => [
                    'pendingOrders' => $pendingOrders ?? 0,
                ],
                'activities' => [
                    'yearlyRevenue' => $yearlyRevenue ?? 0,
                    'monthlyRevenue' => $monthlyRevenue,
                    'currentYear' => now()->year,
                ],
            ];
            return $this->sendResponse($response, 'Dashboard data retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving dashboard data', ['error' => $e->getMessage()], 500);
        }
    }
}
