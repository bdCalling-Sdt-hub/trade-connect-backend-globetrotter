
<?php

use App\Http\Controllers\Api\LikeController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NewsFeedController;
use App\Http\Controllers\Api\SocketController;

Route::group([
    'middleware' => ['api'],
    'controller' => AuthController::class,
], function () {

    Route::post('/register', 'register')->withoutMiddleware('jwt.auth');
    Route::post('/login', 'login')->withoutMiddleware('jwt.auth');
    Route::post('/verifyOtp', 'verifyOtp')->withoutMiddleware('jwt.auth');
    Route::post('/forgotPassword', 'forgotPassword')->withoutMiddleware('jwt.auth');
    Route::post('/resetPassword', 'resetPassword')->withoutMiddleware('jwt.auth');
    Route::post('/resendOtp', 'resendOtp')->withoutMiddleware('jwt.auth');

    // Route::post('/loginWithGoogle', 'loginWithGoogle')->withoutMiddleware('jwt.auth');
    // Route::post('/loginWithFacebook', 'loginWithFacebook')->withoutMiddleware('jwt.auth');

    Route::post('/logout', 'logout')->middleware('jwt.auth');
    Route::get('/user', 'user')->middleware('jwt.auth');
    Route::post('/updatePassword', 'updatePassword')->middleware('jwt.auth');
    Route::post('/profile', 'profile')->middleware('jwt.auth');
});
Route::group(['middleware' => ['api', 'jwt.auth']], function () {
    Route::get('/newsfeeds', [NewsFeedController::class, 'index']);
    Route::post('/newsfeeds', [NewsFeedController::class, 'store']);
    Route::get('/newsfeeds/{id}', [NewsFeedController::class, 'show']);
    Route::put('/updateNewsfeeds/{id}', [NewsFeedController::class, 'update']);
    Route::delete('/newsfeeds/{id}', [NewsFeedController::class, 'destroy']);
});
Route::group(['middleware' => ['api', 'jwt.auth']], function () {
    Route::post('like-newsfeed', [LikeController::class, 'likeNewsfeed']);
});
Route::group(['middleware' => ['api', 'jwt.auth']], function () {
    Route::post('comment', [CommentController::class, 'store']);
    Route::get('commentView/{newsfeedId}', [CommentController::class, 'commentView']);
    Route::delete('commentDelete/{id}', [CommentController::class, 'destroy']);
});
Route::group(['middleware' => ['api', 'jwt.auth']], function () {
    Route::post('messageSend', [MessageController::class, 'store']);
    Route::delete('deleteMessage/{id}', [MessageController::class, 'destroy']);
    Route::get('messageView/{id}', [MessageController::class, 'view']);
});
Route::group(['middleware' => ['api', 'jwt.auth']], function () {
    Route::post('groups', [GroupController::class, 'store']);
    Route::get('groups', [GroupController::class, 'index']);
    Route::put('groups/{id}', [GroupController::class, 'update']);
    Route::delete('groups/{id}', [GroupController::class, 'destroy']);

    Route::post('groups/{group}/members', [GroupController::class, 'addMember']);
    Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);
});


