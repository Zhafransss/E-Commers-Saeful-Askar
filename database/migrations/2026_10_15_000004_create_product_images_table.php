<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Image shown when this variant is selected; exposed as `variant_id`.
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('jubelio_image_id')->nullable();
            $table->string('url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->unique(['product_id', 'jubelio_image_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
