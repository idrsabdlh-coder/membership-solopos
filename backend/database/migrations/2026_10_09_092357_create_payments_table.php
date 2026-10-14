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
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->cascadeOnDelete();
        $table->string('gateway');
        $table->string('gateway_ref')->nullable();
        $table->string('method')->nullable();
        $table->unsignedInteger('amount');
        $table->string('status')->default('pending');
        $table->dateTime('paid_at')->nullable();
        $table->json('raw_payload')->nullable();
        $table->timestamps();

        $table->unique(['gateway', 'gateway_ref']); // dasar idempotensi webhook
    });
}
};
