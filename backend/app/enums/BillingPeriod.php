<?php
// app/Enums/BillingPeriod.php
namespace App\Enums;

enum BillingPeriod: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }
}