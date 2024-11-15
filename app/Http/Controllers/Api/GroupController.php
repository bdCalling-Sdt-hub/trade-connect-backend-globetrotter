<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\JoinRequest;
use App\Models\User;
use App\Notifications\JoinRequestAcceptedNotification;
use App\Notifications\JoinRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Notification;

class GroupController extends Controller
{
    public function acceptJoinRequest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id'
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $joinRequest = JoinRequest::find($id);
        if (!$joinRequest) {
            return $this->sendError('Join request not found.', [], 404);
        }
        if ($joinRequest->group_id !== $request->group_id) {
            return $this->sendError('Unauthorized action.', [], 403);
        }
        return $this->sendResponse([], 'Join request accepted successfully.');
    }
    public function joinGroupRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $userId = Auth::id();
        $groupId = $request->group_id;

        if (JoinRequest::where('user_id', $userId)->where('group_id', $groupId)->exists()) {
            return $this->sendError('You have already requested to join this group.', [], 400);
        }
    }
    public function otherGroup(Request $request)
    {
        $userId = auth()->user()->id;
        $groups = Group::where('created_by','!=',$userId)->orderBy('id','desc')->get();
        if ($groups->isEmpty()) {
            return $this->sendError('No other groups found.', [], 404);
        }
        $formattedGroups = $groups->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'image' => $group->image ? url('Groups/' . $group->image) : url('avatar/group.png'),
                'created_by' => [
                    'id' => $group->createdBy->id,
                    'full_name' => $group->createdBy->full_name,
                    'user_name' => $group->createdBy->user_name,
                    'email' => $group->createdBy->email,
                    'image' => $group->createdBy->image ? url('profile/' . $group->createdBy->image) : url('avatar/profile.png'),
                ],
                'member_count' => $group->members->count(),
            ];
        });
        return $this->sendResponse($formattedGroups, 'Other groups retrieved successfully.');
    }

    public function yourGroup(Request $request)
    {
        $userId = auth()->user()->id;
        $groups = Group::where('created_by', $userId)
            ->with(['createdBy', 'members'])
            ->orderBy('id','desc')
            ->get()
            ->map(function ($group) {
                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'group_members' => $group->members()->count(),
                    'group_image' => $group->image ? url('Groups/' . $group->image) : url('avatar/group.png'),
                    'group_creator' => [
                        'id' => $group->createdBy->id ?? null,
                        'full_name' => $group->createdBy->full_name ?? 'N/A',
                        'user_name' => $group->createdBy->user_name ?? 'N/A',
                        'email' => $group->createdBy->email ?? 'N/A',
                        'image' => $group->createdBy->image ? url('profile/',$group->createdBy->image) : url('avatar/profile.png'),
                    ],
                ];
            });
        if ($groups->isEmpty()) {
            return $this->sendError('No groups found for the user.', [], 404);
        }
        return $this->sendResponse($groups, 'Groups retrieved successfully.');
    }
    public function groupSearch(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
        ]);
        $keyword = $request->keyword;
        $groups = Group::where('name', 'LIKE', '%' . $keyword . '%')->get();
        if ($groups->isEmpty()) {
            return $this->sendResponse([], 'No groups found matching your search criteria.');
        }
        return $this->sendResponse($groups, 'Groups retrieved successfully.');
    }
    public function peopleSearch(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
        ]);
        $user = Auth::user();
        $users = User::where('id', '!=', $user->id)
                    ->where('role', 'MEMBER')
                    ->where('full_name', 'like', '%' . $request->keyword . '%')
                    ->orWhere('email', 'like', '%' . $request->keyword . '%')
                    ->get()
                    ->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'full_name' => $member->full_name,
                            'user_name' => $member->user_name,
                            'image' => $member->image ? url('profile/' . $member->image) : url('avatar/profile.png'),
                        ];
                    });
        return $this->sendResponse($users, 'users get successfully.');
    }
    public function peoples()
    {
        $user = Auth::user();
        $members = User::where('id', '!=', $user->id)
                       ->where('role', 'MEMBER')
                       ->get()
                       ->map(function ($member) {
                           return [
                               'id' => $member->id,
                               'full_name' => $member->full_name,
                               'user_name' => $member->user_name,
                               'image' => $member->image ? url('profile/' . $member->image) : url('avatar/profile.png'),
                           ];
                       });
        return $this->sendResponse($members, 'Successfully retrieved members.');
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        try {
            $group = new Group();
            $group->name = $request->name;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('Groups'), $fileName);
                $group->image = $fileName;
            }
            $group->created_by = auth()->user()->id;
            $group->status = 1;
            $group->save();

            $group->members()->attach($request->members);
            return $this->sendResponse($group, 'Group created successfully with members.');
        } catch (\Exception $e) {
            return $this->sendError('Error creating group', $e->getMessage(), 500);
        }
    }
    public function update(Request $request)
    {
        $group = Group::find($request->groupId);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
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
        }
        $group->name = $request->name ?? $group->name;
        $group->image = $fileName ?? $group->image;
        $group->status = 1 ?? $group->status;
        $group->save();
        return $this->sendResponse($group, 'Group updated successfully.');
    }
    public function destroy(Request $request)
    {
        $group = Group::find($request->groupId);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }
        if ($group->created_by !== auth()->id()) {
            return $this->sendError('You are not eligible to delete the group.', [], 403);
        }
        if ($group->image) {
            $imagePath = public_path('groups/' . $group->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        $group->groupMembers()->delete();
        $group->messages()->delete();
        $group->delete();
        return $this->sendResponse([], 'Group deleted successfully.');
    }
    public function addMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids.*' => 'required|exists:users,id',
            'user_ids'=>'array',
            'group_id' => 'required|exists:groups,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $group = Group::find($request->group_id);
        if (!$group) {
            return $this->sendError('Group not found.', [], 404);
        }
        $userIds = $request->user_ids;

        $group->members()->syncWithoutDetaching($userIds);
        return $this->sendResponse($group->load('members'), 'Members added to group successfully.', 201);
    }
    public function removeMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $groupId = $request->group_id;
        $userId = $request->user_id;
        $group = Group::find($groupId);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }
        $isMember = $group->members()->where('user_id', $userId)->exists();
        if (!$isMember) {
            return $this->sendError('User is not a member of this group', [], 400);
        }
        $group->members()->detach($userId);
        return $this->sendResponse([], 'Member removed from group successfully.');
    }
    public function leaveGroup(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $groupId = $request->group_id;
        $isMember = GroupMember::where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->exists();
        if (!$isMember) {
            return $this->sendError('You are not a member of this group.', 404);
        }
        GroupMember::where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->delete();
        return $this->sendResponse([], 'Successfully left the group.');
    }
    public function groupMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $group = Group::with('members')->find($request->group_id);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }
        $membersData = $group->members->map(function ($member) {
            return [
                'user_id' => $member->id,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'image' => $member->image ? url('profile/', $member->image) : url('avatar/profile.png'),
            ];
        });
        $responseData = [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'members' => $membersData,
        ];
        return $this->sendResponse($responseData, 'Group members retrieved successfully.');
    }
    public function sendGroupMessage(Request $request)
    {
        $userId = auth()->user()->id;
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'message' => 'required|string',
            'images' => 'nullable|array|max:9',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        $imagePaths = [];
        if ($request->has('images')) {
            foreach ($request->images as $image) {
                $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('GropupMessages/'), $fileName);
                $imagePaths[] = $fileName;
            }
        }
        $message = new GroupMessage();
        $message->group_id = $request->group_id;
        $message->sender_id = $userId;
        $message->message = $request->message;
        $message->images = json_encode($imagePaths);
        $message->is_read = false;
        $message->read_by = json_encode([]);
        $message->save();

        return $this->sendResponse($message, 'Message sent successfully.');
    }
    public function getMessages(Request $request)
    {
        $userId = auth()->user()->id;
        $messages = GroupMessage::where('group_id', $request->groupId)
            ->orderBy('created_at', 'desc')
            ->get();
        $messagesData = $messages->map(function ($message) use ($userId) {
            $images = json_decode($message->images, true);
            if ($images) {
                $images = array_map(function ($image) {
                    return url('GropupMessages/' . $image);
                }, $images);
            }
            $isReadByUser = in_array($userId, json_decode($message->read_by ?? '[]'));
            return [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'images' => $images,
                'is_read' => $message->is_read,
                'is_read_by_user' => $isReadByUser,
                'read_by' => json_decode($message->read_by, true),
                'created_at' => $message->created_at,
            ];
        });
        $unreadCount = GroupMessage::where('group_id', $request->groupId)
            ->whereRaw('JSON_CONTAINS(read_by, ?)', [json_encode([$userId])])
            ->where('is_read', false)
            ->count();
        return $this->sendResponse([
            'messages' => $messagesData,
            'unread_count' => $unreadCount,
        ], 'Messages fetched successfully.');
    }
    public function markMessageAsRead(Request $request)
    {
        $userId = auth()->user()->id;
        $message = GroupMessage::find($request->messageId);
        if (!$message) {
            return $this->sendError('Message not found', [], 404);
        }
        $readBy = json_decode($message->read_by, true) ?? [];
        if (!in_array($userId, $readBy)) {
            $readBy[] = $userId;
            $message->read_by = json_encode($readBy);
            if ($message->is_read === false) {
                $message->is_read = true;
            }
            $message->save();
        }
        return $this->sendResponse([], 'Message marked as read successfully.');
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
