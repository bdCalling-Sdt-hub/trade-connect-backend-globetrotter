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
        try {
            $categories = Category::orderBy('id', 'DESC')->paginate(10);
            $formattedCategories = $categories->getCollection()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                ];
            });
            $paginatedData = $categories->setCollection($formattedCategories);
            return $this->sendResponse($paginatedData, 'Categories retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving categories', ['error' => $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|unique:categories,category_name|max:255',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }
        try {
            $category = Category::create([
                'category_name' => $request->category_name,
            ]);
            return $this->sendResponse($category, 'Category created successfully.', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating category', ['error' => $e->getMessage()], 500);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return $this->sendError('Category not found', [], 404);
            }
            $validator = Validator::make($request->all(), [
                'category_name' => 'required|string|unique:categories,category_name,' . $category->id . '|max:255',
                'status' => 'boolean',
            ]);
            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors(), 400);
            }
            $category->update([
                'category_name' => $request->category_name,
                'status' => $request->status ?? $category->status,
            ]);

            return $this->sendResponse($category, 'Category updated successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Error updating category', ['error' => $e->getMessage()], 500);
        }
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
