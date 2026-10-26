<?php

use App\Http\Controllers\Api\Admin\EpaperEditionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClippingController;
use App\Http\Controllers\Api\EntitlementController;
use App\Http\Controllers\Api\EpaperDownloadController;
use App\Http\Controllers\Api\EpaperReaderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\VoucherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('plans', [PlanController::class, 'index']);

Route::get('epaper/editions', [EpaperReaderController::class, 'index']);
Route::get('epaper/editions/{edition}/cover', [EpaperReaderController::class, 'cover'])
    ->whereNumber('edition')
    ->name('api.epaper.editions.cover');
Route::get('epaper/pages/{edition}/{page}', [EpaperReaderController::class, 'pageContent'])
    ->whereNumber('edition')
    ->whereNumber('page')
    ->middleware('signed')
    ->name('api.epaper.pages.content');
Route::get('epaper/downloads/{download}/file', [EpaperDownloadController::class, 'file'])
    ->whereNumber('download')
    ->middleware('signed')
    ->name('api.epaper.downloads.file');

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:5,1');
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::patch('password', [ProfileController::class, 'changePassword'])->middleware('throttle:5,1');
        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{session}', [SessionController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('vouchers/validate', [VoucherController::class, 'validateCode'])->middleware('throttle:10,1');
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/pay', [PaymentController::class, 'pay'])->middleware('throttle:10,1');
    Route::get('orders/{order}/payment-status', [PaymentController::class, 'status'])->middleware('throttle:20,1');
    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::get('entitlements', [EntitlementController::class, 'show']);
    Route::get('clippings', [ClippingController::class, 'index']);
    Route::post('clippings', [ClippingController::class, 'store'])->middleware('throttle:30,1');
    Route::delete('clippings/{clipping}', [ClippingController::class, 'destroy']);
    Route::get('epaper/editions/{edition}/pages/{page}', [EpaperReaderController::class, 'pageUrl'])
        ->whereNumber('edition')
        ->whereNumber('page')
        ->middleware('throttle:60,1');
    Route::get('epaper/editions/{edition}/download', [EpaperDownloadController::class, 'store'])
        ->whereNumber('edition')
        ->middleware('throttle:10,1');
    Route::get('epaper/downloads', [EpaperDownloadController::class, 'index']);
    Route::get('epaper/downloads/{download}', [EpaperDownloadController::class, 'show'])->whereNumber('download');
});

Route::prefix('admin/epaper/editions')
    ->middleware(['auth:sanctum', 'epaper-admin'])
    ->group(function (): void {
        Route::get('/', [EpaperEditionController::class, 'index']);
        Route::post('/', [EpaperEditionController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/{edition}', [EpaperEditionController::class, 'show']);
        Route::patch('/{edition}', [EpaperEditionController::class, 'update']);
        Route::post('/{edition}/publish', [EpaperEditionController::class, 'publish']);
    });

Route::post('payments/webhook', [PaymentController::class, 'webhook']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
