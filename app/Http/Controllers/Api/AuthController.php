<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Mail\SendOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
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
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8|max:60',
            'c_password' => 'required|same:password',
            'role'       => ['required', Rule::in(['USER', 'ADMIN'])],
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
        $otp = rand(100000, 999999);
        $input = $request->except('c_password');
        $input['password'] = Hash::make($input['password']);
        $input['otp'] = $otp;
        $input['otp_expires_at'] = now()->addMinutes(10);
        $input['verify_email'] = 0;

        return User::create($input);
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
            'name' => $user->first_name . ' ' . $user->last_name,
            'otp' => $otp,
        ];

        Mail::to($user->email)->queue(new OtpVerificationMail($emailData));
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
            $random = rand(100000, 999999);
            Mail::to($request->email)->send(new SendOtp($random));
            $user->update(['otp' => $random]);
            $user->update(['verify_email' => 0]);
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
        if (!$user->verify_email == 0) {
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
    public function user(){
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status'=> 400,'message'=> 'You are not Authenticated'] , 400);
        }else{
            $users = User::all();
            return response()->json(['status'=> 200,'users'=> $users], 200);
        }
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

        $otp = rand(100000, 999999);
        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        Mail::to($user->email)->send(new SendOtp($user));

        return response()->json(['status' => 200, 'message' => 'OTP has been resent successfully.'], 200);
    }

    public function profile(Request $request)
    {
        $user = Auth::user();
        if(!$user){
            return $this->sendError([],"You are not user.");
        }
        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
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
        $user->image = $fileName;
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
        $authUser = auth()->user();
        $userToUpdate = User::find($id);

        if (!$userToUpdate) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        $userToUpdate->role = $request->role;
        $userToUpdate->save();

        return response()->json(['message' => 'User role updated successfully.', 'user' => $userToUpdate]);
    }

    public function deleteUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully.']);
    }


}
