<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\CommentNotification;
use App\Notifications\CommentReplyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
                'newsfeed_id' => 'required|exists:news_feeds,id',
                'comments' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:comments,id',

        ]);
        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        try {
            $comment = Comment::create([
                'newsfeed_id' => $request->newsfeed_id,
                'user_id' => Auth::user()->id,
                'comments' => $request->comments,
                'parent_id' => $request->parent_id,
            ]);
            $comment->load('user', 'replies');
            if ($request->parent_id) {
                $parentComment = Comment::find($request->parent_id);
                $parentUser = User::find($parentComment->user_id);
                $parentUser->notify(new CommentReplyNotification($comment));
            }
            $newsFeedOwner = User::find($comment->user_id);
            $newsFeedOwner->notify(new CommentNotification($comment));
            return $this->sendResponse($comment, 'Comment added successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error adding comment.', $e->getMessage(), 500);
        }
    }
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comments' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        try {
            $comment = Comment::findOrFail($id);
            if ($comment->user_id !== Auth::id()) {
                return $this->sendError('Unauthorized', 'You are not allowed to edit this comment or reply.', 403);
            }
            if ($comment->parent_id) {
                $comment->update(['comments' => $request->comments]);
                return $this->sendResponse($comment, 'Reply comment updated successfully.');
            } else {
                $comment->update(['comments' => $request->comments]);
                return $this->sendResponse($comment, 'Comment updated successfully.');
            }

        } catch (\Exception $e) {
            return $this->sendError('Error updating comment or reply.', $e->getMessage(), 500);
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
            return $this->sendResponse([], 'Comment deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting comment.', $e->getMessage(), 500);
        }
    }
}
