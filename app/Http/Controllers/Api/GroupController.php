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

        // Find the group and attach the user as a member
        // $group = Group::find($joinRequest->group_id);
        // $group->members()->attach($joinRequest->user_id);

        // $user = User::find($joinRequest->user_id);
        // $user->notify(new JoinRequestAcceptedNotification($group->name, $user->name));
        // $joinRequest->delete();

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
        // $joinRequest = JoinRequest::create([
        //     'user_id' => $userId,
        //     'group_id' => $groupId,
        // ]);
        // $groupOwner = Group::find($groupId);
        // $OnwerEmail = $groupOwner->createdBy->email;

        // $OnwerEmail->notify( new JoinRequestNotification($joinRequest));

        // return $this->sendResponse([], 'Your request to join the group has been submitted successfully.');
    }
    public function otherGroup(Request $request)
    {
        $userId = auth()->user()->id;
        $groups = Group::whereDoesntHave('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with(['createdBy', 'members'])
          ->get();
        if ($groups->isEmpty()) {
            return $this->sendError('No other groups found.', [], 404);
        }
        $groups->transform(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'image' => $group->image ? url('Groups/' . $group->image) : '',
                'created_by' => [
                    'id' => $group->createdBy->id,
                    'full_name' => $group->createdBy->full_name,
                    'user_name' => $group->createdBy->user_name,
                    'email' => $group->createdBy->email,
                    'image' => $group->createdBy->image ? url('Profile/' . $group->createdBy->image) : '',
                ],
                'member_count' => $group->members->count(),
            ];
        });
        return $this->sendResponse($groups, 'Other groups retrieved successfully.');
    }

    public function yourGroup(Request $request)
    {
        $userId = auth()->user()->id;
        $groups = Group::where('created_by', $userId)
            ->with(['createdBy', 'members'])
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
                    ->get();
        return $this->sendResponse($users, 'users get successfully.');
    }
    public function index()
    {
        $groups = Group::where('status', 1)->orderBy('id', 'DESC')->get();
        return $this->sendResponse($groups, 'Groups retrieved successfully.');
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
                               'image' => $member->image ? url('profile/' . $member->image) :'',
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
    public function update(Request $request, $id)
    {
        $group = Group::find($id);
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

    public function groupMessage(Request $request, $groupId)
    {
        $validator = Validator::make($request->all(), [
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
            'group_id' => $request->groupId,
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
