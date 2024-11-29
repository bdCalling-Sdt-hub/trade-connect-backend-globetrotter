<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function privacyPrivate(Request $request)
    {
        $user = Auth::user();
        $user->privacy = 'private';
        $user->save();

        return $this->sendResponse(["privacy"=>$user->privacy], 'Privacy set to private.');
    }
    public function privacyFriend(Request $request)
    {
        $user = Auth::user();
        $user->privacy = 'friends';
        $user->save();

        return $this->sendResponse(["privacy"=>$user->privacy], 'Privacy set to friends.');
    }
    public function privacyPublic(Request $request)
    {
        $user = Auth::user();
        $user->privacy = 'public';
        $user->save();

        return $this->sendResponse(["privacy"=>$user->privacy], 'Privacy set to public.');
    }

    public function userProfile(Request $request)
    {
        $user = Auth::user();
        $user->load([
            'friends',
            'newsFeeds.likes',
            'newsFeeds.comments.replies',
            'newsFeeds.comments.user',
            'products.shop',
            'products.category',
        ]);

        $friendsCount = Friend::where('is_accepted', true)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->count();

        $formattedNewsFeeds = $this->formatNewsFeeds($user->newsFeeds)->sortByDesc('id')->values();
        $formattedProducts = $this->formatProducts($user->products);

        $shop = $this->getShopDetails($user);

        return $this->sendResponse([
            'id' => $user->id,
            'full_name' => $user->full_name,
            'user_name' => $user->user_name,
            'privacy' => $user->privacy,
            'contact' => $user->contact,
            'bio' => $user->bio,
            'email' => $user->email,
            'image' => $this->getUserImage($user->image),
            'friends_count' => $friendsCount,
            'news_feeds' => $formattedNewsFeeds,
            'shop' => $shop,
            'formattedProducts' => $formattedProducts,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
        ], 'User profile retrieved successfully');
    }

    private function formatNewsFeeds($newsFeeds)
    {
        return $newsFeeds->map(function ($newsFeed)  {
            $decodedImages = json_decode($newsFeed->images);
            return [
                'id'              => $newsFeed->id,
                'content'         => $newsFeed->share_your_thoughts,
                'image_count'     => count($decodedImages),
                'images'          => collect($decodedImages)->map(function ($image) {
                    return [
                        'url' => $image ? url('NewsFeedImages/', $image) : '',
                    ];
                })->toArray(),
                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'user'            => [
                    'id'        => $newsFeed->user->id ?? '',
                    'full_name' => $newsFeed->user->full_name ?? '',
                    'user_name' => $newsFeed->user->user_name ?? '',
                    'privacy'   => $newsFeed->user->privacy ?? '',
                    'email'     => $newsFeed->user->email,
                    'image'     => $newsFeed->user->image
                        ? url('profile/', $newsFeed->user->image)
                        : url('avatar/profile.png'),
                ],
                'like_count'      => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id'),
                'comment_count'   => $newsFeed->comments->count(),
                'comments'        => $newsFeed->comments->map(function ($comment) {
                    return [
                        'id'          => $comment->id,
                        'full_name'   => $comment->user->full_name,
                        'user_name'   => $comment->user->user_name,
                        'image'       => $comment->user->image
                            ? url('profile/', $comment->user->image)
                            : url('avatar/profile.png'),
                        'comment'     => $comment->comments,
                        'created_at'  => $this->getTimePassed($comment->created_at),
                        'reply_count' => $comment->replies->count(),
                        'replies'     => $comment->replies->map(function ($reply) {
                            return [
                                'id'         => $reply->id,
                                'comment'    => $reply->comments,
                                'full_name'  => $reply->user->full_name,
                                'user_name'  => $reply->user->user_name,
                                'image'      => $reply->user->image
                                    ? url('profile/', $reply->user->image)
                                    : url('avatar/profile.png'),
                                'created_at' => $this->getTimePassed($reply->created_at),
                            ];
                        }),
                    ];
                }),
            ];
        });
    }

    private function formatProducts($products)
    {
        return $products->map(function ($product) {
            return [
                'id'          => $product->id,
                'product_name'=> $product->product_name,
                'product_code'=> $product->product_code,
                'category_name'=> $product->category->category_name,
                'price' => $product->price,
                'product_images'=> collect(json_decode($product->images))->map(function ($image) {
                                    return $image
                                    ? url('products/', $image)
                                    : url('avatar/product.png');
                                }),
                'description' => $product->description,
                'shop_name'   => $product->shop->shop_name,
                'seller_name' => $product->shop->user->full_name,
                'seller_user_name' => $product->shop->user->user_name,
                'seller_image' => $product->shop->user->image
                                ? url('profile/', $product->shop->user->image)
                                : url('avatar/profile.png'),
                'created_at'  => $product->created_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    private function formatImages($images, $basePath = 'NewsFeedImages')
    {
        $decodedImages = json_decode($images);
        return collect($decodedImages)->map(function ($image) use ($basePath) {
            return $image ? url($basePath, $image) : '';
        })->toArray();
    }

    private function getShopDetails($user)
    {
        $shopData = Shop::where('user_id', $user->id)->first();
        if (!$shopData) return null;

        return [
            'shop_name' => $shopData->shop_name,
            'seller' => [
                'full_name' => $shopData->user->full_name,
                'user_name' => $shopData->user->user_name,
                'email' => $shopData->user->email,
                'image' => $this->getUserImage($shopData->user->image),
            ],
        ];
    }

    private function getUserImage($image)
    {
        return $image ? url('profile/', $image) : url('avatar/profile.png');
    }


    public function anotherUserProfile($id, Request $request)
    {
        $user = Auth::user();
        $anotherUser = User::findOrFail($id);
        if(!$anotherUser)
        {
            $this->sendError("No user found.");
        }
        if ($anotherUser->privacy === 'private') {
            $imageUrl = $anotherUser->image
                        ? url('profile/' . $anotherUser->image)
                        : url('avatar/profile.png');
            $profileData = [
                'id'=> $anotherUser->id,
                'full_name' => $anotherUser->full_name,
                'user_name' => $anotherUser->user_name,
                'email'=> $anotherUser->email,
                'balance'=>$anotherUser->balance,
                'bio'=> $anotherUser->bio ?? '',
                'privacy'=>$anotherUser->privacy ??'',
                'location' => $anotherUser->location,
                'contact' => $anotherUser->contact,
                'image' => $imageUrl,
            ];
            return $this->sendResponse($profileData,'Profile is Private.');
        }
        return $this->formatUserProfile($anotherUser, $request);
    }
    private function formatUserProfile($user, Request $request)
    {
        $user->load('friends', 'newsFeeds.likes', 'newsFeeds.comments.replies', 'newsFeeds.comments.user', 'products.shop', 'products.category');

        $friendsCount = Friend::where('is_accepted', true)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id);
            })
            ->count();
        $authUserId = Auth::user()->id;
        $newsFeedsQuery = $user->newsFeeds()->orderBy('id', 'DESC');
        if ($user->privacy === 'friends') {
            $friendIds = Friend::where('is_accepted', true)
            ->where(function ($query) use ($authUserId) {
                $query->where('user_id', $authUserId)
                      ->orWhere('friend_id', $authUserId);
            })
            ->pluck('user_id', 'friend_id')
            ->keys()
            ->unique()
            ->toArray();

            $newsFeedsQuery->whereIn('user_id', $friendIds);
        } elseif ($user->privacy === 'public') {
            $newsFeedsQuery->where('privacy', 'public');
        }
        $newsFeeds = $newsFeedsQuery->get();
        $formattedNewsFeeds = $newsFeeds->map(function ($newsFeed) use ($user) {
            $decodedImages = json_decode($newsFeed->images);
            return [
                'id'          => $newsFeed->id,
                'content'     => $newsFeed->share_your_thoughts,
                'image_count' => count($decodedImages),
                'images'      => collect(json_decode($newsFeed->images))->map(function ($image) {
                                        return [
                                        'url' => $image ? url('NewsFeedImages/', $image) : '',
                                        ];
                                    })->toArray(),
                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'user'=>[
                    'id'=> $newsFeed->user->id ?? '',
                    'full_name'=> $newsFeed->user->full_name ?? '',
                    'user_name'=> $newsFeed->user->user_name ?? '',
                    'privacy'=> $newsFeed->user->privacy ?? '',
                    'email'=> $newsFeed->user->email ,
                    'image'=> $newsFeed->user->image
                        ? url('profile/',$newsFeed->user->image)
                        : url('avatar/profile.png'),
                    ],
                'like_count'    => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id', $user->id),
                'comment_count' => $newsFeed->comments->count(),
                'comments'      => $newsFeed->comments->map(function ($comment) {
                    return [
                        'id'            => $comment->id,
                        'full_name'     => $comment->user->full_name,
                        'user_name'     => $comment->user->user_name,
                        'image'         => $comment->user->image
                                            ? url('profile/',$comment->user->image)
                                            :url('avatar/profile.png'),
                        'comment'       => $comment->comments,
                        'created_at'    => $this->getTimePassed($comment->created_at),
                        'reply_count'   => $comment->replies->count(),
                        'replies'       => $comment->replies->map(function ($reply) {
                            return [
                                'id'         => $reply->id,
                                'comment'    => $reply->comments,
                                'full_name'  => $reply->user->full_name,
                                'user_name'  => $reply->user->user_name,
                                'image'      => $reply->user->image
                                            ? url('profile/',$reply->user->image)
                                            : url('avatar/profile.png'),
                                'created_at' => $this->getTimePassed($reply->created_at),
                            ];
                        })
                    ];
                })
            ];
        });
        $shop = null;
        $shopData = Shop::where('user_id', $user->id)->first();
        if ($shopData) {
            $shop = [
                'shop_name' => $shopData->shop_name,
                'seller'=>[
                    'full_name' => $shopData->user->full_name,
                    'user_name' => $shopData->user->user_name,
                    'email' => $shopData->user->email,
                    'image' => $shopData->user->image
                            ? url('profile/', $shopData->user->image)
                            : url('avatar/profile.png'),
                ]
            ];
        }
        $productQuery = $user->products();
        if ($user->privacy === 'friends') {
            $friendIds = Friend::where('is_accepted', true)
                ->where(function ($query) use ($authUserId) {
                    $query->where('user_id', $authUserId)
                        ->orWhere('friend_id', $authUserId);
                })
                ->pluck('user_id', 'friend_id')
                ->keys()
                ->unique()
                ->toArray();

            $productQuery->whereIn('user_id', $friendIds);
        }
        $products = $productQuery->get();
        $formattedProducts = $products->map(function ($product) {
            return [
                'id'          => $product->id,
                'product_name'=> $product->product_name,
                'product_code'=> $product->product_code,
                'category_name'=> $product->category->category_name,
                'price' => $product->price,
                'product_images'=> collect(json_decode($product->images))->map(function ($image) {
                                    return $image
                                    ? url('products/', $image)
                                    : url('avatar/product.png');
                                }),
                'description' => $product->description,
                'shop_name'   => $product->shop->shop_name,
                'seller_name' => $product->shop->user->full_name,
                'seller_user_name' => $product->shop->user->user_name,
                'seller_image' => $product->shop->user->image
                                ? url('profile/', $product->shop->user->image)
                                : url('avatar/profile.png'),
                'created_at'  => $product->created_at->format('Y-m-d H:i:s'),
            ];
        });
        return $this->sendResponse([
            'id'            => $user->id,
            'full_name'     => $user->full_name,
            'user_name'     => $user->user_name,
            'privacy'       => $user->privacy,
            'contact'       => $user->contact,
            'bio'           => $user->bio,
            'email'         => $user->email,
            'image'         => $user->image
                            ? url('profile/',$user->image)
                            : url('avatar/profile.png'),
            'friends_count' => $friendsCount,
            'news_feeds'    => $formattedNewsFeeds ?? '',
            'shop'          => $shop ?? null,
            'formattedProducts'=> $formattedProducts ?? '',
            'created_at'    => $user->created_at->format('Y-m-d H:i:s'),
            'updated_at'    => $user->updated_at->format('Y-m-d H:i:s'),
        ], 'User profile retrieved successfully');
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
}
