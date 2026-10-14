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
    Schema::create('epaper_pages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('edition_id')->constrained('epaper_editions')->cascadeOnDelete();
        $table->unsignedSmallInteger('page_no');
        $table->string('file_path');
        $table->timestamps();

        $table->unique(['edition_id', 'page_no']);
    });
}
};
