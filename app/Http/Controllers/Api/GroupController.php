<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;
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

    public function addMember(Request $request, Group $group) // Using route model binding
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        // Check if the user is already a member
        if ($group->members()->where('user_id', $request->user_id)->exists()) {
            return $this->sendError('User is already a member of this group.', [], 400);
        }

        // Attach the user to the group
        $group->members()->attach($request->user_id);

        return $this->sendResponse($group->load('members'), 'Member added to group successfully.', 201);
    }

    public function removeMember($groupId, $userId)
    {
        $group = Group::find($groupId);
        if (!$group) {
            return $this->sendError('Group not found', [], 404);
        }

        $group->members()->detach($userId);

        return $this->sendResponse($group->load('members'), 'Member removed from group successfully.');
    }
}
