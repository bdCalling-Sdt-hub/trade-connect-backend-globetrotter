<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ProductApprovedNotification;
use App\Notifications\ProductCanceledNotification;
use App\Notifications\ProductNotification;
use App\Notifications\ProductPendingNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
class ProductController extends Controller
{
    public function index()
    {
        try {
            $userId = auth()->user()->id;

            $products = Product::whereHas('shop', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->where('status', 'approved')
              ->with(['shop', 'category'])
              ->get();

            return $this->sendResponse($products, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving products', ['error' => $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => 'required|exists:shops,id',
            'category_id' => 'required|exists:categories,id',
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $imageUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if ($image->isValid()) {
                    $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('products'), $fileName);
                    $imageUrls[] =  $fileName;
                }
            }
        }
        $userId = auth()->user()->id;
        $uniqueProductCode = $this->generateUniqueProductCode($userId);
        $product = Product::create([
            'shop_id' => $request->shop_id,
            'category_id' => $request->category_id,
            'product_name' => $request->product_name,
            'price' => $request->price,
            'product_code' => $uniqueProductCode,
            'description' => $request->description,
            'images' => json_encode($imageUrls),
            'status' => $request->status,
        ]);

        $adminUser = User::where('role', 'ADMIN')
            ->where('status', 'active')
            ->where('verify_email', 1)
            ->first();

        if (!$adminUser) {
            return $this->sendError([], "No Admin Users Found.");
        }
        $adminUser->notify(new ProductNotification($product));

        return response()->json(['data' => $product, 'message' => 'Product created successfully.'], 201);
    }
    private function generateUniqueProductCode($userId)
    {
        do {
            $productCode = Str::random(5) . $userId;
            $codeExists = Product::where('product_code', $productCode)->exists();

        } while ($codeExists);

        return $productCode;
    }

    public function update(Request $request, $id)
    {

        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        if ($product->shop->user_id !== auth()->user()->id || $product->status !== 'approved') {
            return response()->json(['message' => 'Unauthorized or product not approved.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_name' => 'sometimes|required|string|max:255',
            'price' => 'required|numeric|min:0',
            'product_code' =>'required|string|unique:products,product_code,' . $id,
            'description' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }
        $newImageUrls = [];
        $oldImageUrls = json_decode($product->images, true) ?? [];

        if ($request->hasFile('images')) {
            foreach ($oldImageUrls as $oldImageUrl) {
                $oldImagePath = public_path($oldImageUrl);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            foreach ($request->file('images') as $image) {
                if ($image->isValid()) {
                    $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('products'), $fileName);
                    $newImageUrls[] = $fileName;
                }
            }
        } else {
            $newImageUrls = $oldImageUrls;
        }

        $product->update([
            'product_name' => $request->product_name ?? $product->product_name,
            'price' => $request->price ?? $product->price,
            'product_code' => $request->product_code ?? $product->product_code,
            'description' => $request->description ?? $product->description,
            'images' => json_encode($newImageUrls),
            'status' => $request->status ?? $product->status,
        ]);

        return response()->json(['data' => $product, 'message' => 'Product updated successfully.']);
    }
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        if ($product->shop->user_id !== auth()->user()->id || $product->status !== 'approved') {
            return response()->json(['message' => 'Unauthorized or product not approved.'], 403);
        }
        $images = json_decode($product->images, true) ?? [];
        foreach ($images as $image) {
            $imagePath = public_path($image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $product->delete();
        return response()->json(['message' => 'Product deleted successfully.']);
    }

    public function approved($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $product->status = 'approved';
        $product->save();

        $user = $product->shop->user;

        if (!$user) {
            return $this->sendError('Shop owner not found', [], 404);
        }
        $user->notify(new ProductApprovedNotification($product));

        return response()->json(['message' => 'Product approved successfully'], 200);
    }

    public function canceled($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $product->status = 'canceled';
        $product->save();

        $user = $product->shop->user;
        if (!$user) {
            return $this->sendError('Shop owner not found', [], 404);
        }
        $user->notify(new ProductCanceledNotification($product));

        return response()->json(['message' => 'Product canceled successfully'], 200);
    }

    // Mark a product as pending
    public function pending($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $product->status = 'pending';
        $product->save();

        $user = $product->shop->user;
        if (!$user) {
            return $this->sendError('Shop owner not found', [], 404);
        }
        $user->notify(new ProductPendingNotification($product));

        return response()->json(['message' => 'Product marked as pending'], 200);
    }
    public function userproducts()
    {
        $userId = Auth::id();
        $shop = Shop::where('user_id', $userId)->first();
        if (!$shop) {
            return $this->sendError([], "No shop found for the authenticated user.");
        }
        $products = Product::where('shop_id', $shop->id)->get();
        if (!$products) {
            return $this->sendError([], "No products found for the shop.");
        }

        return $this->sendResponse($products, 'User products retrieved successfully.');
    }
}
