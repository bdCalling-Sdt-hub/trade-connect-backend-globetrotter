<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsFeed;
use App\Models\User;
use App\Notifications\NewsFeedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;


class NewsFeedController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $followingIds = $user->following()->pluck('users.id')->toArray();
        $newsFeeds = NewsFeed::where('privacy', 'public')
            ->orWhere('user_id', Auth::id())
            ->orWhereIn('user_id', $followingIds)
            ->get();

        return $this->sendResponse($newsFeeds, 'Successfully retrieved news feed.');
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
            $path = $image->move(public_path('storage/NewsFeedImages'), $filename);
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
                $path = public_path('storage/' . $existingImage);
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
