<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Wallet;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderCanceledNotification;
use App\Notifications\OrderDeliveryRequestNotification;
use App\Notifications\OrderNotification;
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
            'status' => 'nullable|in:pending,canceled,accepted,deliveryRequest,acceptDelivery',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
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
            'status' => $request->status,
        ]);
        $user->decrement('balance', $request->total_amount);
        $order->product->user->notify(new OrderNotification($order));
        return $this->sendResponse([],'Order created successfully');
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
                        'image'         => $order->product->image ? url('products/' . $order->product->image) : url('avatar/product.png'),
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
    }
    public function getSellerOrder()
    {
        $orders = Order::with(['product'])
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
                        'image'     =>$order->user->image ? url('profile/',$order->user->image) : url('avatar/profile.png'),
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
                        'image'         => $order->product->image ? url('products/' . $order->product->image) : url('avatar/product.png'),
                    ],
                    'notes'         => $order->notes,
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
        if ($order->status != 'pending') {
            return $this->sendError('Order cannot be accepted as it is not in pending status.', [], 400);
        }
        $order->status = 'accepted';
        $order->save();
        $order->user->notify(new OrderAcceptedNotification($order));
        return $this->sendResponse([], 'Order accepted successfully.');
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
        if ($order->status !== 'accepted') {
            return $this->sendError('Delivery request can only be made for accepted orders.', [], 400);
        }
        $order->status = 'deliveryRequest';
        $order->save();
        $order->user->notify(new OrderDeliveryRequestNotification($order));
        return $this->sendResponse($order, 'Delivery request initiated successfully.');
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
        if ($order->status !== 'deliveryRequest') {
            return $this->sendError('Order cannot be accepted as it is not in delivery request status.', [], 400);
        }
        $order->status = 'acceptDelivery';
        $order->save();

        $product = $order->product;
        $productOwner = $product->user;
        $productOwner->increment('balance', $order->total_amount);

         $wallet = Wallet::create([
            "user_id" => $order->user_id,
            "amount" => $order->total_amount,
            "total_love" => $order->total_amount * 0.95,  // 5% deduction
            "payment_method" => $request->payment_method ?? 'manual',
            "status" => "buy"
        ]);
        return $this->sendResponse($order, 'Delivery accepted successfully.');
    }
}
