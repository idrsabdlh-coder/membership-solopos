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
        Schema::table('epaper_downloads', function (Blueprint $table) {
            $table->string('status')->default('processing')->after('file_name');
            $table->string('generated_pdf_path')->nullable()->after('status');
            $table->timestamp('requested_at')->useCurrent()->after('user_agent');
            $table->timestamp('expires_at')->nullable()->after('downloaded_at');
            $table->timestamp('downloaded_at')->nullable()->default(null)->change();

            $table->index(['user_id', 'requested_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('epaper_downloads', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'requested_at']);
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn(['status', 'generated_pdf_path', 'requested_at', 'expires_at']);
            $table->timestamp('downloaded_at')->useCurrent()->nullable(false)->change();
        });
    }
};
