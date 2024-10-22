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
        return $this->formatUserProfile($user, $request);
    }

    public function anotherUserProfile($id, Request $request)
    {
        $user = Auth::user();
        $anotherUser = User::findOrFail($id);

        if ($anotherUser->privacy === 'private' && $anotherUser->id !== $user->id) {
            return $this->sendError('No available data found', 403);
        }
        if ($anotherUser->privacy === 'friends') {
            $isFriend = Friend::where('is_accepted', true)
                ->where(function ($query) use ($user, $anotherUser) {
                    $query->where('user_id', $user->id)
                          ->where('friend_id', $anotherUser->id)
                          ->orWhere('user_id', $anotherUser->id)
                          ->where('friend_id', $user->id);
                })->exists();

            if (!$isFriend && $anotherUser->id !== $user->id) {
                return $this->sendError('No available data found', 403);
            }
        }

        return $this->formatUserProfile($anotherUser, $request);
    }

    private function formatUserProfile($user, Request $request)
    {
        $privacyFilter = $request->input('privacy', null);
        $user->load('friends', 'newsFeeds.likes', 'newsFeeds.comments.replies', 'newsFeeds.comments.user', 'products.shop', 'products.category');

        $friendsCount = Friend::where('is_accepted', true)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id);
            })
            ->count();
        $newsFeedsQuery = $user->newsFeeds()->orderBy('id', 'DESC');
        if ($privacyFilter) {
            if ($privacyFilter === 'friends') {
                $friendIds = Friend::where('is_accepted', true)
                    ->where(function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->orWhere('friend_id', $user->id);
                    })
                    ->pluck('user_id', 'friend_id')
                    ->flatten()
                    ->toArray();

                $newsFeedsQuery->whereIn('user_id', $friendIds);
            } else {
                $newsFeedsQuery->where('privacy', $privacyFilter);
            }
        }
        $newsFeeds = $newsFeedsQuery->get();
        $formattedNewsFeeds = $newsFeeds->map(function ($newsFeed) use ($user) {
            $decodedImages = json_decode($newsFeed->images);
            return [
                'id'            => $newsFeed->id,
                'content'       => $newsFeed->share_your_thoughts,
                'privacy'       => $newsFeed->privacy,
                'image_count'     => count($decodedImages),
                'images'          => collect(json_decode($newsFeed->images))->map(function ($image) {
                                        return [
                                        'url' => $image ? url('NewsFeedImages/', $image) : '',
                                        ];
                                    })->toArray(),

                'newsfeed_status' => $newsFeed->status ? 'active' : 'inactive',
                'user'=>[
                    'id'=> $newsFeed->user->id ?? '',
                    'full_name'=> $newsFeed->user->full_name ?? '',
                    'user_name'=> $newsFeed->user->user_name ?? '',
                    'email'=> $newsFeed->user->email ?? '',
                    'image'=> $newsFeed->user->image ? url('Profile/',$newsFeed->user->image) : '',
                    ],
                'like_count'    => $newsFeed->likes->count(),
                'auth_user_liked' => $newsFeed->likes->contains('user_id', $user->id),
                'comment_count' => $newsFeed->comments->count(),
                'comments'      => $newsFeed->comments->map(function ($comment) {
                    return [
                        'id'            => $comment->id,
                        'full_name'     => $comment->user->full_name,
                        'user_name'     => $comment->user->user_name,
                        'image'         => $comment->user->image ? url('Profile/',$comment->user->image) :'',
                        'comment'       => $comment->comments,
                        'created_at'    => $this->getTimePassed($comment->created_at),
                        'reply_count'   => $comment->replies->count(),
                        'replies'       => $comment->replies->map(function ($reply) {
                            return [
                                'id'         => $reply->id,
                                'comment'    => $reply->comments,
                                'full_name'  => $reply->user->full_name,
                                'user_name'  => $reply->user->user_name,
                                'image'      => $reply->user->image ? url('Profile/',$reply->user->image) : '',
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
            ];
        }
        $formattedProducts = $user->products->map(function ($product) {
            return [
                'id'          => $product->id,
                'product_name'=> $product->product_name,
                'product_code'=> $product->product_code,
                'category_name'=> $product->category->category_name,
                'price'       => $product->price,
                'product_images'      => collect(json_decode($product->images))->map(function ($image) {
                                    return $image ? url('products/', $image) : '';

                                }),
                'description' => $product->description,
                'shop_name'   => $product->shop->shop_name,
                'seller_name' => $product->shop->user->full_name,
                'seller_user_name' => $product->shop->user->user_name,
                'seller_image' => $product->shop->user->image ? url('Profile/', $product->shop->user->image) : '',
                'created_at'  => $product->created_at->format('Y-m-d H:i:s'),
            ];
        });
        return $this->sendResponse([
            'id'            => $user->id,
            'full_name'     => $user->full_name,
            'user_name'     => $user->user_name,
            'bio'           => $user->bio,
            'privacy'       => $user->privacy,
            'email'         => $user->email,
            'image'         => $user->image ? url('Profile/',$user->image) : '',
            'friends_count' => $friendsCount,
            'news_feeds'    => $formattedNewsFeeds ?? '',
            'shop'          => $shop ?? '',
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
