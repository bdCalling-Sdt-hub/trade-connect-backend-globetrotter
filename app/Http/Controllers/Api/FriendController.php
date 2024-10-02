<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Models\User;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function sendFriendRequest(Request $request)
    {
        $user = auth()->user();

        $friend = User::find($request->friend_id);

        if (!$friend) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $existingRequest = Friend::where('user_id', $user->id)
            ->where('friend_id', $friend->id)
            ->first();

        if ($existingRequest) {
            return response()->json(['message' => 'Friend request already sent or you are already friends'], 400);
        }
        $user->friendRequestsSent()->create([
            'friend_id' => $friend->id,
            'is_accepted' => false,
        ]);

        return response()->json(['message' => 'Friend request sent'], 200);
    }
    public function acceptFriendRequest($friend_id)
    {
        $user = auth()->user();
        $friendRequest = Friend::where('friend_id', $friend_id)
            ->where('user_id', $user->id)
            ->where('is_accepted', false)
            ->first();
        if (!$friendRequest) {
            return response()->json(['message' => 'Friend request not found or already accepted.'], 404);
        }
        $friendRequest->update(['is_accepted' => true]);
        return response()->json(['message' => 'Friend request accepted'], 200);
    }
    public function cancelFriendRequest($friend_id)
    {
        return $friend_id;
        $user = auth()->user();
        $friendRequest = Friend::where('user_id', $user->id)
            ->where('friend_id', $friend_id)
            ->where('is_accepted', false)
            ->first();

        if (!$friendRequest) {
            return response()->json(['message' => 'Friend request not found'], 404);
        }
        $friendRequest->delete();
        return response()->json(['message' => 'Friend request canceled'], 200);
    }

    public function unfriend($friend_id)
    {
        $user = auth()->user();
        $friendship = Friend::where(function ($query) use ($user, $friend_id) {
            $query->where('user_id', $user->id)
                ->where('friend_id', $friend_id);
        })->orWhere(function ($query) use ($user, $friend_id) {
            $query->where('user_id', $friend_id)
                ->where('friend_id', $user->id);
        })->where('is_accepted', true)->first();

        if (!$friendship) {
            return response()->json(['message' => 'Friendship not found'], 404);
        }
        $friendship->delete();
        return response()->json(['message' => 'User unriended'], 200);
    }
}
