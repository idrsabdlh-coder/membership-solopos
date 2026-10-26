<?php

namespace Tests\Feature;

use App\Enums\EditionStatus;
use App\Enums\EpaperDownloadStatus;
use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use App\Jobs\GenerateEpaperDownload;
use App\Models\EpaperDownload;
use App\Models\EpaperEdition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EpaperDownloadService;
use App\Services\EpaperPdfInspector;
use Carbon\CarbonImmutable;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EpaperReaderDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'epaper.storage_disk' => 'local',
            'epaper.signed_url_ttl_minutes' => 5,
            'epaper.default_download_limit_per_edition' => 3,
            'epaper.default_download_limit_per_day' => 10,
        ]);
        Storage::fake('local');
    }

    public function test_public_catalog_lists_only_published_editions_and_does_not_expose_private_paths(): void
    {
        $published = $this->edition();
        $this->edition(['edition_number' => 302, 'status' => EditionStatus::Draft]);

        $this->getJson('/api/epaper/editions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.access', 'locked')
            ->assertJsonMissingPath('data.0.master_pdf_path')
            ->assertJsonMissingPath('data.0.checksum');

        $this->getJson('/api/epaper/editions?to='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_page_urls_require_entitlement_for_an_overlapping_active_epaper_subscription(): void
    {
        $edition = $this->edition();
        $this->getJson("/api/epaper/editions/{$edition->id}/pages/1")->assertUnauthorized();

        $member = User::factory()->create();
        $this->subscription($member, [
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(2),
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/epaper/editions')
            ->assertJsonPath('data.0.access', 'unlocked');

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/editions/{$edition->id}/pages/1")
            ->assertOk()
            ->assertJsonPath('data.expires_in', 300);

        $url = $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/editions/{$edition->id}/pages/1")
            ->json('data.url');
        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
    }

    public function test_page_and_edition_access_are_rejected_when_unentitled_or_page_is_out_of_range(): void
    {
        $edition = $this->edition([
            'publish_date' => now()->subDays(3)->toDateString(),
            'publish_end_date' => now()->subDays(3)->toDateString(),
        ]);
        $member = User::factory()->create();
        $this->subscription($member, [
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/editions/{$edition->id}/pages/1")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'epaper_subscription_required');

        $currentEdition = $this->edition(['edition_number' => 302]);
        $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/editions/{$currentEdition->id}/pages/2")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    public function test_download_request_is_queued_and_records_request_metadata_before_generation(): void
    {
        Queue::fake();
        $edition = $this->edition();
        $member = User::factory()->create();
        $this->subscription($member);

        $this->actingAs($member, 'sanctum')
            ->withHeader('User-Agent', 'Epaper test browser')
            ->getJson("/api/epaper/editions/{$edition->id}/download")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.download_url', null)
            ->assertJsonPath('data.retry_after_seconds', 2);

        $download = EpaperDownload::query()->firstOrFail();
        $this->assertSame(32, strlen($download->watermark_hash));
        $this->assertSame('Epaper test browser', $download->user_agent);
        $this->assertNull($download->downloaded_at);
        $this->assertSame(EpaperDownloadStatus::Processing, $download->status);
        Queue::assertPushed(GenerateEpaperDownload::class, fn (GenerateEpaperDownload $job): bool => $job->downloadId === $download->id);
    }

    public function test_download_limit_is_enforced_per_edition_and_history_is_owner_scoped(): void
    {
        $edition = $this->edition();
        $member = User::factory()->create();
        $this->subscription($member);

        foreach (range(1, 3) as $index) {
            $this->recordDownload($member, $edition, $index);
        }

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/editions/{$edition->id}/download")
            ->assertConflict()
            ->assertJsonPath('error.code', 'download_limit_reached')
            ->assertJsonPath('meta.used_for_edition', 3);

        $otherMember = User::factory()->create();
        $this->actingAs($otherMember, 'sanctum')
            ->getJson('/api/epaper/downloads')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.quota.remaining_today', 0);
    }

    public function test_download_job_watermarks_each_page_and_signed_file_download_is_audited(): void
    {
        $edition = $this->edition(pages: 2);
        $member = User::factory()->create(['email' => 'member@example.test']);
        $subscription = $this->subscription($member);
        $download = EpaperDownload::query()->create([
            'user_id' => $member->id,
            'subscription_id' => $subscription->id,
            'edition_id' => $edition->id,
            'watermark_hash' => str_repeat('a', 32),
            'file_name' => 'solopos-epaper-301-'.now()->year.'.pdf',
            'generated_pdf_path' => 'epaper/generated/test.pdf',
            'status' => EpaperDownloadStatus::Processing,
            'requested_at' => now(),
            'downloaded_at' => null,
        ]);

        app(EpaperDownloadService::class)->generate($download);
        $download->refresh();
        $this->assertSame(EpaperDownloadStatus::Ready, $download->status);
        $temporaryPdf = $this->copyStoredFileToTemporaryPath($download->generated_pdf_path);
        try {
            $this->assertSame(2, app(EpaperPdfInspector::class)->pageCount($temporaryPdf));
        } finally {
            unlink($temporaryPdf);
        }
        $pdfContents = Storage::disk('local')->get($download->generated_pdf_path);
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfContents, $streams);
        $decodedStreams = array_filter(array_map(
            static fn (string $stream): string|false => gzuncompress($stream),
            $streams[1],
        ), 'is_string');
        $this->assertSame(2, substr_count(implode('', $decodedStreams), $download->watermark_hash));
        $this->assertSame(2, substr_count(implode('', $decodedStreams), 'member@example.test'));

        $status = $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/downloads/{$download->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');
        $fileUrl = $status->json('data.download_url');
        $this->assertStringContainsString('signature=', $fileUrl);
        $this->get($fileUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNotNull($download->fresh()->downloaded_at);
        $this->assertSame(EpaperDownloadStatus::Downloaded, $download->fresh()->status);

        $this->travel(6)->minutes();
        $this->get($fileUrl)->assertForbidden();
        $this->travelBack();
    }

    public function test_expired_generated_files_are_removed_without_deleting_audit_history(): void
    {
        $edition = $this->edition();
        $member = User::factory()->create();
        $subscription = $this->subscription($member);
        Storage::disk('local')->put('epaper/generated/expired.pdf', 'temporary');
        $download = $this->recordDownload($member, $edition, 1, [
            'subscription_id' => $subscription->id,
            'generated_pdf_path' => 'epaper/generated/expired.pdf',
            'expires_at' => now()->subMinute(),
            'status' => EpaperDownloadStatus::Downloaded,
            'downloaded_at' => now()->subMinutes(2),
        ]);

        $this->artisan('app:cleanup-expired-epaper-downloads')->assertSuccessful();

        Storage::disk('local')->assertMissing('epaper/generated/expired.pdf');
        $this->assertSame(EpaperDownloadStatus::Expired, $download->fresh()->status);
        $this->assertNull($download->fresh()->generated_pdf_path);
        $this->assertNotNull($download->fresh()->downloaded_at);
    }

    public function test_expired_download_reservations_and_next_day_requests_do_not_consume_today_quota(): void
    {
        $edition = $this->edition();
        $member = User::factory()->create();
        $this->subscription($member);
        $todayStart = CarbonImmutable::now('Asia/Jakarta')->startOfDay()->utc();
        $todayEnd = $todayStart->addDay();

        $this->recordDownload($member, $edition, 1, [
            'status' => EpaperDownloadStatus::Ready,
            'requested_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinute(),
        ]);
        $this->recordDownload($member, $edition, 2, [
            'requested_at' => $todayEnd,
        ]);

        $this->assertSame(0, app(EpaperDownloadService::class)->usedQuota($member, $todayStart, $todayEnd));
        Queue::fake();
        $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/editions/{$edition->id}/download")
            ->assertAccepted();
    }

    public function test_daily_download_limit_counts_successes_and_pending_reservations_across_editions(): void
    {
        $member = User::factory()->create();
        $this->subscription($member);
        $editions = [];
        for ($number = 301; $number <= 304; $number++) {
            $editions[] = $this->edition(['edition_number' => $number]);
        }

        for ($index = 1; $index <= 10; $index++) {
            $edition = $editions[($index - 1) % count($editions)];
            $this->recordDownload($member, $edition, $index);
        }

        $target = $this->edition(['edition_number' => 305]);
        Queue::fake();
        $this->actingAs($member, 'sanctum')
            ->getJson("/api/epaper/editions/{$target->id}/download")
            ->assertConflict()
            ->assertJsonPath('error.code', 'download_limit_reached')
            ->assertJsonPath('meta.used_today', 10);
    }

    public function test_private_files_are_not_served_from_a_public_storage_disk(): void
    {
        $edition = $this->edition(['cover_path' => 'epaper/covers/cover.jpg']);
        config([
            'epaper.storage_disk' => 'public',
            'filesystems.disks.public.visibility' => 'public',
        ]);

        $this->get("/api/epaper/editions/{$edition->id}/cover")->assertServerError();
    }

    private function edition(array $attributes = [], int $pages = 1): EpaperEdition
    {
        $pdf = new FPDF;
        foreach (range(1, $pages) as $page) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(40, 10, "Epaper page {$page}");
        }
        $content = $pdf->Output('S');
        $path = 'epaper/masters/'.bin2hex(random_bytes(8)).'.pdf';
        Storage::disk('local')->put($path, $content);
        $date = now()->toDateString();

        return EpaperEdition::query()->create([
            'edition_number' => 301,
            'edition_year' => now()->year,
            'publish_date' => $date,
            'publish_end_date' => $date,
            'master_pdf_path' => $path,
            'checksum' => hash('sha256', $content),
            'file_size' => strlen($content),
            'page_count' => $pages,
            'status' => EditionStatus::Published,
            ...$attributes,
        ]);
    }

    private function subscription(User $user, array $attributes = []): Subscription
    {
        $plan = new Plan;
        $plan->type = PlanType::Epaper;
        $plan->name = 'E-Paper';
        $plan->billing_period = 'monthly';
        $plan->duration_days = 30;
        $plan->price = 30000;
        $plan->download_limit_per_edition = 3;
        $plan->download_limit_per_day = 10;
        $plan->save();

        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            ...$attributes,
        ]);
    }

    private function recordDownload(User $user, EpaperEdition $edition, int $index, array $attributes = []): EpaperDownload
    {
        return EpaperDownload::query()->create([
            'user_id' => $user->id,
            'edition_id' => $edition->id,
            'watermark_hash' => str_pad(dechex($index), 32, '0', STR_PAD_LEFT),
            'file_name' => 'download-'.$index.'.pdf',
            'generated_pdf_path' => null,
            'status' => EpaperDownloadStatus::Processing,
            'requested_at' => now(),
            'downloaded_at' => null,
            ...$attributes,
        ]);
    }

    private function copyStoredFileToTemporaryPath(string $path): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'epaper-test-');
        $this->assertNotFalse($temporary);
        file_put_contents($temporary, Storage::disk('local')->get($path));

        return $temporary;
    }
}
