<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'newsfeed_id' => 'required|exists:newsfeeds,id',
            'user_id' => 'required|exists:users,id',
            'comments' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        try {
            $comment = Comment::create([
                'newsfeed_id' => $request->input('newsfeed_id'),
                'user_id' => $request->input('user_id'),
                'comments' => $request->input('comments'),
                'parent_id' => $request->input('parent_id'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create comment: ' . $e->getMessage()
            ], 500);
        }

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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete comment: ' . $e->getMessage()
            ], 500);
        }
    }
}
