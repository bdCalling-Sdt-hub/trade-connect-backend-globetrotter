<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
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
                'data'=>[],
                'message'=>"No shop found.",
                'status'=>404
            ]);
        }
        return $this->sendResponse($shop, "User shop fetched successfully.");
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

        return $this->sendResponse($shop, 'Shop created successfully.', 201); // Load user relationship
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
        $shop->delete();
        return $this->sendResponse([], 'Shop deleted successfully.');
    }
}
