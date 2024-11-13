<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\NewsFeed;
use App\Models\User;
use App\Notifications\LikeNotification;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LikeController extends Controller
{
    public function likeNewsfeed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'newsfeed_id' => 'required|exists:news_feeds,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        $existingLike = Like::where('newsfeed_id', $request->newsfeed_id)
                            ->where('user_id', auth()->user()->id)
                            ->first();

        if ($existingLike) {
            $existingLike->delete();
            return $this->sendResponse([], "Like successfully removed.");
        } else {
            $like = Like::create(attributes: [
                'newsfeed_id' => $request->newsfeed_id,
                'user_id' => auth()->user()->id,
            ]);
           $newsfeed = NewsFeed::where('id', $request->newsfeed_id)
                    //  ->where('user_id', $request->user_id)
                     ->first();
            if (!$newsfeed) {
                return $this->sendError("No newsfeed found.");
            }
            $user = User::find($newsfeed->user_id);
            $user->notify(new LikeNotification($like));
            return $this->sendResponse($like, "Like successfully added.");
        }
    }
    public function getNewsfeedLikes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'newsfeed_id' => 'required|exists:news_feeds,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        $authUserId = Auth::id();
        $newsfeedsLikes = NewsFeed::withCount('likes')
            ->with(['likes' => function($query) use ($authUserId) {
                $query->where('user_id', $authUserId);
            }])
            ->where('id', $request->newsfeed_id)
            ->get()
            ->map(function ($newsfeed) use ($authUserId) {
                $authUserLiked = $newsfeed->likes->contains('user_id', $authUserId);
                return [
                    'newsfeed_id' => $newsfeed->id,
                    'like_count' => $newsfeed->likes_count,
                    'auth_user_liked' => $authUserLiked,
                ];
            });
        if ($newsfeedsLikes->isEmpty()) {
            return $this->sendError("No newsfeed likes found.");
        }
        return $this->sendResponse($newsfeedsLikes, 'Likes for all newsfeeds retrieved successfully.');
    }
}




