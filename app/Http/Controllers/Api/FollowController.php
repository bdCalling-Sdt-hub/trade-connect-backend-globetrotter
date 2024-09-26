<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function follow(Request $request, $userId)
    {
        $userToFollow = User::find($userId);

        if (!$userToFollow) {
            return $this->sendError('User not found', [], 404);
        }

        auth()->user()->following()->attach($userToFollow->id);

        return $this->sendResponse(null, 'You are now following ' . $userToFollow->name);
    }

    public function unfollow(Request $request, $userId)
    {
        $userToUnfollow = User::find($userId);

        if (!$userToUnfollow) {
            return $this->sendError('User not found', [], 404);
        }

        // Remove follower
        auth()->user()->following()->detach($userToUnfollow->id);

        return $this->sendResponse(null, 'You have unfollowed ' . $userToUnfollow->name);
    }

    public function followersList(Request $request)
    {
        $followers = auth()->user()->followers->count();

        return $this->sendResponse($followers, 'Followers retrieved successfully.');
    }

    public function followingList(Request $request)
    {
        $following = auth()->user()->following->count();

        return $this->sendResponse($following, 'Following retrieved successfully.');
    }
}
