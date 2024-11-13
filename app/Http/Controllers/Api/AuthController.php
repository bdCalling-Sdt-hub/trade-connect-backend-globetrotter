<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Mail\SendOtp;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Rules\Lowercase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function getUserName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        $query = $request->query('query');
        $members = User::whereRaw('LOWER(user_name) = ?', [strtolower($query)])->get()
        ->map(function ($member) {
            return [
                'user_id'   => $member->id,
                'user_name' => $member->user_name,
            ];
        });
        if ($members->isEmpty()) {
            return $this->sendError('No members found matching your query.', [], 404);
        }
        return $this->sendResponse($members, 'Members retrieved successfully.');
    }
    public function validateToken()
    {
        try {
            // Log the token for debugging
            $token = JWTAuth::getToken();
            \Log::info('Token being validated: ' . $token);

            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'token_status' => false,
                    'error' => 'Invalid token or user not found.'
                ], 401);
            }
            return response()->json([
                'status' => 200,
                'token_status' => true,
                'message' => 'Token is valid.',
            ]);
        } catch (TokenExpiredException $e) {
            return response()->json(['token_status' => false, 'error' => 'Token has expired.'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_status' => false, 'error' => 'Token is invalid.'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_status' => false, 'error' => 'Token is missing.'], 401);
        } catch (\Exception $e) {
            return response()->json(['token_status' => false, 'error' => 'An unexpected error occurred.'], 500);
        }
    }


    public function register(Request $request)
    {
        $user = User::where('email', $request->email)->where('verify_email', 0)->first();

        if ($user) {
            $this->sendOtpEmail($user);
            return response()->json([
                'status' => 200,
                'message' => 'Please check your email to validate your account.'
            ], 200);
        }
        $validator = Validator::make($request->all(), [
            'full_name'  => 'required|string|max:255',
            'user_name'  => ['required', 'string', 'max:255', 'unique:users,user_name', new Lowercase()],
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8|max:60',
            'address'    => 'required|string|max:255',
            'role'       => ['required', Rule::in(['MEMBER', 'ADMIN'])],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $user = $this->createUser($request);
        $this->sendOtpEmail($user);

        return response()->json(['success' => 'User registered successfully. OTP sent to your email.'], 201);
    }
    private function createUser($request)
    {
        try {
            $otp = rand(100000, 999999);
            $input = $request->except('c_password');
            $input['password'] = Hash::make($input['password']);
            $input['otp'] = $otp;
            $input['otp_expires_at'] = now()->addMinutes(10);
            $input['verify_email'] = 0;
            $input['user_name'] = strtolower(str_replace(' ', '_', $input['user_name']));

            return User::create($input);

            } catch (\Exception $e) {
                return $this->sendError('User Create Errors', ['error' => $e->getMessage()], 500);
        }
    }
    private function sendOtpEmail($user)
    {
        $otp = rand(100000, 999999);
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'verify_email' => 0
        ]);
        $emailData = [
            'name' => $user->full_name,
            'otp' => $otp,
        ];
        try {
            Mail::to($user->email)->queue(new OtpVerificationMail($emailData));
        } catch (\Exception $e) {
            return $this->sendError('Failed to send OTP email. Please try again.', ['error' => $e->getMessage()], 500);
        }

    }
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $user = User::where('otp', $request->otp)
                    ->where('verify_email', 0)
                    ->first();
        if (!$user) {
            return response()->json(['error' => 'Invalid OTP or the email is already verified.'], 401);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['error' => 'OTP has expired. Please request a new one.'], 401);
        }

        $user->update([
            'verify_email' => 1,
            'otp' => null,
            'otp_expires_at' => null,
            'status'=>'active'
        ]);
        try {
            if (!$token = JWTAuth::fromUser($user)) {
                return response()->json(['error' => 'Could not create token.'], 500);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token.'], 500);
        }

        // Return success response with token
        return response()->json([
            'status' => 200,
            'message' => 'Email verified successfully.',
            'token' => $token,
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token'], 500);
        }
        return response()->json([
            'status' => 200,
            'message' => 'Login successfully.',
            'token' => $token,
        ]);
    }
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'User successfully logged out.']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout, please try again.'], 500);
        }
    }
    public function forgotPassword(Request $request)
    {
        $email = $request->email;
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['error' => 'Email not found'], 401);
        } else if ($user->google_id != null || $user->facebook_id != null) {
            return response()->json([
                'status'=>400,
                'message' => 'Your are social user, You do not need to forget password',
            ], 400);
        }
         else {
            $this->sendOtpEmail($user);
            return response()->json(['status'=>200, 'message' => 'Please check your email for get the OTP']);
        }
    }
    public function resetPassword(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                "message" => "Your email is not exists"
            ], 401);
        }
        if (!$user->verify_email == 1) {
            return response()->json([
                "message" => "Your email is not verified"
            ], 401);
        }
        $validator = Validator::make($request->all(), [
            'password'   => 'required|min:8|max:60',
            'c_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        } else {
            $user->update(['password' => Hash::make($request->password)]);
            return response()->json(['status'=>200,'message' => 'Password reset successfully','data'=> $user], 200);
        }
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'You are not authorized!'], 401);
        }
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|different:current_password',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 409, 'errors' => $validator->errors()], 409);
        }
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['status' => 409, 'message' => 'Your current password is incorrect'], 409);
        }
        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['status' => 200, 'message' => 'Password updated successfully'], 200);
    }
    public function user()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 400, 'message' => 'You are not authenticated'], 400);
        }
        $users = User::orderBy('id','desc')->paginate(10);
        $formattedUsers = $users->map(function($user) {
            return [
                'id'=>$user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'email' => $user->email,
                'location' => $user->location,
                'address' => $user->address,
                'image' => $user->image ? url('Profile/' . $user->image) : null,
            ];
        });

        return response()->json(['status' => 200, 'users' => $formattedUsers], 200);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }
        $user = User::where('email', $request->email)->first();
        Mail::to($user->email)->queue(new OtpVerificationMail($user));

        return response()->json(['status' => 200, 'message' => 'OTP has been resent successfully.'], 200);
    }
    public function getProfile(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $imageUrl = $user->image ? url('profile/' . $user->image) : url('avatar/profile.png');
        $profileData = [
            'id'=> $user->id,
            'full_name' => $user->full_name,
            'user_name' => $user->user_name,
            'email'=> $user->email,
            'balance'=>$user->balance,
            'bio'=> $user->bio ?? '',
            'privicy'=>$user->privacy ??'',
            'location' => $user->location,
            'image' => $imageUrl,
        ];

        return response()->json([
            'status' => true,
            'message' => 'User profile fetched successfully.',
            'data' => $profileData,
        ], 200);
    }
    //profile update
    public function profile(Request $request)
    {
        $user = Auth::user();
        if(!$user){
            return $this->sendError([],"You are not user.");
        }
        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'location' => 'nullable|required|string|max:255',
            'bio' => 'nullable|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        if ($request->hasFile('image')) {
            if ($user->image) {
                $oldImagePath = public_path('profile/' . $user->image);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            $image = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('profile'), $fileName);
        }
        $user->full_name = $request->full_name ?? $user->full_name;
        $user->image = $fileName ?? $user->image;
        $user->location = $request->location ?? $user->location;
        $user->bio = $request->bio ?? $user->bio;
        $user->save();

        return $this->sendResponse($user, 'Profile updated successfully.');
    }
    public function isActive()
    {
        $user = Auth::user();
        $user->is_active = true;
        $user->save();

        return $this->sendResponse($user, 'User active status updated successfully.');
    }
    public function noActive()
    {
        $user = Auth::user();
        $user->is_active = false;
        $user->save();

        return $this->sendResponse($user, 'User no active status updated successfully.');
    }
    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ];
    }


    /*
    ----------------------------------------
               Admin Dashboard
    ----------------------------------------
    */

    public function updateRole(Request $request, $id)
    {
        try {
            $authUser = auth()->user();
            $userToUpdate = User::find($id);
            if (!$userToUpdate) {
                return response()->json(['message' => 'User not found.'], 404);
            }
            $validator = Validator::make($request->all(), [
                'role' => 'required|string|in:MEMBER,ADMIN',
            ]);
            if ($validator->fails()) {
                return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 400);
            }
            $userToUpdate->role = $request->role;
            $userToUpdate->save();

            return response()->json(['message' => 'User role updated successfully.', 'user' => $userToUpdate]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating user role', 'error' => $e->getMessage()], 500);
        }
    }
    public function deleteUser($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }
            $user->delete();
            return response()->json(['message' => 'User deleted successfully.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting user', 'error' => $e->getMessage()], 500);
        }
    }
    public function searchUser(Request $request)
    {
        try {
            $request->validate([
                'query' => 'required|string|min:1',
            ]);
            $query = $request->input('query');
            $users = User::where('full_name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->orderBy('id', 'DESC')
                ->get();
            $formattedUsers = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'user_name' => $user->user_name,
                    'email' => $user->email,
                    'image' => $user->image ? url('profile/',$user->image) : url('avatar/profile.png'),
                    'role' => $user->role,
                    'status' => $user->status,
                ];
            });
            return $this->sendResponse($formattedUsers, 'User search results retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Error searching users', ['error' => $e->getMessage()], 500);
        }
    }
    public function userList()
    {
        try {
            $users = User::where('status','active')
            ->whereIn('role',['ADMIN','MEMBER'])
            ->orderBy('id', 'DESC')->paginate(10);
            $formattedUsers = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'user_name' => $user->user_name,
                    'email' => $user->email,
                    'image' => $user->image ? url('profile/',$user->image) : url('avatar/profile.png'),
                    'role' => $user->role,
                    'status' => $user->status,
                ];
            });
            return $this->sendResponse($formattedUsers, 'User list retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Error retrieving user list', ['error' => $e->getMessage()], 500);
        }
    }
    public function userDetails(Request $request)
    {

        $userId = $request->user_id;
        $user = User::find($userId);
        if(!$user)
        {
            $this->sendError("No user found.");
        }
        $shop = Shop::where('user_id', $userId)->first();
        if (!$shop) {
            return $this->sendError('Shop not found for the user.', [], 404);
        }
        $products = Product::where('shop_id', $shop->id)->get();
        if ($products->isEmpty()) {
            return $this->sendError('No products found for the user.', [], 404);
        }
        $productIds = $products->pluck('id')->toArray();
        // Get counts
        $approvedProduct = $products->where('status','approved')->count();
        $pendingProduct = $products->where('status','pending')->count();
        $canceledProduct = $products->where('status','canceled')->count();

        $saleOrders = Order::whereIn('product_id', $productIds)
                            ->where('status', 'acceptDelivery')
                            ->count();
        $pendingOrders = Order::whereIn('product_id', $productIds)
                            ->where('status', 'pending')
                            ->count();
        // Initialize activity arrays
        $dailySalesActivity = [];
        $dailyPurchasesActivity = [];
        $weeklySalesActivity = [];
        $weeklyPurchasesActivity = [];
        $monthlySalesActivity = [];
        $monthlyPurchasesActivity = [];
        // Daily Activity Loop
        for ($i = 0; $i < 30; $i++) {
            $date = now()->subDays($i)->toDateString();
            $salesCount = Order::whereIn('product_id', $productIds)
                                ->where('status', 'acceptDelivery')
                                ->whereDate('created_at', $date)
                                ->count();
            $purchasesCount = Order::whereIn('product_id', $productIds)
                                    ->where('status', 'pending')
                                    ->whereDate('created_at', $date)
                                    ->count();

            $dailySalesActivity[] = ['date' => $date, 'count' => $salesCount];
            $dailyPurchasesActivity[] = ['date' => $date, 'count' => $purchasesCount];
        }
        // Weekly Activity Loop
        for ($i = 0; $i < 4; $i++) {
            $startOfWeek = now()->subWeeks($i)->startOfWeek()->toDateString();
            $endOfWeek = now()->subWeeks($i)->endOfWeek()->toDateString();

            $weeklySalesCount = Order::whereIn('product_id', $productIds)
                                    ->where('status', 'acceptDelivery')
                                    ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                                    ->count();
            $weeklyPurchasesCount = Order::whereIn('product_id', $productIds)
                                        ->where('status', 'pending')
                                        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                                        ->count();

            $weeklySalesActivity[] = ['week' => "Week " . ($i + 1), 'count' => $weeklySalesCount];
            $weeklyPurchasesActivity[] = ['week' => "Week " . ($i + 1), 'count' => $weeklyPurchasesCount];
        }
        // Monthly Activity Loop
        for ($i = 0; $i < 12; $i++) {
            $month = now()->subMonths($i)->format('Y-m');
            $monthlySalesCount = Order::whereIn('product_id', $productIds)
                                    ->where('status', 'acceptDelivery')
                                    ->whereMonth('created_at', now()->subMonths($i)->month)
                                    ->whereYear('created_at', now()->subMonths($i)->year)
                                    ->count();
            $monthlyPurchasesCount = Order::whereIn('product_id', $productIds)
                                        ->where('status', 'pending')
                                        ->whereMonth('created_at', now()->subMonths($i)->month)
                                        ->whereYear('created_at', now()->subMonths($i)->year)
                                        ->count();

            $monthlySalesActivity[] = ['month' => $month, 'count' => $monthlySalesCount];
            $monthlyPurchasesActivity[] = ['month' => $month, 'count' => $monthlyPurchasesCount];
        }
        // Prepare Response Data
        $response = [
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'email' => $user->email,
                'balance' => $user->balance,
                'image' => $user->image ? url('profile/', $user->image) : url('avatar/profile.png'),
            ],
            'shop' => [
                'name' => $shop->shop_name,
                'seller_name' => $shop->user->full_name,
                'user_name' => $shop->user->user_name,
                'image' => $shop->user->image ? url('profile/', $shop->user->image) : url('avatar/profile.png'),
            ],
            'counts' => [
                'approvedProduct' => $approvedProduct,
                'pendingProduct' => $pendingProduct,
                'canceledProduct' => $canceledProduct,
                'saleOrders' => $saleOrders,
                'pendingOrders' => $pendingOrders,
            ],
            'activities' => [
                'dailySales' => $dailySalesActivity,
                'dailyPurchases' => $dailyPurchasesActivity,
                'weeklySales' => $weeklySalesActivity,
                'weeklyPurchases' => $weeklyPurchasesActivity,
                'monthlySales' => $monthlySalesActivity,
                'monthlyPurchases' => $monthlyPurchasesActivity,
            ],
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_category' => $product->category->category_name,
                    'price' => $product->price,
                    'product_status' => $product->status,
                    'description' => $product->description,
                    'product_images' => collect(json_decode($product->images))->map(function ($image) {
                        return $image ? url("products/", $image) : url('avatar/product.png');
                    }),
                ];
            }),
        ];
        return $this->sendResponse($response, 'User products retrieved successfully.');
    }
}
