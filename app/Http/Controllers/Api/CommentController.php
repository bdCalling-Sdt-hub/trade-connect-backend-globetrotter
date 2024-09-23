<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
                'newsfeed_id' => 'required|exists:news_feeds,id',
                'user_id' => 'required|exists:users,id',
                'comments' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:comments,id',

        ]);
        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        try {
            $comment = Comment::create([
                'newsfeed_id' => $request->newsfeed_id,
                'user_id' => $request->user_id,
                'comments' => $request->comments,
                'parent_id' => $request->parent_id,
            ]);

            // Load user and replies for the response
            $comment->load('user', 'replies');

            return $this->sendResponse($comment, 'Comment added successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error adding comment.', $e->getMessage(), 500);
        }

    }
    public function commentView($newsfeedId)
    {
        $comments = Comment::with('user', 'replies.user')
        ->where('newsfeed_id', $newsfeedId)
        ->whereNull('parent_id') // Get only parent comments
        ->get();

    return $this->sendResponse($comments, 'Comments retrieved successfully.');
    }
    public function destroy($id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        }

        try {
            $comment->delete();
            return $this->sendResponse([], 'Comment deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting comment.', $e->getMessage(), 500);
        }
    }
}
