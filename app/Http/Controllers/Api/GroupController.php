<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::where('status', 1)->orderBy('id', 'DESC')->get();
        return $this->sendResponse($groups, 'Groups retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('Groups'), $fileName);
        }

        $group = new Group();
        $group->name = $request->name;
        $group->created_by = auth()->user()->id;
        $group->image = $fileName;
        $group->status = $request->status;
        $group->save();
        return $this->sendResponse($group, 'Group created successfully.');
    }

    public function show($id)
    {
        $group = Group::find($id);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }
        return $this->sendResponse($group, 'Group retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        if ($request->hasFile('image')) {
            if ($group->image) {
                $oldImagePath = public_path('Groups/' . $group->image);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            $image = $request->file('image');
            $fileName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('Groups'), $fileName);
            $group->image = $fileName;
        }
        $group->name = $request->input('name', $group->name);
        $group->created_by = auth()->user()->id;
        $group->status = $request->input('status', $group->status);
        $group->save();

        return $this->sendResponse($group, 'Group updated successfully.');
    }

    public function destroy($id)
    {
        $group = Group::find($id);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }
        if ($group->image) {
            $imagePath = public_path('groups/' . $group->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        $group->delete();

        return $this->sendResponse([], 'Group deleted successfully.');
    }

    public function addMembers(Request $request, Group $group)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        $userIds = $request->user_ids;
        $existingMembers = $group->members()->whereIn('user_id', $userIds)->pluck('user_id')->toArray();

        if (count($existingMembers) === count($userIds)) {
            return $this->sendError('All selected users are already members of this group.', [], 400);
        }

        $newMembers = array_diff($userIds, $existingMembers);
        $group->members()->attach($newMembers);

        return $this->sendResponse($group->load('members'), 'Members added to group successfully.', 201);
    }

    public function removeMember($groupId, $userId)
    {
        $group = Group::find($groupId);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }
        $isMember = $group->members()->where('user_id', $userId)->exists();
        if (!$isMember) {
            return $this->sendError('User is not a member of this group', [], 400);
        }
        $group->members()->detach($userId);

        return $this->sendResponse($group->load('members'), 'Member removed from group successfully.');
    }
    public function groupMembers($groupId)
    {
        $group = Group::find($groupId);

        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }
        return $this->sendResponse($group->load('members'), 'Group members retrieved successfully.');
    }

    public function groupMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'message' => 'required|string|max:500',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $filePath = time().'.'.$image->getClientOriginalExtension();
                $image->move(public_path('GroupMessage'),$filePath);
                $images[] = $filePath;

            }
        }
        $groupMessage = GroupMessage::create([
            'group_id' => $request->group_id,
            'sender_id' => auth()->id(),
            'message' => $request->message,
            'images' => json_encode($images),
        ]);

        return $this->sendResponse($groupMessage->load('sender'), 'Message stored successfully.', 201);
    }

    public function getMessages($groupId)
    {
        $validator = Validator::make(['group_id' => $groupId], [
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $messages = Group::find($groupId)->messages()->with('sender')->get();

        return $this->sendResponse($messages, 'Messages retrieved successfully.');
    }

    public function markAsRead($messageId)
    {
        $message = GroupMessage::find($messageId);

        if (!$message) {
            return $this->sendError('Message not found', [], 404);
        }

        $message->is_read = !$message->is_read;
        $message->save();

        return $this->sendResponse($message, 'Message read status updated successfully.');
    }
    public function deleteMessage($messageId)
    {
        $message = GroupMessage::find($messageId);

        if (!$message) {
            return $this->sendError('Message not found', [], 404);
        }
        if ($message->images) {
            $imagePaths = json_decode($message->images, true);

            foreach ($imagePaths as $imagePath) {
                $fileName = basename($imagePath);
                Storage::delete('GroupMessage/' . $fileName);
            }
        }
        $message->delete();

        return $this->sendResponse(null, 'Message and associated images deleted successfully.');
    }
}
