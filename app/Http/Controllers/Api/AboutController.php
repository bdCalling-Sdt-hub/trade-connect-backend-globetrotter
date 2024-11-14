<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\About;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AboutController extends Controller
{
    public function aboutUs(Request $request)
    {
        $about = About::where('status',1)->first();
        if (!$about) {
            return $this->sendError("No about found.");
        }
        return $this->sendResponse($about, 'About retrieved successfully.');
    }
    public function about(Request $request)
    {

        $terms = About::find(1);
        if (!$terms) {
            $validator = Validator::make($request->all(), [
                'about' => 'required|string',
            ]);
            if ($validator->fails()) {
                return $this->sendError('Validation error.', $validator->errors(), 400);
            }
           $about = About::create([
                'about' => $request->about,
            ]);
            return $this->sendResponse($about, 'About created successfully.');
        }
        $validator = Validator::make($request->all(), [
            'about' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 400);
        }
        $terms->update([
            'about' => $request->about,
        ]);
        return $this->sendResponse($terms, 'About updated successfully.');
    }
}
