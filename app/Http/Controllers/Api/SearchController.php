<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsFeed;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query','');
        $filters = [
            'country' => $request->input('country',''),
            'city' => $request->input('city',''),
            'state' => $request->input('state',''),
            'zip_code' => $request->input('zip_code',''),
        ];
        $results = [
            'posts' => $this->post($query,$filters),
            'products' => $this->product($query),
            'people' => $this->people($query),
        ];
        return $this->sendResponse($results, 'Search results retrieved successfully.');
    }
    private function post($query, array $filters = [])
    {
        $newsfeeds = NewsFeed::with(['user', 'likes', 'comments.replies', 'comments.user', 'likes'])
            ->where('share_your_thoughts', 'like', '%' . $query . '%');
        if (!empty($filters['country'])) {
            $newsfeeds->whereHas('user', fn($user) => $user->where('country', $filters['country']));
        }
        if (!empty($filters['city'])) {
            $newsfeeds->whereHas('user', fn($user) => $user->where('city', $filters['city']));
        }
        if (!empty($filters['state'])) {
            $newsfeeds->whereHas('user', fn($user) => $user->where('state', $filters['state']));
        }
        if (!empty($filters['zip_code'])) {
            $newsfeeds->whereHas('user', fn($user) => $user->where('zip_code', $filters['zip_code']));
        }
        $user = auth()->user();
        return $newsfeeds->get()->map(function ($newsFeed) use ($user) {
            $userData = $newsFeed->user;
            $decodedImages = json_decode($newsFeed->images, true);

            return [
                'newsfeed_id' => $newsFeed->id,
                'user' => [
                    'user_id' => $userData->id,
                    'full_name' => $userData->full_name,
                    'user_name' => $userData->user_name,
                    'country' => $userData->country,
                    'city' => $userData->city,
                    'state' => $userData->state,
                    'zip_code' => $userData->zip_code,
                    'image' => $userData->image ? url('profile/', $userData->image) : url('avatar/profile.png'),
                ],
                'content' => $newsFeed->share_your_thoughts,
                'image_count' => count($decodedImages),
                'images' => collect($decodedImages)->map(fn($image) => [
                    'url' => $image ? url('NewsFeedImages/', $image) : '',
                ])->toArray(),
                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'like_count' => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id', $user->id),
                'created_at' => $newsFeed->created_at->format('Y-m-d H:i:s'),
                'comments' => $newsFeed->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'user_id' => $comment->user_id,
                        'full_name' => $comment->user->full_name,
                        'user_name' => $comment->user->user_name,
                        'image' => $comment->user->image ? url('Profile/', $comment->user->image) : '',
                        'comment' => $comment->comments,
                        'created_at' => $this->getTimePassed($comment->created_at),
                        'reply_count' => $comment->replies->count(),
                        'replies' => $comment->replies->map(function ($reply) {
                            return [
                                'id' => $reply->id,
                                'user_id' => $reply->user_id,
                                'full_name' => $reply->user->full_name,
                                'user_name' => $reply->user->user_name,
                                'image' => $reply->user->image ? url('Profile/', $reply->user->image) : '',
                                'comment' => $reply->comments,
                                'created_at' => $this->getTimePassed($reply->created_at),
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ];
        })->toArray();
    }
    private function product( $query, array $filters = [])
    {
        $authUserId = Auth::id();
        $products = Product::where('status', 'approved')
            ->where('product_name', 'like', '%' . $query . '%')
            ->whereHas('user', function ($query) use ($authUserId) {
                $query->where(function ($privacyQuery) use ($authUserId) {
                    $privacyQuery->where('privacy', 'public')
                        ->orWhere(function ($friendsQuery) use ($authUserId) {
                            $friendsQuery->where('privacy', 'friends')
                                ->whereHas('friends', function ($friendQuery) use ($authUserId) {
                                    $friendQuery->where('friend_id', $authUserId)
                                        ->where('is_accepted', true);
                                });
                        });
                });
            });
        if (!empty($filters['country'])) {
            $products->whereHas('user', fn($userQuery) => $userQuery->where('country', $filters['country']));
        }
        if (!empty($filters['city'])) {
            $products->whereHas('user', fn($userQuery) => $userQuery->where('city', $filters['city']));
        }
        if (!empty($filters['state'])) {
            $products->whereHas('user', fn($userQuery) => $userQuery->where('state', $filters['state']));
        }
        if (!empty($filters['zip_code'])) {
            $products->whereHas('user', fn($userQuery) => $userQuery->where('zip_code', $filters['zip_code']));
        }
        $products = $products->orderBy('id', 'desc')->paginate(20);
        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'user_id' => $product->user_id,
                'full_name' => $product->user->full_name,
                'user_name' => $product->user->user_name,
                'image' => $product->user->image ? url('profile/', $product->user->image) : url('avatar/profile.png'),
                'product_name' => $product->product_name,
                'category_name' => $product->category->category_name ?? 'N/A',
                'product_code' => $product->product_code,
                'price' => $product->price,
                'description' => $product->description ?? 'N/A',
                'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                'product_images' => collect(json_decode($product->images))->map(fn($image) => $image ? url("products/", $image) : url('avatar/product.png')),
                'shop' => [
                    'shop_name' => $product->shop->shop_name ?? 'N/A',
                    'seller' => [
                        'seller_name' => $product->shop->user->full_name ?? 'N/A',
                        'user_name' => $product->shop->user->user_name ?? 'N/A',
                        'email' => $product->shop->user->email ?? 'N/A',
                        'country' => $product->shop->user->country ?? 'N/A',
                        'city' => $product->shop->user->city ?? 'N/A',
                        'state' => $product->shop->user->state ?? 'N/A',
                        'zip_code' => $product->shop->user->zip_code ?? 'N/A',
                        'image' => $product->shop->user->image ? url('profile/', $product->shop->user->image) : url('avatar/profile.png'),
                    ],
                ],
            ];
        })->toArray();
    }
    private function people($query, array $filters = [])
    {
        $users = User::where('role','MEMBER')->where(function ($userQuery) use ($query) {
            $userQuery->where('user_name', 'like', '%' . $query . '%')
                ->orWhere('full_name', 'like', '%' . $query . '%');
        });
        if (!empty($filters['country'])) {
            $users->where('country', $filters['country']);
        }
        if (!empty($filters['city'])) {
            $users->where('city', $filters['city']);
        }
        if (!empty($filters['state'])) {
            $users->where('state', $filters['state']);
        }
        if (!empty($filters['zip_code'])) {
            $users->where('zip_code', $filters['zip_code']);
        }
        return $users->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'email' => $user->email,
                'country' => $user->country,
                'city' => $user->city,
                'state' => $user->state,
                'zip_code' => $user->zip_code,
                'image' => $user->image ? url('profile/', $user->image) : url('avatar/profile.png'),
            ];
        })->toArray();
    }
    protected function getTimePassed($createdAt)
    {
        $now = Carbon::now();
        $diff = $now->diff($createdAt);

        if ($diff->y > 0) {
            return $diff->y === 1 ? '1 year ago' : "{$diff->y} years ago";
        } elseif ($diff->m > 0) {
            return $diff->m === 1 ? '1 month ago' : "{$diff->m} months ago";
        } elseif ($diff->d >= 7) {
            $weeks = floor($diff->d / 7);
            return $weeks === 1 ? '1 week ago' : "{$weeks} weeks ago";
        } elseif ($diff->d > 0) {
            return $diff->d === 1 ? '1 day ago' : "{$diff->d} days ago";
        } elseif ($diff->h > 0) {
            return $diff->h === 1 ? '1 hour ago' : "{$diff->h} hours ago";
        } elseif ($diff->i > 0) {
            return $diff->i === 1 ? '1 minute ago' : "{$diff->i} minutes ago";
        } else {
            return 'right now';
        }
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
