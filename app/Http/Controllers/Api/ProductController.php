<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
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
            'price' => 'sometimes|required|numeric|min:0',
            'product_code' => 'sometimes|required|string|unique:products,product_code,' . $id,
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

        return response()->json(['message' => 'Product approved successfully'], 200);
    }

    // Cancel a product
    public function canceled($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $product->status = 'canceled';
        $product->save();

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

        return response()->json(['message' => 'Product marked as pending'], 200);
    }
}
