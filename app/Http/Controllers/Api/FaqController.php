<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FAQ;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::where('status', true)->get();
        if(!$faqs){
            return $this->sendError('FAQ not found!', [], 404);
        }
        return $this->sendResponse($faqs, 'FAQs retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        $faq = Faq::create($request->all());
        return $this->sendResponse($faq, 'FAQ created successfully.');
    }

    public function show($id)
    {
        $faq = Faq::find($id);
        if (!$faq) {
            return $this->sendError('FAQ not found.', [], 404);
        }
        return $this->sendResponse($faq, 'FAQ retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $faq = Faq::find($id);
        if (!$faq) {
            return $this->sendError('FAQ not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'sometimes|required|string|max:255',
            'answer' => 'sometimes|required|string',
            'status' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        $faq->update($request->all());
        return $this->sendResponse($faq, 'FAQ updated successfully.');
    }

    public function destroy($id)
    {
        $faq = FAQ::find($id);
        if (!$faq) {
            return $this->sendError('FAQ not found.', [], 404);
        }

        $faq->delete();
        return $this->sendResponse([], 'FAQ deleted successfully.');
    }

}
