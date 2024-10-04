<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Models\User;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function sendRequest($friend_id)
    {
        $user_id = auth()->user()->id;
        if (Friend::where('user_id', $user_id)->where('friend_id', $friend_id)->exists()) {
            return response()->json(['message' => 'Friend request already sent'], 400);
        }
        Friend::create([
            'user_id' => $user_id,
            'friend_id' => $friend_id,
        ]);
        return response()->json(['message' => 'Friend request sent successfully']);
    }
    public function acceptRequest($friend_id)
    {
        $user_id = auth()->user()->id;
        $friendRequest = Friend::where('user_id', $friend_id)
            ->where('friend_id', $user_id)
            ->where('is_accepted',false)
            ->first();
        if (!$friendRequest) {
            return response()->json(['message' => 'No friend request found'], 404);
        }
        $friendRequest->is_accepted = true;
        $friendRequest->save();
        return response()->json(['message' => 'Friend request accepted']);
    }
    public function unfriend($friend_id)
    {
        $user_id = auth()->user()->id;
        $friendship = Friend::where(function ($query) use ($user_id, $friend_id) {
            $query->where('user_id', $user_id)->where('friend_id', $friend_id);
        })->orWhere(function ($query) use ($user_id, $friend_id) {
            $query->where('user_id', $friend_id)->where('friend_id', $user_id);
        })->first();
        if (!$friendship) {
            return response()->json(['message' => 'No friendship found'], 404);
        }
        $friendship->delete();
        return response()->json(['message' => 'Unfriended successfully']);
    }
    public function cancelRequest($friend_id)
    {
        $user_id = auth()->user()->id;
        $friendRequest = Friend::where('user_id', $user_id)
            ->where('friend_id', $friend_id)
            ->where('is_accepted', false)
            ->first();
        if (!$friendRequest) {
            return response()->json(['message' => 'No pending friend request found'], 404);
        }
        $friendRequest->delete();
        return response()->json(['message' => 'Friend request canceled']);
    }
    public function userFriendRequests(Request $request)
    {
        $user_id = auth()->user()->id;
        $perPage = $request->input('per_page', 20);
        $friendRequests = Friend::where('friend_id', $user_id)
                                ->where('is_accepted', false)
                                ->with('user')
                                ->orderBy('id', 'DESC')
                                ->paginate($perPage)
                                ->through(function ($friendRequest) {
                                    return [
                                        'id'          => $friendRequest->id,
                                        'full_name'   => $friendRequest->user->full_name,
                                        'user_name'   => $friendRequest->user->user_name,
                                        'is_accepted' => $friendRequest->is_accepted,
                                        'image'       => $friendRequest->user->image
                                    ];
                                });
        $totalRequestsCount = Friend::where('friend_id', $user_id)
                                    ->where('is_accepted', false)
                                    ->count();
        return response()->json([
            'total_requests' => $totalRequestsCount,
            'friend_requests' => $friendRequests
        ]);
    }
    public function userFriends(Request $request)
    {
        $user_id = auth()->user()->id;
        $perPage = $request->input('per_page', 20);

        $friends = Friend::where(function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                      ->orWhere('friend_id', $user_id);
            })
            ->where('is_accepted', true)
            ->with(['user', 'friend'])
            ->orderBy('id', 'DESC')
            ->paginate($perPage)
            ->through(function ($friend) use ($user_id) {
                $friendData = $friend->user_id == $user_id ? $friend->friend : $friend->user;

                return [
                    'id'          => $friend->id,
                    'full_name'   => $friendData->full_name,
                    'user_name'   => $friendData->user_name,
                    'image'       => $friendData->image
                ];
            });
        $totalFriendsCount = Friend::where(function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                      ->orWhere('friend_id', $user_id);
            })
            ->where('is_accepted', true)
            ->count();
            
        return response()->json([
            'total_friends' => $totalFriendsCount,
            'friends' => $friends
        ]);
    }


}
