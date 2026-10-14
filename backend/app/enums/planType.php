<?php
// app/Enums/PlanType.php
namespace App\Enums;

enum PlanType: string
{
    case FreeAdsEpaper = 'free_ads_epaper';
    case FreeAds = 'free_ads';
    case Epaper = 'epaper';

    public function label(): string
    {
        return match ($this) {
            self::FreeAdsEpaper => 'Free Ads & E-Paper',
            self::FreeAds => 'Free Ads',
            self::Epaper => 'E-Paper',
        };
    }
}