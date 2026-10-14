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
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_number')->unique();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('plan_id')->constrained();
        $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
        $table->unsignedInteger('subtotal');
        $table->unsignedInteger('discount')->default(0);
        $table->unsignedInteger('total');
        $table->string('status')->default('pending');
        $table->dateTime('expires_at');
        $table->timestamps();

        $table->index(['status', 'expires_at']);
    });
}
};
