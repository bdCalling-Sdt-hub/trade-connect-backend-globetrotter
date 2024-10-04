<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsFeed;
use App\Models\User;
use App\Notifications\NewsFeedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;


class NewsFeedController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $friendIds = $user->friends()->pluck('friends.friend_id')->toArray();

        $newsFeeds = NewsFeed::with(['user', 'likes', 'comments.replies', 'comments.user'])
        ->where(function ($query) use ($user, $friendIds) {
            $query->where('privacy', 'public')  // Public posts are visible to all
                ->orWhere(function ($subQuery) use ($friendIds) {
                    $subQuery->where('privacy', 'friends')
                             ->whereIn('user_id', $friendIds);  // Friends' posts are visible to friends only
                })
                ->orWhere(function ($subQuery) use ($user) {
                    $subQuery->where('privacy', 'private')
                             ->where('user_id', $user->id);  // Private posts are visible only to the owner
                });
        })
        ->orderBy('id', 'DESC')
        ->paginate($request->get('per_page', 20));

        $formattedNewsFeeds = $newsFeeds->getCollection()->transform(function ($newsFeed) use ($user) {
            $userData = $newsFeed->user;
            $decodedImages = json_decode($newsFeed->images);
            return [
                'id'              => $newsFeed->id,
                'user'            => [
                    'id'        => $userData->id,
                    'full_name' => $userData->full_name,
                    'user_name' => $userData->user_name,
                    'image'     => url('Profile/',$userData->image),
                ],
                'content'         => $newsFeed->share_your_thoughts,
                'image_count'     => count($decodedImages),
                'images'          => collect(json_decode($newsFeed->images))->map(function ($image) {
                                        return [
                                        'url' => url('NewsFeedImages/', $image),
                                        ];
                                    })->toArray(),

                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'like_count'      => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id', $user->id),
                'comments'        => $newsFeed->comments->transform(function ($comment) {
                    return [
                        'id'          => $comment->id,
                        'user_id'     => $comment->user_id,
                        'full_name'   => $comment->user->full_name,
                        'user_name'   => $comment->user->user_name,
                        'image'       =>url('Profile/',$comment->user->image),
                        'comment'     => $comment->comments,
                        'created_at'  =>$this->getTimePassed($comment->created_at),
                        'reply_count' => $comment->replies->count(),
                        'replies'     => $comment->replies->transform(function ($reply){
                            return [
                                'id'        => $reply->id,
                                'user_id'   => $reply->user_id,
                                'full_name' => $reply->user->full_name,
                                'user_name' => $reply->user->user_name,
                                'image'     => url('Profile/',$reply->user->image),
                                'comment'   => $reply->comments,
                                'created_at'=> $this->getTimePassed($reply->created_at),
                            ];
                        }),
                    ];
                }),
            ];
        });
        return $this->sendResponse([
            'newsfeeds'       => $formattedNewsFeeds,
            'current_page'    => $newsFeeds->currentPage(),
            'total_pages'     => $newsFeeds->lastPage(),
            'total_newsfeeds' => $newsFeeds->total(),
        ], 'Successfully retrieved news feed with likes, comments, and replies.');
    }
    protected function getTimePassed($createdAt)
    {
        $now = Carbon::now();
        $diffInMinutes = $now->diffInMinutes($createdAt);

        if ($diffInMinutes == 0) {
            return 'right now'; // If the comment was created less than a minute ago
        } elseif ($diffInMinutes < 60) {
            return $diffInMinutes . ' minutes ago';
        } elseif ($diffInMinutes < 1440) { // 1440 minutes = 24 hours
            $diffInHours = $now->diffInHours($createdAt);
            return $diffInHours . ' hours ago';
        } else {
            return 'old'; // More than 24 hours
        }
    }
    public function store(Request $request)
    {
    $validator = Validator::make($request->all(), [
        'user_id'  => 'required',
        'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'privacy'  => 'required|in:public,private',
        'share_your_thoughts' => 'required|string',
    ]);

    if ($validator->fails()) {
        return $this->sendError('Validation Error', $validator->errors(), 422);
    }
    $imagePaths = [];
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $image) {
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('NewsFeedImages'), $filename);
            $imagePaths[] =$filename;
        }
    }
    $newsFeed = NewsFeed::create([
        'user_id' => Auth::id(),
        'share_your_thoughts' => $request->share_your_thoughts,
        'images' => json_encode($imagePaths),
        'privacy' => $request->privacy,
        'status' => $request->status,
    ]);
    $user = User::find($newsFeed->user_id);
    $user->notify(new NewsFeedNotification($newsFeed));
    return $this->sendResponse($newsFeed, 'Successfully created news feed.');
}
    public function update(Request $request, $id)
    {
        $newsFeed = NewsFeed::find($id);
        if (!$newsFeed || $newsFeed->user_id !== Auth::id()) {
            return $this->sendError('News feed not found or unauthorized', [], 404);
        }
        $validator = Validator::make($request->all(), [
            'share_your_thoughts' => 'nullable|string',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'privacy' => 'nullable|in:public,private',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        if ($request->hasFile('images')) {
            $existingImages = $newsFeed->images ? json_decode($newsFeed->images, true) : [];
            foreach ($existingImages as $existingImage) {
                $path = public_path('NewsFeedImages/' . $existingImage);
                if (File::exists($path)) {
                    File::delete($path);
                }
            }
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $filename = time() . '_' . $image->getClientOriginalName();
                $path = $image->move(public_path('storage/NewsFeedImages'), $filename);
                $imagePaths[] =$filename;
            }
            $newsFeed->images = json_encode($imagePaths);
        }
        $newsFeed->share_your_thoughts = $request->share_your_thoughts ?? $newsFeed->share_your_thoughts;
        $newsFeed->privacy = $request->privacy ?? $newsFeed->privacy;
        $newsFeed->save();

        $user = User::find($newsFeed->user_id);
        $user->notify(new NewsFeedNotification($newsFeed));
        return $this->sendResponse($newsFeed, 'News feed updated successfully.');
    }
    public function destroy($id)
    {
        $newsFeed = NewsFeed::find($id);

        if (!$newsFeed || $newsFeed->user_id !== Auth::id()) {
            return $this->sendError('News feed not found or unauthorized', [], 404);
        }
        if ($newsFeed->images) {
            $existingImages = json_decode($newsFeed->images, true);

            foreach ($existingImages as $existingImage) {
                if (Storage::disk('public')->exists($existingImage)) {
                    Storage::disk('public')->delete($existingImage);
                }
            }
        }
        $newsFeed->delete();

        return $this->sendResponse([], 'News feed deleted successfully.');
    }
    public function count(){
        $userId = Auth::id();
        $count = NewsFeed::where('user_id', $userId)->count();
        if(!$count){
            return $this->sendError([],"No NewsFeed Found.");
        }
        return $this->sendResponse($count, 'Newsfeeds count retrieved successfully.');
    }

    public function usernewsfeeds()
    {
        $userId = Auth::id();
        $newsfeeds = NewsFeed::where('user_id', $userId)->get();

        return $this->sendResponse($newsfeeds, 'User newsfeeds retrieved successfully.');
    }
}
