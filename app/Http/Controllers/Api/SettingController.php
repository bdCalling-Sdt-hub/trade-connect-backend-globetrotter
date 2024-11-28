<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class SettingController extends Controller
{
    public function getPersonalInformation()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $profileData = [
            'full_name' => $user->full_name,
            'bio'=> $user->bio ,
            'location' => $user->location,
            'contact' => $user->contact,
            'image' => $user->image
                ? url('profile/',$user->image)
                : url('avatar/profile.png')
        ];

        return response()->json([
            'status' => true,
            'message' => 'Personal Information fetched successfully.',
            'data' => $profileData,
        ], 200);
    }
   public function personalInformation(Request $request)
    {
        $userId = auth()->user()->id;
        $user = User::find($userId);
        if ($user->role !== 'ADMIN') {
            return $this->sendError('User is not an admin.', [], 403);
        }
        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|min:8|same:new_password',
            'contact' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        if ($request->has('full_name')) {
            $user->full_name = $request->full_name ?? $user->full_name;
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
            $image->move(public_path('profile/'), $fileName);
            $user->image = $fileName;
        }
        if ($request->filled('old_password') && $request->filled('new_password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                return $this->sendError('Old password is incorrect.', [], 400);
            }
            $user->password = Hash::make($request->new_password);
        }
        $user->contact =$request->contact ?? $user->contact;
        $user->save();
        $user->makeHidden(['password', 'remember_token']);

        return $this->sendResponse($user, "Profile successfully updated.");
    }

}
