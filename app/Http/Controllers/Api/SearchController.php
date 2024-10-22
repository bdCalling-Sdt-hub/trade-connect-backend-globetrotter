<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsFeed;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $query = $request->input('query');

        $results = [
            'posts' => $this->post($query),
            'products' => $this->product($query),
            'people' => $this->people($query),
        ];

        return $this->sendResponse($results, 'Search results retrieved successfully.');
    }
    public function all(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);
        $query = $request->input('query');

        $posts = $this->post($query);
        $products = $this->product($query);
        $people = $this->people($query);

        return $this->sendResponse(compact('posts', 'products', 'people'), 'All items retrieved successfully.');
    }
    private function post(string $query)
    {
        return NewsFeed::where('share_your_thoughts', 'like', '%' . $query . '%')->get();
    }
    private function product(string $query)
    {
        return Product::where('product_name', 'like', '%' . $query . '%')->get();
    }
    private function people(string $query)
    {
        return User::where('user_name', 'like', '%' . $query . '%')->get();
    }
    public function newsfeed(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);
        $query = $request->input('query');

        $newsfeeds = NewsFeed::where('share_your_thoughts', 'like', '%' . $query . '%')
            ->orWhere('user_id', Auth::id())
            ->get();
        if(!$newsfeeds){
            return $this->sendError([],"No NewsFeed Found.");
        }

        return $this->sendResponse($newsfeeds, 'Successfully retrieved newsfeeds.');
    }
    public function products(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);
        $query = $request->input('query');

        $products = Product::where('product_name', 'like', '%' . $query . '%')
            ->orWhere('description', 'like', '%' . $query . '%')
            ->get();
        if(!$products){
             return $this->sendError([],"No products Found.");
        }
        return $this->sendResponse($products, 'Successfully retrieved products.');
    }
    public function peoples(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);
        $query = $request->input('query');

        $users = User::where('user_name', 'like', '%' . $query . '%')->get();
        if(!$users){
            return $this->sendError([],"No users Found.");
        }
        return $this->sendResponse($users, 'Successfully retrieved people.');
    }



}
