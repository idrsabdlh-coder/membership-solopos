<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EntitlementController;
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
});

Route::post('payments/webhook', [PaymentController::class, 'webhook']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
