<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Events\OrderPaid;
use App\Events\OrderPaymentStatusChanged;
use App\Listeners\ActivateSubscriptionAfterPayment;
use App\Listeners\SendPaymentOutcomeNotification;
use App\Services\MidtransSnapGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, MidtransSnapGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(OrderPaid::class, ActivateSubscriptionAfterPayment::class);
        Event::listen(OrderPaymentStatusChanged::class, SendPaymentOutcomeNotification::class);

        RateLimiter::for('auth-login', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower($email) : '';
            $onlyFailedAttempts = fn ($response): bool => $response->getStatusCode() === 422;

            return [
                Limit::perMinute(5)
                    ->by('credentials:'.Str::transliterate($email.'|'.$request->ip()))
                    ->after($onlyFailedAttempts),
                Limit::perMinute(30)
                    ->by('ip:'.$request->ip())
                    ->after($onlyFailedAttempts),
            ];
        });

        ResetPassword::createUrlUsing(
            fn ($user, string $token): string => rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ])
        );
    }
}
