<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function customers(Request $request, BackofficeAuditService $audit): StreamedResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:200'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $staffEmails = array_values(array_unique([
            ...config('backoffice.admin_emails', []),
            ...config('backoffice.staff_emails', []),
        ]));

        /** @var User $actor */
        $actor = $request->user();
        $audit->record($actor, $request, 'report.customers.exported', 'customer', null, [
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'search_used' => isset($validated['search']),
        ]);

        return response()->streamDownload(function () use ($validated, $staffEmails): void {
            $output = $this->openOutput();
            $this->writeRow($output, ['customer_id', 'name', 'email', 'email_verified', 'subscription_count', 'created_at']);

            User::query()
                ->select(['id', 'name', 'email', 'email_verified_at', 'role', 'created_at'])
                ->where('role', 'member')
                ->when($staffEmails !== [], fn (Builder $query) => $query->whereNotIn(DB::raw('LOWER(email)'), $staffEmails))
                ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
                ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
                ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                    $term = '%'.Str::lower(trim($search)).'%';
                    $query->where(function (Builder $match) use ($term): void {
                        $match->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                    });
                })
                ->withCount('subscriptions')
                ->chunkById(500, function (EloquentCollection $customers) use ($output): void {
                    foreach ($customers as $customer) {
                        $this->writeRow($output, [
                            $customer->id,
                            $customer->name,
                            $customer->email,
                            $customer->email_verified_at ? 'yes' : 'no',
                            $customer->subscriptions_count,
                            $customer->created_at->timezone('Asia/Jakarta')->toIso8601String(),
                        ]);
                    }
                });

            fclose($output);
        }, 'customers-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function transactions(Request $request, BackofficeAuditService $audit): StreamedResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,paid,expired,failed,cancelled'],
            'payment_status' => ['sometimes', 'string', 'in:pending,success,failed,expired'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $audit->record($actor, $request, 'report.transactions.exported', 'transaction', null, [
            'status' => $validated['status'] ?? null,
            'payment_status' => $validated['payment_status'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);

        return response()->streamDownload(function () use ($validated): void {
            $output = $this->openOutput();
            $this->writeRow($output, [
                'order_id',
                'order_number',
                'customer_id',
                'customer_name',
                'customer_email',
                'plan',
                'order_type',
                'subtotal',
                'discount',
                'upgrade_credit',
                'total',
                'order_status',
                'payment_status',
                'payment_method',
                'paid_at',
                'created_at',
            ]);

            Order::query()
                ->with([
                    'user:id,name,email',
                    'plan:id,name',
                    'payments' => fn (HasMany $query) => $query->latest('id')->limit(1),
                ])
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($validated['payment_status'] ?? null, fn (Builder $query, string $status) => $query->whereHas(
                    'payments',
                    fn (Builder $payments) => $payments->where('status', $status)
                ))
                ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
                ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
                ->chunkById(500, function (EloquentCollection $orders) use ($output): void {
                    foreach ($orders as $order) {
                        $payment = $order->payments->first();
                        $this->writeRow($output, [
                            $order->id,
                            $order->order_number,
                            $order->user->id,
                            $order->user->name,
                            $order->user->email,
                            $order->plan->name,
                            $order->upgrade_from_subscription_id !== null ? 'upgrade' : 'subscription',
                            $order->subtotal,
                            $order->discount,
                            $order->upgrade_credit,
                            $order->total,
                            $order->status->value,
                            $payment?->status->value,
                            $payment?->method,
                            $payment?->paid_at?->timezone('Asia/Jakarta')->toIso8601String(),
                            $order->created_at->timezone('Asia/Jakarta')->toIso8601String(),
                        ]);
                    }
                });

            fclose($output);
        }, 'transactions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return resource
     */
    private function openOutput(): mixed
    {
        $output = fopen('php://output', 'wb');

        if (! is_resource($output)) {
            throw new RuntimeException('Unable to open the report output stream.');
        }

        return $output;
    }

    /**
     * @param  resource  $output
     * @param  array<int, int|string|null>  $values
     */
    private function writeRow(mixed $output, array $values): void
    {
        $values = array_map(fn (int|string|null $value): string => $this->safeCsvCell($value), $values);
        if (fputcsv($output, $values, ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('Unable to write the report output.');
        }
    }

    private function safeCsvCell(int|string|null $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $cell) === 1
            ? "'".$cell
            : $cell;
    }
}
