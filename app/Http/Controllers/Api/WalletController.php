<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\RequestLove;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\LoveRequestNotification;
use App\Notifications\ReceivedLoveNotification;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function walletRecharge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'total_love' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError("Validation Error:", $validator->errors());
        }
        try {
            $user = Auth::user();
            $wallet = Wallet::Create([
                "user_id"=> $user->id,
                "amount"=> $request->amount,
                "total_love"=> $request->total_love,
                "payment_method"=> $request->payment_method,
                "status"=> 'buy',
            ]);
            if ($wallet) {
                $user->update([
                $user->increment('balance', $request->total_love),
                ]);
            }
            return $this->sendResponse($wallet, 'Wallet recharged successfully.');
        } catch (Exception $e) {
            return $this->sendError("Error processing the wallet recharge: " . $e->getMessage());
        }
    }
    public function searchUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }
        $query = $request->input('query');
        try {
            $users = User::where('full_name', 'like', '%' . $query . '%')
                ->orWhere('user_name', 'like', '%' . $query . '%')
                ->orWhere('email', 'like', '%' . $query . '%')
                ->get();
            $userData = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'user_name' => $user->user_name,
                    'email' => $user->email,
                    'image' => $user->image ? url('profile/', $user->image) : url('avatar/profile.png'),
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ];
            });
            return $this->sendResponse($userData, 'Users retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Error retrieving users.', $e->getMessage(), 500);
        }
    }
    public function requestLove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'request_id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError("Validation Error:", $validator->errors());
        }
        try {
            $requestId = $request->request_id;
            if($requestId){
                RequestLove::create([
                    'user_id'=> Auth()->id(),
                    "amount"=> $request->amount,
                    "request_id"=> $requestId,
                ]);
                $recipient = User::find($requestId);
                $recipient->notify(new LoveRequestNotification($request->amount, Auth::id()));
        }
            return $this->sendResponse([],"Successfully send love request.");
        } catch (Exception $e) {
            return $this->sendError("Exceptions:", $e->getMessage(),500);
        }
    }
    public function myRequest()
    {
        $user = Auth()->user();
        if(!$user){
            return $this->sendError("User not found.");
        }
        $requestLoves = RequestLove::where('user_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->map(function ($love) {
                return [
                    'id' => $love->id,
                    'amount' => $love->amount,
                    'status' => $love->status,
                    'requested_by' => [
                        'request_id' => $love->request_id,
                        'full_name' => $love->requestedBy->full_name,
                        'user_name' => $love->requestedBy->user_name,
                        'email' => $love->requestedBy->email,
                        'image' => $love->requestedBy->image ? url('profile/' . $love->requestedBy->image) : url('avatar/profile.png'),
                    ],
                    'created_at' => $love->created_at->format('Y-m-d H:i:s'),
                ];
            });

        if ($requestLoves->isEmpty()) {
            return $this->sendError('No pending requests found.');
        }
        return $this->sendResponse($requestLoves, 'Pending requests retrieved successfully.');
    }
    public function acceptRequestLove(Request $request, $requestLoveId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'total_love' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->sendError("Validation Error:", $validator->errors());
        }
        $requestLove = RequestLove::find($requestLoveId);
        if (!$requestLove) {
            return $this->sendError('Request Love not found.');
        }
        try {
            $user = Auth::user();
            if($user->balance >= $request->total_love){
                $wallet = Wallet::create([
                    "user_id" => $user->id,
                    "amount" => $request->amount,
                    "total_love" => $request->total_love,
                    "payment_method" => $request->payment_method,
                    "status"=>'send'
                ]);
                if ($wallet) {
                    $user->decrement('balance', $request->amount);
                    $requestLove->update(['status' => 'accepted']);
                    $requestedUser = $requestLove->requestedBy;
                    $requestedUser->increment('balance', $wallet->total_love);
                }
                $requestedUser->notify(new ReceivedLoveNotification($wallet));
                return $this->sendResponse($wallet, 'Wallet transfer successfully.');
            }else{
                return $this->sendError("Insufficient balance.");
            }
        } catch (Exception $e) {
            return $this->sendError("Error processing the wallet recharge: " . $e->getMessage(), 500);
        }
    }
    public function rejecttRequestLove($requestLoveId)
    {
        $requestLove = RequestLove::where("id", $requestLoveId)->first();
        if (!$requestLove) {
            return $this->sendError("Request love not foun.");
        }
        $requestLove->update(["status"=> 'rejeted']);
        return $this->sendResponse($requestLove,'Successfully rejected request love.');
    }
    public function transferLove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'total_love' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'received_id'=>'required'
        ]);
        if ($validator->fails()) {
            return $this->sendError("Validation Error:", $validator->errors());
        }
        try {
            $user = Auth::user();
            $receivedId = $request->received_id;
            $receivedUser = User::where('id', $receivedId)->first();
            if(!$receivedUser){
                return $this->sendError('Received user not found.');
            }
            if ($user->balance >= $request->total_love) {
                $wallet = Wallet::create([
                        "user_id" => $user->id,
                        "amount" => $request->amount,
                        "total_love" => $request->total_love,
                        "payment_method" => $request->payment_method,
                        "status"=> "send"
                ]);
                 $user->decrement('balance', $request->amount);
                 $receivedUser->increment('balance', $wallet->total_love);
                $receivedUser->notify(new ReceivedLoveNotification($wallet));

                return $this->sendResponse($wallet, 'Wallet transfer successfully.');
            }else{
                return $this->sendError("Insufficient balance.");
            }
        } catch (Exception $e) {
            return $this->sendError("Error processing the wallet recharge: " . $e->getMessage(), 500);
        }
    }
    public function walletTransferHistory()
    {
        $user = Auth::user();
        $wallets = Wallet::with('user')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->paginate(10);

        if ($wallets->isEmpty()) {
            return $this->sendError("No transactions found.", 404);
        }
        $formattedWallets = $wallets->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'amount' => $wallet->amount,
                'total_love' => $wallet->total_love,
                'payment_method' => ucfirst($wallet->payment_method),
                'user' => [
                    'id' => $wallet->user->id,
                    'full_name' => $wallet->user->full_name,
                    'user_name' => $wallet->user->user_name,
                    'email' => $wallet->user->email,
                    'image' => $wallet->user->image ? url('profile/'. $wallet->user->image) : url('avatar/profile.png'),
                ],
                'status' => ucfirst($wallet->status),
                'created_at' => $wallet->created_at->format('Y-m-d H:i:s'),
            ];
        });
        return $this->sendResponse([
            'user_balance'=> number_format($user->balance) ?? 0,
            'transactions' => $formattedWallets,
            'pagination' => [
                'total' => $wallets->total(),
                'current_page' => $wallets->currentPage(),
                'last_page' => $wallets->lastPage(),
                'per_page' => $wallets->perPage(),
            ]
        ], "Transaction history retrieved successfully.");
    }
    public function transitions(Request $request)
    {
        try {
            $wallets = Wallet::orderBy('id', 'desc')->paginate(10);
            if ($wallets->isEmpty()) {
                return $this->sendError([], "No wallet transactions found.");
            }
            $formattedWallets = $wallets->map(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'user' => [
                        'id' => $wallet->user_id,
                        'full_name' => $wallet->user->full_name,
                        'user_name' => $wallet->user->user_name,
                        'email' => $wallet->user->email,
                        'balance' => $wallet->user->balance,
                        'image' => $wallet->user->image
                            ? url('profile/', $wallet->user->image)
                            : url('avatar/profile.png'),
                    ],
                    'payment_method' => $wallet->payment_method,
                    'total_love' => $wallet->total_love,
                    'amount' => $wallet->amount,
                    'status' => $wallet->status,
                    'created_at' => $wallet->created_at->format('Y-m-d H:i:s'),
                ];
            });
            $today = now();
            $newUsers = [
                'week' => User::where('created_at', '>=', $today->copy()->subWeek())->count(),
                'month' => User::where('created_at', '>=', $today->copy()->subMonth())->count(),
                'year' => User::where('created_at', '>=', $today->copy()->subYear())->count(),
            ];
            $activeUsers = User::where('status', 'active')->count();
            $transactions = [
                'week' => Wallet::where('created_at', '>=', $today->copy()->subWeek())->count(),
                'month' => Wallet::where('created_at', '>=', $today->copy()->subMonth())->count(),
                'year' => Wallet::where('created_at', '>=', $today->copy()->subYear())->count(),
            ];
            $totalTransactions = Wallet::count();
            $revenue = [
                'week' => Wallet::where('created_at', '>=', $today->copy()->subWeek())->sum('amount'),
                'month' => Wallet::where('created_at', '>=', $today->copy()->subMonth())->sum('amount'),
                'year' => Wallet::where('created_at', '>=', $today->copy()->subYear())->sum('amount'),
            ];
            $totalRevenue = Wallet::sum('amount');
            $response = [
                'transitions' => $formattedWallets,
                'statistics' => [
                    'new_users' => $newUsers,
                    'active_users' => $activeUsers,
                    'transactions' => $transactions,
                    'total_transactions' => $totalTransactions,
                    'revenue' => $revenue,
                    'total_revenue' => $totalRevenue,
                ],
            ];
            return $this->sendResponse($response, 'Wallet transactions and statistics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Error retrieving wallet transactions and statistics', ['error' => $e->getMessage()], 500);
        }
    }
}
