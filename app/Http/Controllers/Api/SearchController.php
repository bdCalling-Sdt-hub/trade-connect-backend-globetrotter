<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsFeed;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function search(Request $request)
    {

        $query = $request->input('query', '');
        $filters = [
            'shop_name' => $request->input('shop_name', ''),
            'country' => $request->input('country', ''),
            'city' => $request->input('city', ''),
            'state' => $request->input('state', ''),
            'zip_code' => $request->input('zip_code', ''),
        ];

        $searchType = $request->input('type', 'posts'); // Default to 'posts' if type is not provided
        switch ($searchType) {
            case 'posts':
                $results['posts'] = $this->post($query, $filters);
                break;

            case 'products':
                $results['products'] = $this->product($query, $filters);
                break;

            case 'people':
                $results['people'] = $this->people($query, $filters);
                break;

            case 'shops':
                $results['shops'] = $this->shops($query, $filters);
                break;

            default:
                // Handle invalid type
                return $this->sendResponse([], 'Invalid search type.', 400);
        }

        return $this->sendResponse($results, 'Search results retrieved successfully.');
    }
    private function shops($query, $filters = [])
    {
        $shops = Shop::with(['user'])
            ->where('shop_name', 'like', '%' . $query . '%');
        foreach (['shop_name','country', 'city', 'state', 'zip_code'] as $filterKey) {
            if (!empty($filters[$filterKey])) {
                $shops->where($filterKey, $filters[$filterKey]);
            }
        }
        $shops = $shops->paginate(20);

        return $shops->map(function ($shop) {
            return [
                'id' => $shop->id,
                'shop_name' => $shop->shop_name,
                'logo' => $shop->logo ? url('logos/', $shop->logo) : url('avatar/logo.png'),
                'seller' => $this->mapUser($shop->user),
                'product_count' => $shop->products->count(),
                'created_at' => $shop->created_at->format('Y-m-d H:i:s'),
            ];
        });
    }
    private function post($query, $filters = [])
    {
        $newsfeeds = NewsFeed::with([
            'user:id,full_name,user_name,country,city,state,zip_code,image',
            'likes',
            'comments.replies.user:id,full_name,user_name,image',
        ])->where('share_your_thoughts', 'like', '%' . $query . '%');
        foreach (['country', 'city', 'state', 'zip_code'] as $filterKey) {
            if (!empty($filters[$filterKey])) {
                $newsfeeds->whereHas('user', fn($user) => $user->where($filterKey, $filters[$filterKey]));
            }
        }
        $newsfeeds = $newsfeeds->paginate(20);
        $user = auth()->user();
        return $newsfeeds->map(function ($newsFeed) use ($user) {
            return [
                'newsfeed_id' => $newsFeed->id,
                'user' => $this->mapUser($newsFeed->user),
                'content' => $newsFeed->share_your_thoughts,
                'image_count' => count(json_decode($newsFeed->images, true)),
                'images' => collect(json_decode($newsFeed->images))->map(fn($image) => [
                    'url' => $image ? url('NewsFeedImages/', $image) : '',
                ]),
                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'like_count' => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id', $user->id),
                'created_at' => $newsFeed->created_at->format('Y-m-d H:i:s'),
                'comments' => $newsFeed->comments->map(fn($comment) => $this->mapComment($comment)),
            ];
        });
    }
    private function mapComment($comment)
    {
        return [
            'id' => $comment->id,
            'user' => $this->mapUser($comment->user),
            'comment' => $comment->comments,
            'created_at' => $this->getTimePassed($comment->created_at),
            'reply_count' => $comment->replies->count(),
            'replies' => $comment->replies->map(fn($reply) => [
                'id' => $reply->id,
                'user' => $this->mapUser($reply->user),
                'comment' => $reply->comments,
                'created_at' => $this->getTimePassed($reply->created_at),
            ]),
        ];
    }
    private function product($query, $filters = [])
    {
        $authUserId = Auth::id();
        $products = Product::with(['user:id,full_name,user_name,country,city,state,zip_code,image', 'shop.user', 'category'])
            ->where('status', 'approved')
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
        foreach (['country', 'city', 'state', 'zip_code'] as $filterKey) {
            if (!empty($filters[$filterKey])) {
                $products->whereHas('user', fn($user) => $user->where($filterKey, $filters[$filterKey]));
            }
        }
        return $products->orderBy('id', 'desc')->paginate(20)->map(fn($product) => $this->mapProduct($product));
    }
    private function mapProduct($product)
    {
        return [
            'id' => $product->id,
            'user' => $this->mapUser($product->user),
            'product_name' => $product->product_name,
            'category_name' => $product->category->category_name ?? 'N/A',
            'price' => $product->price,
            'description' => $product->description,
           'product_images' => collect(json_decode($product->images))->map(function ($image) {
                    return $image ? url("products/", $image) : url('avatar/product.png');
                }),
            'shop' => [
                'shop_name' => $product->shop->shop_name ?? 'N/A',
                'seller' => $this->mapUser($product->shop->user),
            ],
        ];
    }
    private function mapUser($user)
    {
        if (!$user) {
            return null;
        }
        return [
            'id' => $user->id,
            'full_name' => $user->full_name ?? 'N/A',
            'user_name' => $user->user_name ?? 'N/A',
            'email' => $user->email ?? 'N/A',
            'country' => $user->country ?? 'N/A',
            'city' => $user->city ?? 'N/A',
            'state' => $user->state ?? 'N/A',
            'zip_code' => $user->zip_code ?? 'N/A',
            'image' => $user->image ? url('profile/', $user->image) : url('avatar/profile.png'),
        ];
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
        // Get the search query and user_id from the request
        $query = $request->input('query');
        $userId = $request->input('user_id', Auth::id());

        // Start the query builder for NewsFeed
        $newsfeedsQuery = NewsFeed::query();

        // Apply the filter for the share_your_thoughts column if a query is provided
        if ($query) {
            $newsfeedsQuery->where('share_your_thoughts', 'like', '%' . $query . '%');
        }

        // Apply the filter for the user_id
        $newsfeedsQuery->where('user_id', $userId);

        // Fetch the paginated newsfeeds
        $newsfeeds = $newsfeedsQuery->paginate(10); // You can adjust the number of items per page as needed

        // If no newsfeeds are found, return an error
        if ($newsfeeds->isEmpty()) {
            return $this->sendError([], "No NewsFeed Found.");
        }

        // Format the newsfeeds with images and other necessary fields
        $formattedNewsfeeds = $newsfeeds->map(function ($newsfeed) {
            // Parse the images field (JSON array of image names) and return URLs
            $imageUrls = collect(json_decode($newsfeed->images))->map(function ($image) {
                return $image ? url('NewsFeedImages/' . $image) : ''; // Return empty if no image
            });

            return [
                'id' => $newsfeed->id,
                'user_id' => $newsfeed->user_id,
                'share_your_thoughts' => $newsfeed->share_your_thoughts,
                'images' => $imageUrls,  // Return parsed image URLs
                'privacy' => $newsfeed->privacy,
                'status' => $newsfeed->status,
                'created_at' => $newsfeed->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $newsfeed->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        // Return the formatted newsfeeds with pagination metadata
        return $this->sendResponse([
            'newsfeeds' => $formattedNewsfeeds,
            'pagination' => [
                'total' => $newsfeeds->total(),
                'current_page' => $newsfeeds->currentPage(),
                'last_page' => $newsfeeds->lastPage(),
                'per_page' => $newsfeeds->perPage(),
                'from' => $newsfeeds->firstItem(),
                'to' => $newsfeeds->lastItem(),
            ]
        ], 'Successfully retrieved newsfeeds.');
    }
    public function products(Request $request)
    {
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
