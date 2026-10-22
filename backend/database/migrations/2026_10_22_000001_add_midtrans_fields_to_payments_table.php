<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('merchant_order_id', 50)->nullable()->unique()->after('gateway_ref');
            $table->string('snap_token')->nullable()->after('merchant_order_id');
            $table->text('redirect_url')->nullable()->after('snap_token');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['merchant_order_id']);
            $table->dropColumn(['merchant_order_id', 'snap_token', 'redirect_url']);
        });
    }
};
