<?php

use App\Http\Controllers\Api\Admin\AnalyticsDashboardController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\EpaperEditionController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\Admin\PrivacyDataRequestController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\StaffRoleController;
use App\Http\Controllers\Api\Admin\VoucherManagementController;
use App\Http\Controllers\Api\AnalyticsEventController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClippingController;
use App\Http\Controllers\Api\EntitlementController;
use App\Http\Controllers\Api\EpaperDownloadController;
use App\Http\Controllers\Api\EpaperReaderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PrivacyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionUpgradeController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\VoucherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('plans', [PlanController::class, 'index']);
Route::post('analytics/events', [AnalyticsEventController::class, 'store'])->middleware('throttle:120,1');

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
        Route::get('consents', [PrivacyController::class, 'consents']);
        Route::post('consents', [PrivacyController::class, 'recordConsent'])->middleware('throttle:20,1');
        Route::get('privacy/export', [PrivacyController::class, 'export'])->middleware('throttle:5,1');
        Route::get('privacy/deletion-requests', [PrivacyController::class, 'deletionRequests']);
        Route::post('privacy/deletion-requests', [PrivacyController::class, 'requestDeletion'])
            ->middleware('throttle:3,60');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('vouchers/validate', [VoucherController::class, 'validateCode'])->middleware('throttle:10,1');
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
    Route::get('orders', [TransactionController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::get('orders/{order}/invoice', [TransactionController::class, 'invoice'])
        ->whereNumber('order')
        ->name('api.orders.invoice');
    Route::post('orders/{order}/pay', [PaymentController::class, 'pay'])->middleware('throttle:10,1');
    Route::get('orders/{order}/payment-status', [PaymentController::class, 'status'])->middleware('throttle:20,1');
    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::get('subscriptions/history', [TransactionController::class, 'subscriptionHistory']);
    Route::post('subscription/upgrade/quote', [SubscriptionUpgradeController::class, 'quote'])->middleware('throttle:10,1');
    Route::post('subscription/upgrade', [SubscriptionUpgradeController::class, 'store'])->middleware('throttle:10,1');
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

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'backoffice:staff'])
    ->group(function (): void {
        Route::get('customers', [AdminCustomerController::class, 'index']);
        Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->whereNumber('customer');
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('payments', [AdminPaymentController::class, 'index']);
        Route::get('reports/customers.csv', [ReportController::class, 'customers'])->middleware('throttle:10,1');
        Route::get('reports/transactions.csv', [ReportController::class, 'transactions'])->middleware('throttle:10,1');
        Route::get('analytics/dashboard', [AnalyticsDashboardController::class, 'show']);

        Route::middleware('backoffice:admin')->group(function (): void {
            Route::patch('users/{user}/role', [StaffRoleController::class, 'update'])->whereNumber('user');
            Route::get('audit-logs', [AuditLogController::class, 'index']);
            Route::get('privacy/deletion-requests', [PrivacyDataRequestController::class, 'index']);
            Route::patch('privacy/deletion-requests/{privacyDataRequest}', [PrivacyDataRequestController::class, 'update'])
                ->whereNumber('privacyDataRequest');
            Route::get('plans', [AdminPlanController::class, 'index']);
            Route::post('plans', [AdminPlanController::class, 'store'])->middleware('throttle:10,1');
            Route::get('plans/{plan}', [AdminPlanController::class, 'show'])->whereNumber('plan');
            Route::patch('plans/{plan}', [AdminPlanController::class, 'update'])->whereNumber('plan');
            Route::get('vouchers', [VoucherManagementController::class, 'index']);
            Route::post('vouchers', [VoucherManagementController::class, 'store'])->middleware('throttle:10,1');
            Route::get('vouchers/{voucher}', [VoucherManagementController::class, 'show'])->whereNumber('voucher');
            Route::patch('vouchers/{voucher}', [VoucherManagementController::class, 'update'])->whereNumber('voucher');
            Route::delete('vouchers/{voucher}', [VoucherManagementController::class, 'destroy'])->whereNumber('voucher');
        });
    });

Route::post('payments/webhook', [PaymentController::class, 'webhook']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
