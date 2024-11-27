<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Wallet;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderCanceledNotification;
use App\Notifications\OrderDeliveryRequestNotification;
use App\Notifications\OrderNotification;
use App\Notifications\OrderRejectedNotification;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'total_amount' => 'required|numeric',
            'phone_number' => 'nullable|string',
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'zipcode' => 'nullable|string',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        try{
            $user = auth()->user();
            if ($user->balance < $request->total_amount) {
                return $this->sendError("Insufficient balance.");
            }
            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'total_amount' => $request->total_amount,
                'phone_number' => $request->phone_number,
                'country' => $request->country,
                'state' => $request->state,
                'zipcode' => $request->zipcode,
                'address' => $request->address,
                'notes' => $request->notes,
            ]);
            $user->decrement('balance', $request->total_amount);
            $order->product->user->notify(new OrderNotification($order));
            return $this->sendResponse($order,'Order created successfully');

            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function getUserOrder()
    {
        $orders = Order::where('user_id', auth()->user()->id)
            ->with(['product'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'order_id'      => $order->id,
                    'total_amount'  => $order->total_amount,
                    'status'        => $order->status,
                    'created_at'    => $order->created_at->format('Y-m-d H:i:s'),
                    'phone_number'  => $order->phone_number,
                    'address'       => [
                        'country'   => $order->country,
                        'state'     => $order->state,
                        'zipcode'   => $order->zipcode,
                        'full_address' => $order->address,
                    ],
                    'product'       => [
                        'product_id'    => $order->product->id,
                        'product_name'  => $order->product->product_name,
                        'price'         => $order->product->price,
                        'description'   => $order->product->description,
                        'images'        => collect(json_decode($order->product->images))->map(function ($image) {
                                            return $image
                                                ? url("products/", $image)
                                                : url('avatar/product.png');
                        }),
                    ],
                    'notes'         => $order->notes,
                ];
            });
        if ($orders->isEmpty()) {
            return $this->sendError('No orders found for this user.', [], 404);
        }
        return $this->sendResponse($orders, 'Orders retrieved successfully.');
    }
    public function cancelOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        try{
            $order = Order::find($request->order_id);
            if ($order->status === 'canceled') {
                return $this->sendError('Order is already canceled.', [], 400);
            }
            if ($order->status === 'accepted' || $order->status === 'deliveryRequest' || $order->status === 'acceptDelivery') {
                return $this->sendError('Cannot cancel the order as it is already processed.', [], 400);
            }
            $order->status = 'canceled';
            $order->save();
            $user = auth()->user();
            $user->increment('balance', $order->total_amount);
            $order->product->user->notify(new OrderCanceledNotification($order));
            return $this->sendResponse([], 'Order canceled and notification sent.');

            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function getSellerOrder()
    {
        $user = Auth::user();
        $orders = Order::with(['product', 'user'])
        ->whereHas('product', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->orderBy('id', 'desc')
        ->get()
            ->map(function ($order) {
                return [
                    'order_id'      => $order->id,
                    'total_amount'  => $order->total_amount,
                    'status'        => $order->status,
                    'created_at'    => $order->created_at->format('Y-m-d H:i:s'),
                    'user'          =>[
                        'id'        =>$order->user->id,
                        'full_name' =>$order->user->full_name,
                        'user_name' =>$order->user->user_name,
                        'image'     =>$order->user->image
                            ? url('profile/',$order->user->image)
                            : url('avatar/profile.png'),
                    ],
                    'phone_number'  => $order->phone_number,
                    'address'       => [
                        'country'   => $order->country,
                        'state'     => $order->state,
                        'zipcode'   => $order->zipcode,
                        'full_address' => $order->address,
                    ],
                    'product'       => [
                        'product_id'    => $order->product->id,
                        'product_name'  => $order->product->product_name,
                        'price'         => $order->product->price,
                        'description'   => $order->product->description,
                        'images'        => collect(json_decode($order->product->images))->map(function ($image) {
                                        return $image
                                            ? url("products/", $image)
                                            : url('avatar/product.png');
                        }),
                    ],
                    'notes'=> $order->notes,
                ];
            });
        if ($orders->isEmpty()) {
            return $this->sendError('No orders found for this user.', [], 404);
        }
        return $this->sendResponse($orders, 'Orders retrieved successfully.');
    }
    public function acceptOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $order = Order::find($request->order_id);
        if (!$order) {
            return $this->sendError('Order not found.', [], 404);
        }
        try{
            if ($order->status != 'pending') {
                return $this->sendError('Order cannot be accepted as it is not in pending status.', [], 400);
            }
            $order->status = 'accepted';
            $order->save();
            $order->user->notify(new OrderAcceptedNotification($order));
            return $this->sendResponse([], 'Order accepted successfully.');

            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function deliveryRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $order = Order::find($request->order_id);
        if (!$order) {
            return $this->sendError('Order not found.', [], 404);
        }
        try{
            if($order->status == 'deliveryRequest')
            {
                return $this->sendResponse([], 'Delivery request already initiated.');
            }
            if ($order->status !== 'accepted') {
                return $this->sendError('Delivery request can only be made for accepted orders.', [], 400);
            }
            $order->status = 'deliveryRequest';
            $order->save();
            $order->user->notify(new OrderDeliveryRequestNotification($order));
            return $this->sendResponse([], 'Delivery request initiated successfully.');

            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function acceptDelivery(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $order = Order::find($request->order_id);

        if (!$order) {
            return $this->sendError('Order not found.', [], 404);
        }
        try{
            if ($order->status !== 'deliveryRequest') {
                return $this->sendError('Order cannot be accepted as it is not in delivery request status.', [], 400);
            }
            $order->status = 'acceptDelivery';
            $order->save();
            $product = $order->product;
            $productOwner = $product->user;
            $productOwner->balance += $order->product->price;
            $productOwner->save();
             Wallet::create([
                "user_id" => $order->user_id,
                "amount" => $order->total_amount,
                "total_love" => $order->product->price,
                "payment_method" => $request->payment_method ?? 'manual...',
                "status" => "purchage"
            ]);
            return $this->sendResponse($order, 'Delivery accepted successfully.');

            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function rejectDelivery(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        try{
            $order = Order::find($request->order_id);
            if (!$order) {
                return response()->json(['message' => 'Order not found.'], 404);
            }
            if ($order->status == 'rejectDelivery') {
                return response()->json(['message' => 'This order has already been rejected.'], 200);
            }
            if ($order->status !== 'deliveryRequest') {
                return response()->json(['message' => 'Only orders with a delivery request can be rejected.'], 400);
            }
            $order->status = 'rejectDelivery';
            $order->save();
            $order->product->user->notify(new OrderRejectedNotification($order));
            return response()->json(['message' => 'Order delivery rejected successfully.'], 200);

            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function rejectedDelivery(Request $request)
    {
        $query = Order::where('status', 'rejectDelivery')
                    ->orWhere('status', 'amountReturned');
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('user_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
            });
        }
        $rejectedOrders = $query->paginate(10);
        if ($rejectedOrders->isEmpty()) {
            return response()->json([
                'data' => [],
                'message' => 'No rejected deliveries found.',
                'status' => 404
            ]);
        }
        $rejectedOrdersData = $rejectedOrders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'product_id' => $order->product_id,
                'total_amount' => $order->total_amount,
                'phone_number' => $order->phone_number,
                'country' => $order->country,
                'state' => $order->state,
                'zipcode' => $order->zipcode,
                'address' => $order->address,
                'notes' => $order->notes,
                'status' => $order->status,
                'created_at' => $order->created_at->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
                'user' => [
                    'id' => $order->user->id,
                    'full_name' => $order->user->full_name,
                    'user_name' => $order->user->user_name,
                    'email' => $order->user->email,
                    'balance' => $order->user->balance,
                    'image' => $order->user->image ? url('profile/' . $order->user->image) : url('avatar/profile.png'),
                ],
                'product' => [
                    'id' => $order->product->id,
                    'product_name' => $order->product->product_name,
                    'price' => $order->product->price,
                    'description' => $order->product->description,
                ]
            ];
        });
        return response()->json([
            'data' => $rejectedOrdersData,
            'pagination' => [
                'current_page' => $rejectedOrders->currentPage(),
                'total_items' => $rejectedOrders->total(),
                'per_page' => $rejectedOrders->perPage(),
                'total_pages' => $rejectedOrders->lastPage(),
            ],
            'message' => 'Rejected deliveries retrieved successfully.',
            'status' => 200
        ]);
    }
    public function returnAmount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }
        try{
            $order = Order::find($request->order_id);
            if (!$order) {
                return $this->sendError('Order not found.');
            }
            if($order->status == 'amountReturned')
            {
                return $this->sendResponse([], 'Amount already returned to the user.');
            }
            $user = $order->user;
            if ($user->balance >= 0) {
                $user->balance= $order->total_amount;
            } else {
                return $this->sendError('User does not have a balance record.');
            }
            $order->status = 'amountReturned';
            $order->save();
            return $this->sendResponse([], 'Amount has been successfully returned to the user.');

            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
