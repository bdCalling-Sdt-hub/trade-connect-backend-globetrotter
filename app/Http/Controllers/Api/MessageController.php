<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MessageController extends Controller
{
    public function getMessage()
    {
        $userId = auth()->id();
        try {
            $messages = Message::where(function ($query) use ($userId) {
                    $query->where('sender_id', $userId)
                        ->orWhere('receiver_id', $userId);
                })
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'desc')
                ->get();
            if ($messages->isEmpty()) {
                return $this->sendError('No messages found.');
            }
            $formattedMessages = $messages->map(function ($message) {
                $decodedImages = json_decode($message->images, true);
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                   'images' => is_array($decodedImages) ? array_map(function ($image) {
                                        return url('message/' . $image);
                                    }, $decodedImages) : [],
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    'sender' => $this->formatUser($message->sender),
                    'receiver' => $this->formatUser($message->receiver),
                ];
            });
            return $this->sendResponse($formattedMessages, 'Messages retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error occurred while retrieving messages: ' . $e->getMessage());
        }
    }

    private function formatUser($user)
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'user_name' => $user->user_name,
            'email' => $user->email,
            'image' => $user->image ? url('profile/' . $user->image) : '',
        ];
    }
    public function store(Request $request)
    {
        $validator =Validator::make($request->all(),[
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
            'image' => 'nullable|image|max:2048',
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
}

