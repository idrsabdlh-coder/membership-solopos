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
    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('type'); // percent | fixed
        $table->unsignedInteger('value');
        $table->unsignedInteger('quota')->nullable();
        $table->unsignedInteger('used')->default(0);
        $table->dateTime('valid_from')->nullable();
        $table->dateTime('valid_until')->nullable();
        $table->timestamps();
    });
}
};
