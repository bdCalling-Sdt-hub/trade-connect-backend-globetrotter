<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function userShop()
    {
        $user = Auth::user();
        $shop = $user->shop;
        if (!$shop) {
            return response()->json([
                'data' => [],
                'message' => "No shops found.",
                'status' => 404
            ]);
        }
        $approvedProducts = $shop->products()->where('status', 'approved')->get();
        $pendingProducts = $shop->products()->where('status', 'pending')->get();
        $canceledProducts = $shop->products()->where('status', 'canceled')->get();
        $approvedProductsCount = $approvedProducts->count();
        $pendingProductsCount = $pendingProducts->count();
        $canceledProductsCount = $canceledProducts->count();
        $totalProductsCount = $shop->products()->count();
        $logoPath = public_path('logos/' . $shop->logo);
        if (file_exists($logoPath)) {
            $logoUrl = url('logos/' . $shop->logo);
        } else {
            $logoUrl = url('avatar/logo.png');
        }
        $shopData = [
            'id' => $shop->id,
            'shop_name' => $shop->shop_name,
            'logo' => $logoUrl,
            'approvedProductsCount'=>$approvedProductsCount,
            'pendingProductsCount'=>$pendingProductsCount,
            'canceledProductsCount'=>$canceledProductsCount,
            'totalProductsCount'=>$totalProductsCount,
            'status' => $shop->status,
            'user' => $shop->user,
            'created_at' => $shop->created_at->toIso8601String(),
            'updated_at' => $shop->updated_at->toIso8601String(),
        ];
        return $this->sendResponse($shopData, "User shop fetched successfully.");
    }
    public function anotherUserShop(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return $this->sendError('User not found.', [], 404);
        }
        $shop = $user->shop;
        if (!$shop) {
            return $this->sendError('No shop found for this user.', [], 404);
        }
        $approvedProducts = $shop->products()->where('status', 'approved')->get();
        $pendingProducts = $shop->products()->where('status', 'pending')->get();
        $canceledProducts = $shop->products()->where('status', 'canceled')->get();
        $approvedProductsCount = $approvedProducts->count();
        $pendingProductsCount = $pendingProducts->count();
        $canceledProductsCount = $canceledProducts->count();
        $totalProductsCount = $shop->products()->count();
        $logoUrl = url('logos/' . $shop->logo);
        $approvedProductDetails = $approvedProducts->map(function ($product) {
            $imageUrls = collect(json_decode($product->images))->map(function ($image) {
                return $image ? url("products/", $image) : url('avatar/product.png');
            });
            return [
                'id' => $product->id,
                'full_name' => $product->user->full_name,
                'user_name' => $product->user->user_name,
                'image' => $product->user->image ? url('profile/', $product->user->image) : url('avatar/profile.png'),
                'product_name' => $product->product_name,
                'status' => $product->status,
                'category_name' => $product->category->category_name,
                'product_code' => $product->product_code,
                'price' => $product->price,
                'description' => $product->description,
                'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                'product_images' => $imageUrls,
            ];
        });
        $pendingProductDetails = $pendingProducts->map(function ($product) {
            $imageUrls = collect(json_decode($product->images))->map(function ($image) {
                return $image ? url("products/", $image) : url('avatar/product.png');
            });
            return [
                'id' => $product->id,
                'full_name' => $product->user->full_name,
                'user_name' => $product->user->user_name,
                'image' => $product->user->image ? url('profile/', $product->user->image) : url('avatar/profile.png'),
                'product_name' => $product->product_name,
                'status' => $product->status,
                'category_name' => $product->category->category_name,
                'product_code' => $product->product_code,
                'price' => $product->price,
                'description' => $product->description,
                'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                'product_images' => $imageUrls,
            ];
        });
        $canceledProductDetails = $canceledProducts->map(function ($product) {
            $imageUrls = collect(json_decode($product->images))->map(function ($image) {
                return $image ? url("products/", $image) : url('avatar/product.png');
            });
            return [
                'id' => $product->id,
                'full_name' => $product->user->full_name,
                'user_name' => $product->user->user_name,
                'image' => $product->user->image ? url('profile/', $product->user->image) : url('avatar/profile.png'),
                'product_name' => $product->product_name,
                'status' => $product->status,
                'category_name' => $product->category->category_name,
                'product_code' => $product->product_code,
                'price' => $product->price,
                'description' => $product->description,
                'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                'product_images' => $imageUrls,
            ];
        });
        $shopData = [
            'id' => $shop->id,
            'user_id' => $shop->user_id,
            'shop_name' => $shop->shop_name,
            'logo_url' => $logoUrl,
            'status' => $shop->status,
            'approved_products_count' => $approvedProductsCount,
            'pending_products_count' => $pendingProductsCount,
            'canceled_products_count' => $canceledProductsCount,
            'total_products_count' => $totalProductsCount,
            'approved_products' => $approvedProductDetails,
            'pending_products' => $pendingProductDetails,
            'canceled_products' => $canceledProductDetails,
            'created_at' => $shop->created_at->toIso8601String(),
            'updated_at' => $shop->updated_at->toIso8601String(),
        ];
        return $this->sendResponse($shopData, 'User shop fetched successfully.');
    }
    public function index()
    {
        try {
            $shops = Shop::with('user')->get();
            $formattedShops = $shops->map(function ($shop) {
                return [
                    'id'=>$shop->id,
                    'shop_name' => $shop->shop_name,
                    'seller' => [
                        'seller_name' => $shop->user->full_name,
                        'user_name' => $shop->user->user_name,
                        'image' => $shop->user->image ? url('profile/',$shop->user->image) : url('avatar/profile.png'),
                    ],
                ];
            });
            return $this->sendResponse($formattedShops, 'Shops retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving shops', ['error' => $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'status' => 'boolean',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        if(Shop::where('user_id', $request->user()->id)->exists()) {
            return $this->sendError([], ['error'=> 'Your shop already exits.']) ;
        }
        $shop = new Shop();
        $shop->user_id = auth()->user()->id;
        $shop->shop_name = $request->shop_name;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $fileName = time() . '.' . $logo->getClientOriginalExtension();
            $logo->move(public_path('logos'), $fileName);
            $shop->logo = $fileName;
        }
        $shop->status = $request->status ?? true;
        $shop->save();

        return $this->sendResponse($shop, 'Shop created successfully.'); // Load user relationship
    }
    public function show($id)
    {
        $shop = Shop::with('user')->find($id); // Eager load user relationship
        if (!$shop) {
            return $this->sendError('Shop not found', [], 404);
        }
        return $this->sendResponse($shop, 'Shop retrieved successfully.');
    }
    public function update(Request $request, $id)
    {
        $shop = Shop::find($id);
        if (!$shop) {
            return $this->sendError('Shop not found', [], 404);
        }
        $validator = Validator::make($request->all(), [
            'shop_name' => 'sometimes|required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'status' => 'boolean',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $shop->shop_name = $request->shop_name ?? $shop->shop_name;
        if ($request->hasFile('logo')) {
            if ($shop->logo) {
                $oldLogoPath = public_path('logos/' . $shop->logo);
                if (file_exists($oldLogoPath)) {
                    unlink($oldLogoPath);
                }
            }
            $logo = $request->file('logo');
            $fileName = time() . '.' . $logo->getClientOriginalExtension();
            $logo->move(public_path('logos'), $fileName);
            $shop->logo = $fileName;
        }
        $shop->status = $request->status ?? $shop->status;
        $shop->save();
        return $this->sendResponse($shop, 'Shop updated successfully.'); // Load user relationship
    }
    public function destroy($id)
    {
        $shop = Shop::find($id);
        if (!$shop) {
            return $this->sendError('Shop not found', [], 404);
        }
        if ($shop->logo) {
            $oldLogoPath = public_path('logos/' . $shop->logo);
            if (file_exists($oldLogoPath)) {
                unlink($oldLogoPath);
            }
        }
        $shop->delete();
        return $this->sendResponse([], 'Shop deleted successfully.');
    }
}
