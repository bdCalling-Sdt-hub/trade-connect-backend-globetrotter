
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;

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
