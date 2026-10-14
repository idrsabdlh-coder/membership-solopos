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
    Schema::create('plans', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->string('name');
        $table->string('billing_period');
        $table->unsignedSmallInteger('duration_days');
        $table->unsignedInteger('price');
        $table->unsignedSmallInteger('clipping_quota')->default(0);
        $table->unsignedTinyInteger('download_limit_per_edition')->default(0);
        $table->unsignedTinyInteger('download_limit_per_day')->default(0);
        $table->boolean('is_recommended')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->unique(['type', 'billing_period']);
    });
}
};
