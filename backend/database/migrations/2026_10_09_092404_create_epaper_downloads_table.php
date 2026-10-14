<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('edition_id')->constrained('epaper_editions')->cascadeOnDelete();
            $table->char('watermark_hash', 32)->unique();
            $table->string('file_name');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at')->useCurrent();

            $table->index(['user_id', 'downloaded_at']);
            $table->index(['user_id', 'edition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_downloads');
    }
};