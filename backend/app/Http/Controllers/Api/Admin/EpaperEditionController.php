<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EditionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpaperEditionResource;
use App\Models\EpaperEdition;
use App\Models\User;
use App\Services\BackofficeAuditService;
use App\Services\EpaperPdfInspector;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class EpaperEditionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(array_column(EditionStatus::cases(), 'value'))],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:65535'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $editions = EpaperEdition::query()
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['year'] ?? null, fn (Builder $query, int $year) => $query->where('edition_year', $year))
            ->orderByDesc('publish_date')
            ->orderByDesc('edition_number')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return response()->json([
            'data' => EpaperEditionResource::collection($editions->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $editions->currentPage(),
                'last_page' => $editions->lastPage(),
                'per_page' => $editions->perPage(),
                'total' => $editions->total(),
            ],
        ]);
    }

    public function store(
        Request $request,
        EpaperPdfInspector $pdfInspector,
        BackofficeAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'edition_number' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'edition_year' => ['required', 'integer', 'min:2000', 'max:65535'],
            'publish_date' => ['required', 'date_format:Y-m-d'],
            'publish_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:publish_date'],
            'master_pdf' => ['required', 'file', 'mimes:pdf', 'max:'.config('epaper.max_pdf_kilobytes')],
            'cover' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4000,max_height=4000', 'max:10240'],
        ]);

        $this->validateEditionDates($validated['edition_year'], $validated['publish_date'], $validated['publish_end_date']);
        $this->ensureEditionNumberIsUnique($validated['edition_year'], $validated['edition_number']);
        $this->ensurePrivateStorageDisk();

        /** @var UploadedFile $masterPdf */
        $masterPdf = $request->file('master_pdf');
        $pageCount = $pdfInspector->pageCount($masterPdf->getRealPath());
        $checksum = hash_file('sha256', $masterPdf->getRealPath());
        $fileSize = $masterPdf->getSize();
        if ($checksum === false || $fileSize === false) {
            throw new RuntimeException('Unable to read uploaded E-Paper file metadata.');
        }

        $disk = Storage::disk(config('epaper.storage_disk'));
        $storedPaths = [];

        try {
            $masterPath = 'epaper/masters/'.bin2hex(random_bytes(16)).'.pdf';
            $storedPaths[] = $masterPath;
            if (! $disk->putFileAs(dirname($masterPath), $masterPdf, basename($masterPath))) {
                throw new RuntimeException('Unable to store the E-Paper master PDF.');
            }

            $coverPath = null;
            if ($request->hasFile('cover')) {
                $coverPath = 'epaper/covers/'.bin2hex(random_bytes(16)).'.jpg';
                $storedPaths[] = $coverPath;
                if (! $disk->put($coverPath, $this->createCoverThumbnail($request->file('cover')))) {
                    throw new RuntimeException('Unable to store the E-Paper cover thumbnail.');
                }
            }

            /** @var User $actor */
            $actor = $request->user();
            $edition = DB::transaction(function () use (
                $validated,
                $masterPath,
                $checksum,
                $fileSize,
                $coverPath,
                $pageCount,
                $actor,
                $request,
                $audit,
            ): EpaperEdition {
                $edition = EpaperEdition::query()->create([
                    'edition_number' => $validated['edition_number'],
                    'edition_year' => $validated['edition_year'],
                    'publish_date' => $validated['publish_date'],
                    'publish_end_date' => $validated['publish_end_date'],
                    'master_pdf_path' => $masterPath,
                    'checksum' => $checksum,
                    'file_size' => $fileSize,
                    'cover_path' => $coverPath,
                    'page_count' => $pageCount,
                    'status' => EditionStatus::Draft,
                ]);

                $audit->record($actor, $request, 'epaper_edition.created', 'epaper_edition', $edition->id, [
                    'edition_number' => $edition->edition_number,
                    'edition_year' => $edition->edition_year,
                    'status' => $edition->status->value,
                ]);

                return $edition;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $disk->delete($path);
            }

            throw $exception;
        }

        return response()->json([
            'data' => EpaperEditionResource::make($edition)->resolve($request),
        ], 201);
    }

    public function show(Request $request, EpaperEdition $edition): JsonResponse
    {
        return response()->json([
            'data' => EpaperEditionResource::make($edition)->resolve($request),
        ]);
    }

    public function update(
        Request $request,
        EpaperEdition $edition,
        BackofficeAuditService $audit,
    ): JsonResponse {
        if ($edition->status !== EditionStatus::Draft) {
            return $this->conflict('published_edition_locked', 'Edisi terbit tidak dapat diubah.');
        }

        $validated = $request->validate([
            'edition_number' => ['sometimes', 'required', 'integer', 'min:1', 'max:4294967295'],
            'edition_year' => ['sometimes', 'required', 'integer', 'min:2000', 'max:65535'],
            'publish_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'publish_end_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        $attributes = array_merge([
            'edition_number' => $edition->edition_number,
            'edition_year' => $edition->edition_year,
            'publish_date' => $edition->publish_date->toDateString(),
            'publish_end_date' => $edition->publish_end_date->toDateString(),
        ], $validated);

        $this->validateEditionDates(
            (int) $attributes['edition_year'],
            $attributes['publish_date'],
            $attributes['publish_end_date']
        );
        $this->ensureEditionNumberIsUnique(
            (int) $attributes['edition_year'],
            (int) $attributes['edition_number'],
            $edition->id
        );

        /** @var User $actor */
        $actor = $request->user();
        $result = DB::transaction(function () use ($edition, $validated, $actor, $request, $audit): EpaperEdition|JsonResponse {
            $lockedEdition = EpaperEdition::query()->whereKey($edition->id)->lockForUpdate()->firstOrFail();
            if ($lockedEdition->status !== EditionStatus::Draft) {
                return $this->conflict('published_edition_locked', 'Edisi terbit tidak dapat diubah.');
            }

            $before = [
                'edition_number' => $lockedEdition->edition_number,
                'edition_year' => $lockedEdition->edition_year,
                'publish_date' => $lockedEdition->publish_date->toDateString(),
                'publish_end_date' => $lockedEdition->publish_end_date->toDateString(),
            ];
            $lockedEdition->fill($validated)->save();
            $lockedEdition->refresh();

            $audit->record($actor, $request, 'epaper_edition.updated', 'epaper_edition', $lockedEdition->id, [
                'before' => $before,
                'after' => [
                    'edition_number' => $lockedEdition->edition_number,
                    'edition_year' => $lockedEdition->edition_year,
                    'publish_date' => $lockedEdition->publish_date->toDateString(),
                    'publish_end_date' => $lockedEdition->publish_end_date->toDateString(),
                ],
            ]);

            return $lockedEdition;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'data' => EpaperEditionResource::make($result)->resolve($request),
        ]);
    }

    public function publish(
        Request $request,
        EpaperEdition $edition,
        BackofficeAuditService $audit,
    ): JsonResponse {
        if ($edition->status === EditionStatus::Published) {
            return response()->json([
                'data' => EpaperEditionResource::make($edition)->resolve($request),
            ]);
        }

        $this->ensurePrivateStorageDisk();
        $disk = Storage::disk(config('epaper.storage_disk'));
        if (! $disk->exists($edition->master_pdf_path)
            || ! $edition->checksum
            || $edition->page_count < 1) {
            return $this->conflict('edition_ingestion_incomplete', 'File master edisi belum siap diterbitkan.');
        }

        $stream = $disk->readStream($edition->master_pdf_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read the stored E-Paper master PDF.');
        }

        $hash = hash_init('sha256');
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                fclose($stream);
                throw new RuntimeException('Unable to verify the stored E-Paper master PDF.');
            }
            hash_update($hash, $chunk);
        }
        fclose($stream);

        if (! hash_equals($edition->checksum, hash_final($hash))) {
            return $this->conflict('master_checksum_mismatch', 'Checksum file master tidak sesuai; edisi tidak dapat diterbitkan.');
        }

        /** @var User $actor */
        $actor = $request->user();
        $publishedEdition = DB::transaction(function () use ($edition, $actor, $request, $audit): EpaperEdition {
            $lockedEdition = EpaperEdition::query()->whereKey($edition->id)->lockForUpdate()->firstOrFail();
            if ($lockedEdition->status === EditionStatus::Published) {
                return $lockedEdition;
            }

            $lockedEdition->status = EditionStatus::Published;
            $lockedEdition->save();
            $lockedEdition->refresh();
            $audit->record($actor, $request, 'epaper_edition.published', 'epaper_edition', $lockedEdition->id, [
                'edition_number' => $lockedEdition->edition_number,
                'edition_year' => $lockedEdition->edition_year,
            ]);

            return $lockedEdition;
        });

        return response()->json([
            'data' => EpaperEditionResource::make($publishedEdition)->resolve($request),
        ]);
    }

    private function validateEditionDates(int $year, string $publishDate, string $publishEndDate): void
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $publishDate);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $publishEndDate);
        $oneDay = $start->equalTo($end);
        $weekend = $start->isSaturday() && $end->equalTo($start->addDay()) && $end->isSunday();

        if ((int) $start->format('Y') !== $year || (! $oneDay && ! $weekend)) {
            throw ValidationException::withMessages([
                'publish_end_date' => ['Tanggal edisi harus satu hari atau rentang Sabtu-Minggu, dan tahun harus sama dengan edition_year.'],
            ]);
        }
    }

    private function ensureEditionNumberIsUnique(int $year, int $number, ?int $ignoreId = null): void
    {
        $exists = EpaperEdition::query()
            ->where('edition_year', $year)
            ->where('edition_number', $number)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'edition_number' => ['Nomor edisi sudah digunakan pada tahun tersebut.'],
            ]);
        }
    }

    private function ensurePrivateStorageDisk(): void
    {
        $disk = config('epaper.storage_disk');
        $visibility = config("filesystems.disks.{$disk}.visibility");

        if (! is_string($disk) || $disk === '' || $disk === 'public' || $visibility === 'public') {
            throw new RuntimeException('E-Paper storage must use a private filesystem disk.');
        }
    }

    private function createCoverThumbnail(UploadedFile $cover): string
    {
        $source = imagecreatefromstring(file_get_contents($cover->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages([
                'cover' => ['Gambar cover tidak dapat dibaca.'],
            ]);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(480 / $sourceWidth, 680 / $sourceHeight, 1);
        $width = max(1, (int) floor($sourceWidth * $scale));
        $height = max(1, (int) floor($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($thumbnail, 255, 255, 255);
        imagefill($thumbnail, 0, 0, $background);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($thumbnail, null, 85);
        $image = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumbnail);

        if (! is_string($image) || $image === '') {
            throw new RuntimeException('Unable to encode the E-Paper cover thumbnail.');
        }

        return $image;
    }

    private function conflict(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 409);
    }
}
