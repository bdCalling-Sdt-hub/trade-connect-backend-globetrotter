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
            return [
                'id' => $message->id,
                'message' => $message->message,
                'images' => $message->images,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at,
                'sender' => [
                    'id' => $message->sender->id,
                    'full_name' => $message->sender->full_name,
                    'user_name' => $message->sender->user_name,
                    'email' => $message->sender->email,
                    'image'=> url('profile/',$message->sender->image),

                ],
                'receiver' => [
                    'id' => $message->receiver->id,
                    'full_name' => $message->receiver->full_name,
                    'user_name' => $message->receiver->user_name,
                    'email' => $message->receiver->email,
                    'image'=> url('profile/',$message->receiver->image),
                ],
            ];
        });

        return $this->sendResponse($formattedMessages, 'Messages retrieved successfully.');
    }



    public function store(Request $request)
    {
        // return $request;
        // $validator =Validator::make($request->all(),[
        //     'sender_id' => 'required|exists:users,id',
        //     'receiver_id' => 'required|exists:users,id',
        //     'message' => 'required|string',
        //     'image' => 'nullable|image|max:2048',
        // ]);
        // if($validator->fails()){
        //     return $this->sendError("Validation Error",$validator->errors(),422);
        // }

        try {
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $filename = time() . '_' . $image->getClientOriginalName();
                    $path = $image->move(public_path('storage/Message'), $filename);
                    $imagePaths[] =$filename;
                }
            }
            $message = Message::create([
                'sender_id' => $request->sender_id,
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

