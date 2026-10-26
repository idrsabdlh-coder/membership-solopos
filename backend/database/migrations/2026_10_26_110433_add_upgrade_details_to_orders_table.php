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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('upgrade_credit')->default(0)->after('discount');
            $table->foreignId('upgrade_from_subscription_id')
                ->nullable()
                ->after('upgrade_credit')
                ->constrained('subscriptions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['upgrade_from_subscription_id']);
            $table->dropColumn(['upgrade_credit', 'upgrade_from_subscription_id']);
        });
    }
};
