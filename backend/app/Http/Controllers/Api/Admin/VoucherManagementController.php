<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminVoucherResource;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BackofficeAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoucherManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $vouchers = Voucher::query()
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query
                ->whereRaw('LOWER(code) LIKE ?', ['%'.Str::lower(trim($search)).'%']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return response()->json([
            'data' => AdminVoucherResource::collection($vouchers->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
            ],
        ]);
    }

    public function store(Request $request, BackofficeAuditService $audit): JsonResponse
    {
        $type = $request->input('type');
        $validated = $request->validate($this->rules(is_string($type) ? $type : null));
        $validated['code'] = Str::upper(trim($validated['code']));

        /** @var User $actor */
        $actor = $request->user();
        $voucher = DB::transaction(function () use ($validated, $actor, $request, $audit): Voucher {
            $this->ensureCodeIsUnique($validated['code']);
            $voucher = Voucher::query()->create([
                ...$validated,
                'used' => 0,
            ]);

            $audit->record($actor, $request, 'voucher.created', 'voucher', $voucher->id, [
                'after' => $this->auditableVoucherValues($voucher),
            ]);

            return $voucher;
        });

        return response()->json([
            'data' => AdminVoucherResource::make($voucher)->resolve($request),
        ], 201);
    }

    public function show(Request $request, Voucher $voucher): JsonResponse
    {
        return response()->json([
            'data' => AdminVoucherResource::make($voucher)->resolve($request),
        ]);
    }

    public function update(Request $request, Voucher $voucher, BackofficeAuditService $audit): JsonResponse
    {
        $type = $request->input('type', $voucher->type);
        $rules = [
            ...$this->rules(is_string($type) ? $type : $voucher->type, creating: false),
            'code' => ['sometimes', 'required', 'string', 'max:100'],
            'used' => ['prohibited'],
        ];
        if (is_string($type) && $type !== $voucher->type) {
            $rules['value'][0] = 'required';
        }
        $validated = $request->validate($rules);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'voucher' => ['Setidaknya satu atribut voucher harus diperbarui.'],
            ]);
        }

        /** @var User $actor */
        $actor = $request->user();
        $result = DB::transaction(function () use ($voucher, $validated, $actor, $request, $audit): Voucher|JsonResponse {
            $lockedVoucher = Voucher::query()->whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $effectiveValidFrom = array_key_exists('valid_from', $validated)
                ? $validated['valid_from']
                : $lockedVoucher->valid_from?->toDateTimeString();
            $effectiveValidUntil = array_key_exists('valid_until', $validated)
                ? $validated['valid_until']
                : $lockedVoucher->valid_until?->toDateTimeString();

            if ($effectiveValidFrom !== null && $effectiveValidUntil !== null
                && Carbon::parse($effectiveValidUntil)->lt(Carbon::parse($effectiveValidFrom))) {
                throw ValidationException::withMessages([
                    'valid_until' => ['Tanggal berakhir harus sama dengan atau setelah tanggal mulai.'],
                ]);
            }

            if (array_key_exists('quota', $validated)
                && $validated['quota'] !== null
                && $validated['quota'] < $lockedVoucher->used) {
                throw ValidationException::withMessages([
                    'quota' => ['Kuota tidak boleh lebih kecil dari jumlah yang sudah terpakai atau dipesan.'],
                ]);
            }

            if (array_key_exists('code', $validated)) {
                $validated['code'] = Str::upper(trim($validated['code']));
            }

            $definitionChanged = (array_key_exists('code', $validated) && $validated['code'] !== $lockedVoucher->code)
                || (array_key_exists('type', $validated) && $validated['type'] !== $lockedVoucher->type)
                || (array_key_exists('value', $validated) && $validated['value'] !== $lockedVoucher->value);
            if ($definitionChanged && $lockedVoucher->orders()->exists()) {
                return response()->json([
                    'error' => [
                        'code' => 'voucher_definition_in_use',
                        'message' => 'Kode, tipe, atau nilai voucher tidak dapat diubah setelah digunakan pada pesanan.',
                    ],
                ], 409);
            }

            if (array_key_exists('code', $validated) && $validated['code'] !== $lockedVoucher->code) {
                $this->ensureCodeIsUnique($validated['code'], $lockedVoucher);
            }

            $before = $this->auditableVoucherValues($lockedVoucher);
            $lockedVoucher->forceFill($validated)->save();
            $lockedVoucher->refresh();

            $audit->record($actor, $request, 'voucher.updated', 'voucher', $lockedVoucher->id, [
                'before' => $before,
                'after' => $this->auditableVoucherValues($lockedVoucher),
            ]);

            return $lockedVoucher;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'data' => AdminVoucherResource::make($result)->resolve($request),
        ]);
    }

    public function destroy(Request $request, Voucher $voucher, BackofficeAuditService $audit): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $result = DB::transaction(function () use ($voucher, $actor, $request, $audit): ?JsonResponse {
            $lockedVoucher = Voucher::query()->whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($lockedVoucher->orders()->exists()) {
                return response()->json([
                    'error' => [
                        'code' => 'voucher_has_orders',
                        'message' => 'Voucher yang sudah terkait dengan pesanan tidak dapat dihapus.',
                    ],
                ], 409);
            }

            $before = $this->auditableVoucherValues($lockedVoucher);
            $voucherId = $lockedVoucher->id;
            $lockedVoucher->delete();

            $audit->record($actor, $request, 'voucher.deleted', 'voucher', $voucherId, [
                'before' => $before,
            ]);

            return null;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(status: 204);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?string $type = null, bool $creating = true): array
    {
        $maximumValue = $type === 'percent' ? 100 : 4294967295;

        return [
            'code' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'type' => [$creating ? 'required' : 'sometimes', 'string', 'in:percent,fixed'],
            'value' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:'.$maximumValue],
            'quota' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'used' => ['prohibited'],
        ];
    }

    private function ensureCodeIsUnique(string $code, ?Voucher $ignore = null): void
    {
        $exists = Voucher::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower($code)])
            ->when($ignore, fn (Builder $query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ['Kode voucher sudah digunakan.'],
            ]);
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function auditableVoucherValues(Voucher $voucher): array
    {
        return [
            'code' => $voucher->code,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'quota' => $voucher->quota,
            'used' => $voucher->used,
            'valid_from' => $voucher->valid_from?->toIso8601String(),
            'valid_until' => $voucher->valid_until?->toIso8601String(),
        ];
    }
}
