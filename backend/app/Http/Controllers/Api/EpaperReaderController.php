<?php

namespace App\Http\Controllers\Api;

use App\Enums\EditionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpaperPublicEditionResource;
use App\Models\EpaperEdition;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EpaperAccessService;
use App\Services\EpaperPrivateStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class EpaperReaderController extends Controller
{
    public function index(Request $request, EpaperAccessService $access): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:65535'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if (isset($filters['from'], $filters['to']) && $filters['to'] < $filters['from']) {
            throw ValidationException::withMessages([
                'to' => ['Tanggal akhir harus sama atau setelah tanggal awal.'],
            ]);
        }

        $editions = EpaperEdition::query()
            ->published()
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('publish_end_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('publish_date', '<=', $to))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->where('edition_year', $year))
            ->orderByDesc('publish_date')
            ->orderByDesc('edition_number')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        $user = $request->user();
        $subscriptions = $user instanceof User
            ? $access->activeEpaperSubscriptions($user)
            : collect();
        $resources = $editions->getCollection()->map(function (EpaperEdition $edition) use ($access, $request, $subscriptions): array {
            $edition->setAttribute(
                'access_allowed',
                $subscriptions->contains(
                    fn (Subscription $subscription): bool => $access->overlaps($subscription, $edition)
                )
            );

            return EpaperPublicEditionResource::make($edition)->resolve($request);
        });

        return response()->json([
            'data' => $resources,
            'meta' => [
                'current_page' => $editions->currentPage(),
                'last_page' => $editions->lastPage(),
                'per_page' => $editions->perPage(),
                'total' => $editions->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function cover(EpaperEdition $edition, EpaperPrivateStorage $storage)
    {
        abort_unless($edition->status === EditionStatus::Published && $edition->cover_path !== null, 404);

        $stream = $storage->disk()->readStream($edition->cover_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read the stored E-Paper cover.');
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pageUrl(
        Request $request,
        EpaperEdition $edition,
        int $page,
        EpaperAccessService $access
    ): JsonResponse {
        abort_unless($edition->status === EditionStatus::Published, 404);
        $this->authorizeEdition($request, $access, $edition);
        $this->validatePage($edition, $page);

        $ttl = (int) config('epaper.signed_url_ttl_minutes');
        $url = URL::temporarySignedRoute(
            'api.epaper.pages.content',
            CarbonImmutable::now()->addMinutes($ttl),
            ['edition' => $edition->id, 'page' => $page],
        );

        return response()->json([
            'data' => [
                'url' => $url,
                'expires_in' => $ttl * 60,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function pageContent(EpaperEdition $edition, int $page, EpaperPrivateStorage $storage)
    {
        abort_unless($edition->status === EditionStatus::Published, 404);
        $this->validatePage($edition, $page);

        $source = $storage->copyToTemporaryFile($edition->master_pdf_path, 'epaper-master-');
        $output = tempnam(sys_get_temp_dir(), 'epaper-page-');
        if ($output === false) {
            unlink($source);
            throw new RuntimeException('Unable to allocate a temporary PDF file.');
        }

        $ready = false;
        try {
            $pdf = new Fpdi;
            $pdf->setSourceFile($source);
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            $pdf->Output('F', $output);
            $ready = true;
        } finally {
            unlink($source);
            if (! $ready && file_exists($output)) {
                unlink($output);
            }
        }

        return response()->stream(function () use ($output): void {
            try {
                readfile($output);
            } finally {
                unlink($output);
            }
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="epaper-page-'.$edition->edition_number.'-'.$page.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeEdition(Request $request, EpaperAccessService $access, EpaperEdition $edition): void
    {
        $user = $request->user();
        if (! $user instanceof User || ! $access->canAccessEdition($user, $edition)) {
            abort(response()->json([
                'error' => [
                    'code' => 'epaper_subscription_required',
                    'message' => 'Langganan aktif yang mencakup E-Paper diperlukan untuk membaca edisi ini.',
                ],
            ], 403));
        }
    }

    private function validatePage(EpaperEdition $edition, int $page): void
    {
        if ($page < 1 || $page > $edition->page_count) {
            throw ValidationException::withMessages([
                'page' => ['Nomor halaman tidak tersedia pada edisi ini.'],
            ]);
        }
    }
}
