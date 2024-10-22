
<?php

use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\LoveController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NewsFeedController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SocketController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TermAndConditioncontroller;

Route::group(['middleware' => ['api'],'controller' => AuthController::class], function () {
    Route::post('/register', 'register')->withoutMiddleware('jwt.auth');
    Route::post('/login', 'login')->withoutMiddleware('jwt.auth');
    Route::post('/verifyOtp', 'verifyOtp')->withoutMiddleware('jwt.auth');
    Route::post('/forgotPassword', 'forgotPassword')->withoutMiddleware('jwt.auth');
    Route::post('/resetPassword', 'resetPassword')->withoutMiddleware('jwt.auth');
    Route::post('/resendOtp', 'resendOtp')->withoutMiddleware('jwt.auth');
    Route::get('/get-user-name', 'getUserName')->withoutMiddleware('jwt.auth');
    // Route::post('/loginWithGoogle', 'loginWithGoogle')->withoutMiddleware('jwt.auth');
    // Route::post('/loginWithFacebook', 'loginWithFacebook')->withoutMiddleware('jwt.auth');
    Route::post('/logout', 'logout')->middleware(['jwt.auth','member']);
    Route::get('/user', 'user')->middleware(['jwt.auth','member']);
    Route::post('/updatePassword', 'updatePassword')->middleware(['jwt.auth','member']);
    Route::get('/getProfile', 'getProfile')->middleware(['jwt.auth','member']);
    Route::put('/profile', 'profile')->middleware(['jwt.auth','member']);
    Route::patch('/isActive', 'isActive')->middleware(['jwt.auth','member']);
    Route::patch('/noActive', 'noActive')->middleware(['jwt.auth','member']);
    Route::get('/validate-token',  'validateToken')->middleware(['jwt.auth','member']);

});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::post('/privacy-private', [ProfileController::class, 'privacyPrivate']);
    Route::post('/privacy-friend', [ProfileController::class, 'privacyFriend']);
    Route::post('/privacy-public', [ProfileController::class, 'privacyPublic']);
    Route::get('/userProfile', [ProfileController::class, 'userProfile']);
    Route::get('/anotherUserProfile/{id}', [ProfileController::class, 'anotherUserProfile']);
});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::get('/newsfeeds', [NewsFeedController::class, 'index']);
    Route::post('/newsfeeds', [NewsFeedController::class, 'store']);
    Route::put('/updateNewsfeeds/{id}', [NewsFeedController::class, 'update']);
    Route::delete('/newsfeeds/{id}', [NewsFeedController::class, 'destroy']);
    Route::get('/newsfeedsCount', [NewsFeedController::class, 'count']);
    Route::get('/usernewsfeeds', [NewsFeedController::class, 'usernewsfeeds']);
});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::post('/friend-request/{friend_id}', [FriendController::class, 'sendRequest']);
    Route::post('/accept-request/{friend_id}', [FriendController::class, 'acceptRequest']);
    Route::delete('/unfriend/{friend_id}', [FriendController::class, 'unfriend']);
    Route::delete('/cancel-request/{friend_id}', [FriendController::class, 'cancelRequest']);
    Route::get('/user-friend-requests', [FriendController::class, 'userFriendRequests']);
    Route::get('/user-friends', [FriendController::class, 'userFriends']);
});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::post('like-newsfeed', [LikeController::class, 'likeNewsfeed']);
    Route::get('getNewsfeedlikes', [LikeController::class, 'getNewsfeedLikes']);
});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::post('comment', [CommentController::class, 'store']);
    Route::put('comment/{id}', [CommentController::class, 'update']);
    // Route::get('commentView/{newsfeedId}', [CommentController::class, 'commentView']);
    Route::delete('commentDelete/{id}', [CommentController::class, 'destroy']);
});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::post('follow/{userId}', [FollowController::class, 'follow'])->name('follow');
    Route::delete('unfollow/{userId}', [FollowController::class, 'unfollow'])->name('unfollow');
    Route::get('followers', [FollowController::class, 'followersList'])->name('followers.list');
    Route::get('following', [FollowController::class, 'followingList'])->name('following.list');
});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::post('messageSend', [MessageController::class, 'store']);
    Route::get('getMessage', [MessageController::class, 'getMessage']);
    Route::delete('deleteMessage/{id}', [MessageController::class, 'destroy']);
    Route::get('messageView/{id}', [MessageController::class, 'view']);
});
Route::group(['middleware' => ['api', 'jwt.auth','member']], function () {
    Route::get('search-group', [GroupController::class, 'groupSearch']);
    Route::get('your-group', [GroupController::class, 'yourGroup']);
    Route::get('other-group', [GroupController::class, 'otherGroup']);

    Route::post('join-group-request', [GroupController::class, 'joinGroupRequest']);
    Route::post('accept-join-request/{id}', [GroupController::class, 'acceptJoinRequest']);


    Route::get('groups', [GroupController::class, 'index']);
    Route::get('search-people', [GroupController::class, 'peopleSearch']);
    Route::post('groups', [GroupController::class, 'store']);
    Route::put('groups/{id}', [GroupController::class, 'update']);
    Route::delete('groups/{id}', [GroupController::class, 'destroy']);

    Route::get('groups/{group}/members', [GroupController::class, 'groupMembers']);
    Route::post('groups/{group}/members', [GroupController::class, 'addMembers']);
    Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);

    Route::post('groups/{group}/messages', [GroupController::class, 'groupMessage']);
    Route::get('groups/{group}/messages', [GroupController::class, 'getMessages']);
    Route::patch('group-messages/{messageId}/read', [GroupController::class, 'markAsRead']);
    Route::delete('group-messages/{messageId}', [GroupController::class, 'deleteMessage']);
});
Route::middleware(['api', 'jwt.auth','member'])->prefix('')->group(function () {
    Route::apiResource('shops', ShopController::class);
    Route::get('user-shop', [ShopController::class,'userShop']);
});
Route::middleware(['api', 'jwt.auth','member'])->prefix('')->group(function () {
    Route::post('/support', [SupportController::class, 'support']);
});
Route::middleware(['api', 'jwt.auth','member'])->prefix('')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'getAllNotifications']);
    Route::get('/notifications/unread', [NotificationController::class, 'getUnreadNotifications']);
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
Route::middleware(['api', 'jwt.auth','member'])->prefix('')->group(function () {
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/all', [SearchController::class, 'all']);
    Route::get('/post', [SearchController::class, 'newsfeed']);
    Route::get('/product', [SearchController::class, 'products']);
    Route::get('/people', [SearchController::class, 'peoples']);
});
                        /* --- Admin  */
