<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friend;
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
        $newsFeedsQuery = NewsFeed::with(['user', 'likes', 'comments', 'comments.replies'])
        ->where(function($query) use ($user) {
            $query->where('privacy', 'public')
                  ->orWhere(function($query) use ($user) {
                      $query->where('privacy', 'friends')
                            ->whereHas('user', function ($query) use ($user) {
                                $query->whereIn('users.id', $user->userFriends()->pluck('friend_id')->toArray());
                            });
                  });
        })
        ->orderBy('id', 'DESC');
    $newsFeeds = $newsFeedsQuery->get();
    $newsFeeds = $newsFeedsQuery->paginate($request->get('per_page', 20));
        $formattedNewsFeeds = $newsFeeds->getCollection()->transform(function ($newsFeed) use ($user) {
            $userData = $newsFeed->user;
            $decodedImages = json_decode($newsFeed->images);
            return [
                'id'   => $newsFeed->id,
                'user'          => [
                    'user_id'   => $userData->id,
                    'full_name' => $userData->full_name,
                    'privacy'   => $userData->privacy,
                    'user_name' => $userData->user_name,
                    'image'     => $userData->image ? url('profile/',$userData->image) : url('avatar/profile.png'),
                ],
                'content'         => $newsFeed->share_your_thoughts,
                'privacy'         => $newsFeed->privacy,
                'image_count'     => count($decodedImages),
                'images'          => collect(json_decode($newsFeed->images))->map(function ($image) {
                                        return [
                                        'url' => $image ?  url('NewsFeedImages/', $image) : '',
                                        ];
                                    })->toArray(),
                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'like_count'      => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id', $user->id),
                'created_at' => $newsFeed->created_at->format('Y-m-d H:i:s'),
                'comment_count'        => $newsFeed->comments->count(),
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
        $diff = $now->diff($createdAt);

        if ($diff->y > 0) {
            return $diff->y === 1 ? '1 year ago' : "{$diff->y} years ago";
        } elseif ($diff->m > 0) {
            return $diff->m === 1 ? '1 month ago' : "{$diff->m} months ago";
        } elseif ($diff->d >= 7) { // Check for weeks
            $weeks = floor($diff->d / 7); // Calculate number of weeks
            return $weeks === 1 ? '1 week ago' : "{$weeks} weeks ago";
        } elseif ($diff->d > 0) {
            return $diff->d === 1 ? '1 day ago' : "{$diff->d} days ago";
        } elseif ($diff->h > 0) {
            return $diff->h === 1 ? '1 hour ago' : "{$diff->h} hours ago";
        } elseif ($diff->i > 0) {
            return $diff->i === 1 ? '1 minute ago' : "{$diff->i} minutes ago";
        } else {
            return 'right now'; // If the comment was created less than a minute ago
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'privacy'  => 'required|in:public,private,friends',
            'share_your_thoughts' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $filename = time() . '_' . $image->getClientOriginalExtension();
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
        return $this->sendResponse($newsFeed, 'Successfully created newsfeed.');
    }
    public function update(Request $request, $id)
    {
        $newsFeed = NewsFeed::find($id);
        if (!$newsFeed || $newsFeed->user_id !== Auth::id()) {
            return $this->sendError('News feed not found or unauthorized', [], 404);
        }
        $validator = Validator::make($request->all(), [
            'share_your_thoughts' => 'nullable|string',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'privacy' => 'nullable|in:public,private,friends',
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
                $filename = time() . '_' . $image->getClientOriginalExtension();
                $path = $image->move(public_path('NewsFeedImages'), $filename);
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
        if(!$newsfeeds){
            return $this->sendError([],'No newsfeed found.');
        }
        return $this->sendResponse($newsfeeds, 'User newsfeeds retrieved successfully.');
    }
}
