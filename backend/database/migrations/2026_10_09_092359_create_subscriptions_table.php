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
    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('plan_id')->constrained();
        $table->string('status')->default('pending');
        $table->dateTime('start_date')->nullable();
        $table->dateTime('end_date')->nullable();
        $table->foreignId('source_order_id')->nullable()->constrained('orders')->nullOnDelete();
        $table->timestamps();

        $table->index(['user_id', 'status', 'end_date']);
    });
}
};
