<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Expire unpaid orders after their 24-hour payment window.';

    public function handle(OrderService $orders): int
    {
        $expired = $orders->expirePendingOrders();
        $this->info("Expired {$expired} pending order(s).");

        return self::SUCCESS;
    }
}
