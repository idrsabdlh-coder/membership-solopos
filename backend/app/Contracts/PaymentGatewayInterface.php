<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function createTransaction(Order $order, Payment $payment): array;

    public function fetchStatus(string $merchantOrderId): array;

    public function verifyNotification(array $notification): bool;

    public function normalizeNotification(array $notification): array;
}
