<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermAndCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TermAndConditioncontroller extends Controller
{
    public function update(Request $request, $id)
    {
        $terms = TermAndCondition::find($id);
        if (!$terms) {
            return $this->sendError('Terms and conditions not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 400);
        }

        $terms->update([
            'content' => $request->content,
            'status' => $request->status ?? $terms->status,
        ]);

        return $this->sendResponse($terms, 'Terms and conditions updated successfully.');
    }
}

