<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LikeController extends Controller
{
    public function likeNewsfeed(Request $request)
    {
        $newsfeedId = $request->input('newsfeed_id');
        $userId = $request->input('user_id');

        if (is_null($newsfeedId) || is_null($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'newsfeed_id and user_id are required'
            ], 400);
        }

        // Store like in the database
        try {
            $like = Like::firstOrCreate([
                'newsfeed_id' => $newsfeedId,
                'user_id' => $userId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to like the newsfeed: ' . $e->getMessage()
            ], 500);
        }

        // Trigger the Socket.IO event
        $client = new Client();
        try {
            $response = $client->post('http://192.168.10.14:3000/trigger-like', [
                'json' => [
                    'newsfeed_id' => $newsfeedId,
                    'user_id' => $userId
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Newsfeed liked successfully',
                'response' => json_decode($response->getBody()->getContents(), true)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function unlikeNewsfeed(Request $request)
    {
        $newsfeedId = $request->input('newsfeed_id');
        $userId = $request->input('user_id');

        if (is_null($newsfeedId) || is_null($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'newsfeed_id and user_id are required'
            ], 400);
        }

        // Remove like from the database
        try {
            $like = Like::where('newsfeed_id', $newsfeedId)
                ->where('user_id', $userId)
                ->first();

            if ($like) {
                $like->delete();
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Like not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to unlike the newsfeed: ' . $e->getMessage()
            ], 500);
        }

        // Trigger the Socket.IO event
        $client = new Client();
        try {
            $response = $client->post('http://192.168.10.14:3000/trigger-unlike', [
                'json' => [
                    'newsfeed_id' => $newsfeedId,
                    'user_id' => $userId
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Newsfeed unliked successfully',
                'response' => json_decode($response->getBody()->getContents(), true)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}


        // $validator = Validator::make($request->all(), [
        //     'newsfeed_id' => 'required|exists:news_feeds,id',
        // ]);

        // if ($validator->fails()) {
        //     return $this->sendError('Validation Error', $validator->errors(), 422);
        // }
        // $existingLike = Like::where('newsfeed_id', $request->newsfeed_id)
        //                     ->where('user_id', Auth::id())
        //                     ->first();

        // if ($existingLike) {
        //     $existingLike->delete();
        //     return $this->sendResponse([], "Like successfully removed.");
        // } else {
        //     $like = Like::create([
        //         'newsfeed_id' => $request->newsfeed_id,
        //         'user_id' => Auth::id(),
        //     ]);
        //     return $this->sendResponse($like, "Like successfully added.");
        // }


