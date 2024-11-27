<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermAndCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TermAndConditioncontroller extends Controller
{
    public function index(Request $request)
    {
        $termAndCondition = TermAndCondition::where('status',1)->first();
        if (!$termAndCondition) {
            $data = "No Term and Conditions Found.";
            return $this->sendResponse($data,"No Term and Condition Found.");
        }
        return $this->sendResponse($termAndCondition, 'Term and conditions retrieved successfully.');
    }
    public function termAndConditions(Request $request)
    {
        $terms = TermAndCondition::first();
        if (!$terms) {
            $validator = Validator::make($request->all(), [
                'content' => 'required|string',
                'status' => 'boolean',
            ]);
            if ($validator->fails()) {
                return $this->sendError('Validation error.', $validator->errors(), 400);
            }
           $data = TermAndCondition::create([
                'content' => $request->content,
                'status' => $request->status ?? $terms->status,
            ]);
            return $this->sendResponse($data, 'Terms and conditions created successfully.');
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

