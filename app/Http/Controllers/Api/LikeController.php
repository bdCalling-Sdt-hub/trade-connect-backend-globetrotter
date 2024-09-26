<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
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
                            ->where('user_id', Auth::id())
                            ->first();

        if ($existingLike) {
            $existingLike->delete();
            return $this->sendResponse([], "Like successfully removed.");
        } else {
            $like = Like::create(attributes: [
                'newsfeed_id' => $request->newsfeed_id,
                'user_id' => Auth::id(),
            ]);
            $user = User::find($like->user_id);
            $user->notify(new LikeNotification($like));
            return $this->sendResponse($like, "Like successfully added.");
        }

    }

}




