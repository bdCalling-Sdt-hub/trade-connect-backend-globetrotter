<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MessageController extends Controller
{
    public function getMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        $authUserId = Auth::id();
        $receiverId = $request->receiver_id;
        $messages = Message::with(['sender', 'receiver'])
            ->where(function ($query) use ($authUserId, $receiverId) {
                $query->where('sender_id', $authUserId)
                    ->where('receiver_id', $receiverId);
            })
            ->orWhere(function ($query) use ($authUserId, $receiverId) {
                $query->where('sender_id', $receiverId)
                    ->where('receiver_id', $authUserId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page);
        if ($messages->isEmpty()) {
            return $this->sendError([], 'No messages found.');
        }
        $formattedMessages = $messages->map(function ($message) {
            $imageCollection = collect(json_decode($message->images, true) ?? []);
            return [
                'id' => $message->id,
                'message' => $message->message,
                'images' => $imageCollection->map(function ($image) {
                    return $image ? url("message/", $image) :null;
                }),
                'is_read' => $message->is_read,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                'sender' => [
                    'id' => $message->sender->id,
                    'full_name' => $message->sender->full_name,
                    'email' => $message->sender->email,
                    'image' => $message->sender->image ? url('Profile/' . $message->sender->image) : url('avatar/profile.png'),
                ],
            ];
        });
        return $this->sendResponse($formattedMessages, 'Messages retrieved successfully.');
    }
    private function formatUser($user)
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'user_name' => $user->user_name,
            'email' => $user->email,
            'image' => $user->image ? url('profile/' . $user->image) : url('avatar/profile.png'),
        ];
    }
    public function store(Request $request)
    {
        $validator =Validator::make($request->all(),[
            'receiver_id' => 'required|exists:users,id',
            'images'=>'nullable|array|max:9',
            'images*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
        ]);
        if($validator->fails()){
            return $this->sendError("Validation Error",$validator->errors(),422);
        }
        try {
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $filename = time() . '_' . $image->getClientOriginalName();
                    $path = $image->move(public_path('message/'), $filename);
                    $imagePaths[] =$filename;
                }
            }
            $message = Message::create([
                'sender_id' => auth()->user()->id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message,
                'images' => json_encode($imagePaths),
                'is_read'=>$request->is_read
            ]);

            return $this->sendResponse($message, 'Message sent successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error sending message.', $e->getMessage(), 500);
        }
    }
    public function view($id)
    {
        try {
            $message = Message::with(['sender', 'receiver'])->findOrFail($id);
            if (!$message->is_read) {
                $message->is_read = true;
                $message->save();
            }
            return $this->sendResponse($message, 'Message was already read.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving message.', $e->getMessage(), 500);
        }
    }
public function destroy($id)
    {
        try {
            $message = Message::findOrFail($id);
            if ($message->image) {
                $imagePath = public_path('message/' . $message->image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $message->delete();
            return $this->sendResponse([], 'Message deleted successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->sendError('Message not found.', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Error deleting message.', $e->getMessage(), 500);
        }
    }
    public function userChat()
    {
        $userId = Auth::id();
        $members = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->select('sender_id', 'receiver_id')
            ->distinct()
            ->get()
            ->flatMap(function ($message) use ($userId) {
                return [$message->sender_id === $userId ? $message->receiver_id : $message->sender_id];
            })
            ->unique();
        $userMembers = User::whereIn('id', $members)->get();
        if ($userMembers->isEmpty()) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'User not found.'
            ]);
        }
        $formattedMembers = $userMembers->map(function ($user) use ($userId) {
            $lastMessage = Message::where(function ($query) use ($userId, $user) {
                    $query->where('sender_id', $userId)->where('receiver_id', $user->id);
                })
                ->orWhere(function ($query) use ($userId, $user) {
                    $query->where('sender_id', $user->id)->where('receiver_id', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->first();
            $unreadCount = Message::where(function ($query) use ($userId, $user) {
                    $query->where('sender_id', $user->id)->where('receiver_id', $userId);
                })
                ->where('is_read', false)
                ->count();
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'location' => $user->location,
                'email' => $user->email,
                'image' => $user->image ? url('profile/', $user->image) : url('avatar/', 'profile.png'),
                'last_message' => $lastMessage ? $lastMessage->message : null,
                'last_message_time' => $lastMessage ? $lastMessage->created_at : null,
                'unread_count' => $unreadCount,
            ];
        });
        return $this->sendResponse($formattedMembers, 'Chat members retrieved successfully.');
    }
}

