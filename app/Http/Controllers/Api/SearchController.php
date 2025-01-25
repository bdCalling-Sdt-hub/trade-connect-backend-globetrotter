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
    private function post(string $query)
    {
        $newsfeeds = NewsFeed::where('share_your_thoughts', 'like', '%' . $query . '%')
            ->orWhereHas('user', function ($user) use ($query) {
                $user->where('country', $query)
                    ->orWhere('city', $query)
                    ->orWhere('state', $query)
                    ->orWhere('zip_code', $query)
                    ->orWhere('full_name', $query)
                    ->orWhere('user_name', $query)
                    ->orWhere('email', $query);
            })->get();

        $user = Auth()->user();
        return $newsfeeds->map(function ($newsFeed) use ($user) {
            $userData = $newsFeed->user;
            $decodedImages = json_decode($newsFeed->images);

            return [
                'newsfeed_id'   => $newsFeed->id,
                'user'          => [
                    'user_id'   => $userData->id,
                    'full_name' => $userData->full_name,
                    'user_name' => $userData->user_name,
                    'country' => $userData->country,
                    'city' => $userData->city,
                    'state' => $userData->state,
                    'zip_code' => $userData->zip_code,
                    'image' => $userData->image ? url('profile/', $userData->image) : url('avatar/profile.png'),
                ],
                'content'         => $newsFeed->share_your_thoughts,
                'image_count'     => count($decodedImages),
                'images'          => collect($decodedImages)->map(fn($image) => [
                                        'url' => $image ? url('NewsFeedImages/', $image) : '',
                                    ])->toArray(),
                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'like_count'      => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id', $user->id),
                'created_at'      => $newsFeed->created_at->format('Y-m-d H:i:s'),
                'comments'        => $newsFeed->comments->map(function ($comment) {
                    return [
                        'id'          => $comment->id,
                        'user_id'     => $comment->user_id,
                        'full_name'   => $comment->user->full_name,
                        'user_name'   => $comment->user->user_name,
                        'image'       => $comment->user->image ? url('Profile/', $comment->user->image) : '',
                        'comment'     => $comment->comments,
                        'created_at'  => $this->getTimePassed($comment->created_at),
                        'reply_count' => $comment->replies->count(),
                        'replies'     => $comment->replies->map(function ($reply) {
                            return [
                                'id'        => $reply->id,
                                'user_id'   => $reply->user_id,
                                'full_name' => $reply->user->full_name,
                                'user_name' => $reply->user->user_name,
                                'image'     => $reply->user->image ? url('Profile/', $reply->user->image) : '',
                                'comment'   => $reply->comments,
                                'created_at'=> $this->getTimePassed($reply->created_at),
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ];
        })->toArray();
    }
    private function product(string $query)
    {
        $authUserId = Auth::user()->id;
        $products = Product::where('status', 'approved')
                            ->where('product_name', 'like', '%' . $query . '%')
                            ->orWhereHas('user', function ($user) use ($query) {
                                $user->where('country', $query)
                                    ->orWhere('city', $query)
                                    ->orWhere('state', $query)
                                    ->orWhere('zip_code', $query)
                                    ->orWhere('full_name', $query)
                                    ->orWhere('user_name', $query)
                                    ->orWhere('email', $query);
                            })
                            ->whereHas('user', function ($query) use ($authUserId) {
                                $query->where(function ($query) use ($authUserId) {
                                    $query->where('privacy', 'public') // Include public products
                                        ->orWhere(function ($query) use ($authUserId) {
                                            $query->where('privacy', 'friends') // Include friends-only products
                                                ->whereHas('friends', function ($friendQuery) use ($authUserId) {
                                                    $friendQuery->where('friend_id', $authUserId)
                                                        ->where('is_accepted', true); // Check accepted friendship
                                                });
                                        });
                                });
                            })
                            ->orderBy('id','desc')
                            ->paginate(20);

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
                'product_images' => collect(json_decode($product->images))->map(function ($image) {
                    return $image ? url("products/", $image) : url('avatar/product.png');
                }),
                'shop' => [
                    'shop_name' => $product->shop->shop_name,
                    'seller' => [
                        'seller_name' => $product->shop->user->full_name,
                        'user_name' => $product->shop->user->user_name,
                        'email' => $product->shop->user->email,
                        'country' => $product->shop->user->country,
                        'city' => $product->shop->user->city,
                        'state' => $product->shop->user->state,
                        'zip_code' => $product->shop->user->zip_code,
                        'image' => $product->shop->user->image ? url('profile/', $product->shop->user->image) : url('avatar/profile.png'),
                    ],
                ],
            ];
        })->toArray();
    }
    private function people(string $query)
    {
        $users = User::where('user_name', 'like', '%' . $query . '%')
                ->orWhere('full_name','like','%'.$query. '%')
                ->orWhere('country','like','%'.$query. '%')
                ->orWhere('city','like','%'.$query. '%')
                ->orWhere('state','like','%'.$query. '%')
                ->orWhere('zip_code','like','%'.$query. '%')
                ->get();

        return $users->map(function ($user) {
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
