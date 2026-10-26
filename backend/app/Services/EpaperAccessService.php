<?php

namespace App\Services;

use App\Enums\PlanType;
use App\Models\EpaperEdition;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EpaperAccessService
{
    /**
     * @return Collection<int, Subscription>
     */
    public function activeEpaperSubscriptions(User $user, bool $lock = false): Collection
    {
        $query = Subscription::query()
            ->active()
            ->where('user_id', $user->id)
            ->whereHas('plan', fn (Builder $query) => $query->whereIn('type', [
                PlanType::FreeAdsEpaper->value,
                PlanType::Epaper->value,
            ]))
            ->with('plan')
            ->orderByDesc('end_date');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(
            fn (Subscription $subscription): bool => $subscription->plan?->includesEpaper() ?? false
        );
    }

    public function subscriptionForEdition(User $user, EpaperEdition $edition, bool $lock = false): ?Subscription
    {
        return $this->activeEpaperSubscriptions($user, $lock)
            ->first(fn (Subscription $subscription): bool => $this->overlaps($subscription, $edition));
    }

    public function canAccessEdition(User $user, EpaperEdition $edition): bool
    {
        return $this->subscriptionForEdition($user, $edition) !== null;
    }

    public function overlaps(Subscription $subscription, EpaperEdition $edition): bool
    {
        if ($subscription->start_date === null || $subscription->end_date === null) {
            return false;
        }

        $publicationStart = CarbonImmutable::parse($edition->publish_date->toDateString(), 'Asia/Jakarta')
            ->startOfDay()
            ->utc();
        $publicationEnd = CarbonImmutable::parse($edition->publish_end_date->toDateString(), 'Asia/Jakarta')
            ->endOfDay()
            ->utc();

        return $subscription->start_date->lessThanOrEqualTo($publicationEnd)
            && $subscription->end_date->greaterThan($publicationStart);
    }
}
