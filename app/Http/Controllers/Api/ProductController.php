<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
    public function categories()
    {
        try {
            $categories = Category::orderBy('id', 'DESC')->paginate(10);
            $formattedCategories = $categories->getCollection()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                ];
            });
            $paginatedData = $categories->setCollection($formattedCategories);
            return $this->sendResponse($paginatedData, 'Categories retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving categories', ['error' => $e->getMessage()], 500);
        }
    }
    public function index()
    {
        try {
            $products = Product::with(['shop.user', 'category'])
            ->where('status', 'approved')
            ->where(function ($query) {
                $query->whereHas('shop.user', function ($q) {
                    $q->where('privacy', 'public'); // Show public products
                })
                ->orWhereHas('shop.user', function ($q) {
                    $q->where('privacy', 'friends'); // Show friends' products
                });
            })
            ->orderBy('id','desc')
            ->paginate(20);

            $products = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'full_name' => $product->user->full_name,
                    'user_name' => $product->user->user_name,
                    'image' => $product->user->image
                        ? url('profile/',$product->user->image)
                        : url('avatar/profile.png'),
                    'product_name' => $product->product_name,
                    'category_name' => $product->category->category_name,
                    'product_price' => $product->price,
                    'product_code' => $product->product_code,
                    'product_description' => $product->description,
                    'product_status' => $product->status,
                    'images' => collect(json_decode($product->images))->map(function ($image) {
                        return $image ? url("products/", $image) : url('avatar/product.png');
                    }),
                    'shop' => [
                        'shop_name' => $product->shop->shop_name,
                        'seller_name' => $product->shop->user->full_name,
                        'image' => $product->shop->user->image
                            ? url('profile/',$product->user->image)
                            : url('avatar/profile.png'),
                    ],
                ];
            });
            return $this->sendResponse($products, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving products', ['error' => $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:50000',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }
        $user = Auth::user();
        $shop = $user->shop;
        if (!$shop) {
            return response()->json([
                'data'=>[],
                'message'=>"You have no shop. Please create a shop.",
                'status'=>404
            ]);
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
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'category_id' => $request->category_id,
            'product_name' => $request->product_name,
            'price' => $request->price,
            'product_code' => $uniqueProductCode,
            'description' => $request->description,
            'images' => json_encode($imageUrls),
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
        if ($product->status === 'approved') {
            return response()->json([
                'message' => 'You cannot change your product after it has been approved.'
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'product_code' =>'max:255|required|string|unique:products,product_code,' . $id,
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
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
            'category_id'=>$request->category_id ?? $product->category_id,
            'product_name' => $request->product_name ?? $product->product_name,
            'price' => $request->price ?? $product->price,
            'product_code' => $request->product_code ?? $product->product_code,
            'description' => $request->description ?? $product->description,
            'images' => json_encode($newImageUrls),
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
    public function productList(Request $request)
    {
        try {
            $search = $request->get('search');
            $categoryId = $request->get('category_id');
            $minPrice = $request->get('min_price');
            $maxPrice = $request->get('max_price');

            $products = Product::with(['shop.user', 'category'])
                ->when($search, function ($query, $search) {
                    $query->where('product_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
                ->when($categoryId, function ($query, $categoryId) {
                    $query->where('category_id', $categoryId);
                })
                ->when($minPrice, function ($query, $minPrice) {
                    $query->where('price', '>=', $minPrice);
                })
                ->when($maxPrice, function ($query, $maxPrice) {
                    $query->where('price', '<=', $maxPrice);
                })
                ->orderBy('id', 'DESC')
                ->paginate(10);

            $formattedProducts = $products->getCollection()->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'full_name'     => $product->user->full_name,
                    'user_name'     => $product->user->user_name,
                    'image'=>$product->user->image ? url('profile/',$product->user->image) : url('avatar/profile.png'),
                    'product_category' => $product->category->category_name,
                    'price' => $product->price,
                    'product_status' => $product->status,
                    'description' => $product->description,
                    'images' => collect(json_decode($product->images))->map(function ($image) {
                        return $image ? url("products/",$image) : url('avatar/product.png');
                    }),
                    'shop' => [
                        'shop_name' => $product->shop->shop_name,
                        'logo' => $product->shop->logo ? url('logos/',$product->shop->logo) : url('avatar/logo.png'),
                        'seller' => [
                            'seller_name' => $product->shop->user->full_name,
                            'user_name' => $product->shop->user->user_name,
                            'email' => $product->shop->user->email,
                            'image' => $product->shop->user->image
                                ? url('profile/',$product->shop->user->image)
                                : url('avatar/profile.png'),
                        ],
                    ],
                ];
            });
            $paginatedData = $products->setCollection($formattedProducts);
            return $this->sendResponse($paginatedData, 'Product list retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving product list', ['error' => $e->getMessage()], 500);
        }
    }
    public function userproducts()
    {
        $userId = Auth::id();
        $shop = Shop::where('user_id', $userId)->first();
        if (!$shop) {
            return $this->sendError([], "No shop found for the authenticated user.");
        }
        $products = Product::where('shop_id', $shop->id)
            ->with(['category', 'shop.user'])
            ->orderBy('id', 'desc')
            ->get();
            if ($products->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No approved products found for this shop.',
                    'status' => 404
                ], 404);
            }


        $formattedProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'full_name' => $product->user->full_name,
                'user_name' => $product->user->user_name,
                'image' => $product->user->image ? url('profile/', $product->user->image) : url('profile/profile.png'),
                'product_name' => $product->product_name,
                'status' => $product->status,
                'category_name' => $product->category->category_name,
                'product_code' => $product->product_code,
                'price' => $product->price,
                'description' => $product->description,
                'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                'product_images' => collect(json_decode($product->images))->map(function ($image) {
                    return $image ? url("products/", $image) : url('avatar/product.png');
                }),
                'shop' => [
                    'shop_name' => $product->shop->shop_name,
                    'logo' => $product->shop->logo
                            ? url('logos/',$product->shop->logo)
                            : url('avatar/logo.png'),
                    'seller' => [
                        'seller_name' => $product->shop->user->full_name,
                        'user_name' => $product->shop->user->user_name,
                        'email' => $product->shop->user->email,
                        'image' =>$product->shop->user->image
                            ? url('profile/',$product->shop->user->image)
                            : url('avatar/profile.png')
                    ],
                ],
            ];
        });
        return $this->sendResponse($formattedProducts, 'Products retrieved successfully.');
    }
}
