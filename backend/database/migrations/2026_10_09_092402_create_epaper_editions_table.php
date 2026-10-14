<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('epaper_editions', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('edition_number');
        $table->unsignedSmallInteger('edition_year');
        $table->date('publish_date');
        $table->date('publish_end_date'); // edisi harian: sama dengan publish_date
        $table->string('master_pdf_path');
        $table->string('checksum', 64)->nullable();
        $table->unsignedBigInteger('file_size')->default(0);
        $table->string('cover_path')->nullable();
        $table->unsignedSmallInteger('page_count')->default(0);
        $table->string('status')->default('draft');
        $table->timestamps();

        $table->unique(['edition_year', 'edition_number']);
        $table->index(['publish_date', 'publish_end_date']);
    });
}
};
