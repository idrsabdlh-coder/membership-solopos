<?php

namespace Tests\Feature;

use App\Enums\EditionStatus;
use App\Models\EpaperEdition;
use App\Models\User;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EpaperEditionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'epaper.admin_emails' => ['epaper-admin@example.test'],
            'epaper.storage_disk' => 'local',
            'epaper.max_pdf_kilobytes' => 102400,
        ]);
        Storage::fake('local');
    }

    public function test_management_api_requires_a_verified_allowlisted_admin(): void
    {
        $this->getJson('/api/admin/epaper/editions')->assertUnauthorized();

        $member = User::factory()->create();
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/admin/epaper/editions')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'epaper_admin_required');

        $unverifiedAdmin = User::factory()->unverified()->create([
            'email' => 'epaper-admin@example.test',
        ]);
        $this->actingAs($unverifiedAdmin, 'sanctum')
            ->getJson('/api/admin/epaper/editions')
            ->assertForbidden();
    }

    public function test_admin_can_ingest_private_pdf_and_cover_and_get_metadata(): void
    {
        $admin = $this->admin();
        $pdf = $this->pdfWithPages(2);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/admin/epaper/editions', [
                'edition_number' => 301,
                'edition_year' => 2026,
                'publish_date' => '2026-10-24',
                'publish_end_date' => '2026-10-25',
                'master_pdf' => $pdf,
                'cover' => UploadedFile::fake()->image('cover.png', 300, 400),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.edition_number', 301)
            ->assertJsonPath('data.edition_year', 2026)
            ->assertJsonPath('data.publish_date', '2026-10-24')
            ->assertJsonPath('data.publish_end_date', '2026-10-25')
            ->assertJsonPath('data.page_count', 2)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.has_cover', true)
            ->assertJsonMissingPath('data.master_pdf_path');

        $edition = EpaperEdition::query()->firstOrFail();
        $this->assertSame(hash('sha256', $pdf->getContent()), $edition->checksum);
        $this->assertSame(strlen($pdf->getContent()), $edition->file_size);
        Storage::disk('local')->assertExists($edition->master_pdf_path);
        Storage::disk('local')->assertExists($edition->cover_path);
    }

    public function test_invalid_or_corrupt_pdf_is_rejected_without_storing_an_edition(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->post('/api/admin/epaper/editions', [
                'edition_number' => 302,
                'edition_year' => 2026,
                'publish_date' => '2026-10-24',
                'publish_end_date' => '2026-10-25',
                'master_pdf' => UploadedFile::fake()->createWithContent('bad.pdf', '%PDF-1.4 corrupt'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('master_pdf');

        $this->assertDatabaseCount('epaper_editions', 0);
        Storage::disk('local')->assertDirectoryEmpty('epaper/masters');
    }

    public function test_edition_dates_must_be_one_day_or_a_saturday_sunday_pair_in_the_given_year(): void
    {
        $admin = $this->admin();

        foreach ([
            ['2026-10-23', '2026-10-24'],
            ['2026-10-24', '2026-10-26'],
            ['2025-10-24', '2025-10-25'],
        ] as [$start, $end]) {
            $this->actingAs($admin, 'sanctum')
                ->post('/api/admin/epaper/editions', [
                    'edition_number' => random_int(500, 900),
                    'edition_year' => 2026,
                    'publish_date' => $start,
                    'publish_end_date' => $end,
                    'master_pdf' => $this->pdfWithPages(1),
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('publish_end_date');
        }

        $this->assertDatabaseCount('epaper_editions', 0);
    }

    public function test_edition_number_must_be_unique_within_a_year(): void
    {
        $admin = $this->admin();
        $this->createDraft();

        $this->actingAs($admin, 'sanctum')
            ->post('/api/admin/epaper/editions', [
                'edition_number' => 301,
                'edition_year' => 2026,
                'publish_date' => '2026-10-24',
                'publish_end_date' => '2026-10-25',
                'master_pdf' => $this->pdfWithPages(1),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('edition_number');

        $this->assertDatabaseCount('epaper_editions', 1);
    }

    public function test_admin_can_update_draft_metadata_and_publish_an_ingested_edition(): void
    {
        $admin = $this->admin();
        $edition = $this->createDraft();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/epaper/editions/{$edition->id}", [
                'edition_number' => 303,
                'publish_date' => '2026-10-23',
                'publish_end_date' => '2026-10-23',
            ])
            ->assertOk()
            ->assertJsonPath('data.edition_number', 303)
            ->assertJsonPath('data.publish_date', '2026-10-23');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/epaper/editions/{$edition->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertSame(EditionStatus::Published, $edition->fresh()->status);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/epaper/editions/{$edition->id}", ['edition_number' => 304])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'published_edition_locked');
    }

    public function test_publish_is_rejected_when_the_master_file_is_missing(): void
    {
        $admin = $this->admin();
        $edition = $this->createDraft();
        Storage::disk('local')->delete($edition->master_pdf_path);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/epaper/editions/{$edition->id}/publish")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'edition_ingestion_incomplete');

        $this->assertSame(EditionStatus::Draft, $edition->fresh()->status);
    }

    public function test_publish_is_rejected_if_the_stored_master_checksum_changed(): void
    {
        $admin = $this->admin();
        $edition = $this->createDraft();
        Storage::disk('local')->put($edition->master_pdf_path, 'tampered file');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/epaper/editions/{$edition->id}/publish")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'master_checksum_mismatch');

        $this->assertSame(EditionStatus::Draft, $edition->fresh()->status);
    }

    public function test_admin_can_filter_and_paginate_editions(): void
    {
        $admin = $this->admin();
        $first = $this->createDraft();
        $this->createDraft([
            'edition_number' => 302,
            'publish_date' => '2026-10-23',
            'publish_end_date' => '2026-10-23',
        ]);
        $this->assertDatabaseCount('epaper_editions', 2);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/epaper/editions?status=draft&year=2026&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_even_an_allowlisted_admin_cannot_use_a_public_storage_disk(): void
    {
        config([
            'epaper.storage_disk' => 'public',
            'filesystems.disks.public.visibility' => 'public',
        ]);
        $this->actingAs($this->admin(), 'sanctum')
            ->post('/api/admin/epaper/editions', [
                'edition_number' => 301,
                'edition_year' => 2026,
                'publish_date' => '2026-10-24',
                'publish_end_date' => '2026-10-25',
                'master_pdf' => $this->pdfWithPages(1),
            ])
            ->assertServerError();

        $this->assertDatabaseCount('epaper_editions', 0);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'epaper-admin@example.test',
            'email_verified_at' => now(),
        ]);
    }

    private function createDraft(array $attributes = []): EpaperEdition
    {
        $pdf = $this->pdfWithPages(1);
        $path = 'epaper/masters/'.uniqid('test-', true).'.pdf';
        Storage::disk('local')->put($path, $pdf->getContent());

        return EpaperEdition::query()->create([
            'edition_number' => 301,
            'edition_year' => 2026,
            'publish_date' => '2026-10-24',
            'publish_end_date' => '2026-10-25',
            'master_pdf_path' => $path,
            'checksum' => hash('sha256', $pdf->getContent()),
            'file_size' => strlen($pdf->getContent()),
            'page_count' => 1,
            'status' => EditionStatus::Draft,
            ...$attributes,
        ]);
    }

    private function pdfWithPages(int $pageCount): UploadedFile
    {
        $pdf = new FPDF;
        for ($page = 0; $page < $pageCount; $page++) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(40, 10, 'Page '.($page + 1));
        }

        $path = tempnam(sys_get_temp_dir(), 'epaper-pdf-');
        $pdf->Output('F', $path);
        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('master.pdf', $contents);
    }
}