Route::middleware(['api', 'jwt.auth','admin'])->group(function () {
    Route::apiResource('categories', CategoryController::class);
});
Route::middleware(['api', 'jwt.auth','member'])->prefix('')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::get('userproducts', [ProductController::class, 'userproducts']);
    Route::get('productList', [ProductController::class, 'productList']);
    Route::post('approved/{id}', [ProductController::class, 'approved']);
    Route::post('canceled/{id}', [ProductController::class, 'canceled']);
    Route::post('pending/{id}', [ProductController::class, 'pending']);
    Route::get('categories', [ProductController::class, 'categories']);
});
Route::middleware(['api', 'jwt.auth','admin'])->group(function () {
    Route::get('userList', [AuthController::class, 'userList']);
    Route::get('searchUser', [AuthController::class, 'searchUser']);
    Route::get('userProducts', [AuthController::class, 'userProducts']);
    Route::post('users/{id}/role', [AuthController::class, 'updateRole']);
    Route::delete('deleteUser/{id}', [AuthController::class, 'deleteUser']);
});
Route::middleware(['api', 'jwt.auth','admin'])->group(function () {
    Route::apiResource('love', LoveController::class);
});
Route::middleware(['api', 'jwt.auth','admin'])->group(function () {
    Route::get('activeUser', [DashboardController::class,'activeUser']);
});
Route::middleware(['api', 'jwt.auth'])->group(function () {
    Route::put('personalInformation', [SettingController::class,'personalInformation'])->middleware('admin');
    Route::get('getpersonalInformation', [SettingController::class,'getPersonalInformation'])->middleware('admin');
    Route::apiResource('faqs',FaqController::class)->middleware('member');
    Route::apiResource('terms-and-conditions',TermAndConditioncontroller::class)->middleware('admin')
    ->only(['index','store','update','destroy']);
    Route::apiResource('terms-and-conditions',TermAndConditioncontroller::class)->middleware('member')
    ->only(['index']);
});

