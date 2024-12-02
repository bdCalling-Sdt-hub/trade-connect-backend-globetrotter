<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\NewsFeed;
use App\Models\User;
use App\Notifications\CommentNotification;
use App\Notifications\CommentReplyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function getComment($newsfeedId)
    {
        $newsfeed = NewsFeed::find($newsfeedId);
        if (!$newsfeed) {
            return $this->sendError('Newsfeed not found.', [], 404);
        }
        $comments = Comment::where('newsfeed_id', $newsfeedId)
                            ->with(['user', 'replies.user'])
                            ->orderBy('created_at', 'asc')
                            ->get();
        if ($comments->isEmpty()) {
            return $this->sendError([], 'No comments found for this newsfeed.');
        }
        $formattedComments = $comments->map(function ($comment) {
            return [
                'id' => $comment->id,
                'user_id' => $comment->user->id,
                'full_name' => $comment->user->full_name,
                'email' => $comment->user->email,
                'image' => $comment->user->image ? url('profile/' . $comment->user->image) : url('avatar/profile.png'),
                'comment' => $comment->comments,
                'created_at' => $comment->created_at->toDateTimeString(),
                'replies' => $comment->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'user_id' => $reply->user->id,
                        'full_name' => $reply->user->full_name,
                        'email' => $reply->user->email,
                        'image' => $reply->user->image ? url('profile/' . $reply->user->image) : url('avatar/profile.png'),
                        'comment' => $reply->comments,
                        'created_at' => $reply->created_at->toDateTimeString(),
                    ];
                }),
            ];
        });
        return $this->sendResponse($formattedComments, 'Comments retrieved successfully.');
    }
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
