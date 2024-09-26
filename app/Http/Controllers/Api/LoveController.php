<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Love;
use Illuminate\Http\Request;

class LoveController extends Controller
{
    public function index()
    {
        $loves = Love::all();
        return $this->sendResponse($loves, 'Love entries retrieved successfully.');
    }
    public function store(Request $request)
    {
        $validator = $request->validate([
            'amount_of_love' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
        ]);
        $love = Love::create([
            'amount_of_love'=>$request->amount_of_love,
            'price'=>$request->price,

        ]);
        return $this->sendResponse($love, 'Love entry created successfully.');
    }
    public function show($id)
    {
        $love = Love::find($id);

        if (!$love) {
            return $this->sendError('Love entry not found.');
        }

        return $this->sendResponse($love, 'Love entry retrieved successfully.');
    }
    public function update(Request $request, $id)
    {
        $love = Love::find($id);

        if (!$love) {
            return $this->sendError('Love entry not found.');
        }

        $validator = $request->validate([
            'amount_of_love' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $love->update($request->all());

        return $this->sendResponse($love, 'Love entry updated successfully.');
    }
    public function destroy($id)
    {
        $love = Love::find($id);

        if (!$love) {
            return $this->sendError('Love entry not found.');
        }

        $love->delete();
        return $this->sendResponse([], 'Love entry deleted successfully.');
    }
}
