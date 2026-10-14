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
    Schema::create('clippings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
        $table->string('article_ref');
        $table->string('title');
        $table->text('url');
        $table->timestamps();

        $table->unique(['user_id', 'article_ref']);
    });
}
};
