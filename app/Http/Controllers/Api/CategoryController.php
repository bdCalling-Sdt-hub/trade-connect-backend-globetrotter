<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        return $this->sendResponse($categories, 'Categories retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|unique:categories,category_name|max:255',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        $category = Category::create($request->all());
        return $this->sendResponse($category, 'Category created successfully.', 201);
    }

    public function show($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return $this->sendError('Category not found', [], 404);
        }
        return $this->sendResponse($category, 'Category retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        return $request;
        $category = Category::find($id);
        if (!$category) {
            return $this->sendError('Category not found', [], 404);
        }

        // $validator = Validator::make($request->all(), [
        //     'category_name' => 'required|string|unique:categories,category_name,' . $category->id . '|max:255',
        //     'status' => 'boolean',
        // ]);

        // if ($validator->fails()) {
        //     return $this->sendError('Validation Error', $validator->errors(), 400);
        // }

        $category->update($request->all());
        return $this->sendResponse($category, 'Category updated successfully.');
    }

    public function destroy($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return $this->sendError('Category not found', [], 404);
        }
        $category->delete();
        return $this->sendResponse([], 'Category deleted successfully.');
    }
}
