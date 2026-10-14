<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Jubelio item group.
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jubelio_item_group_id')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // Stays the same when the name changes in Jubelio (CAT-03).
            $table->string('slug', 270)->unique();
            $table->string('brand_name', 100)->nullable();
            $table->longText('description_html')->nullable();
            $table->string('sell_unit', 30)->nullable();
            $table->unsignedSmallInteger('min_qty')->nullable();
            $table->unsignedSmallInteger('max_qty')->nullable();
            // Fallback when a variant has no weight (INT-JB-05).
            $table->unsignedInteger('package_weight')->nullable();
            $table->unsignedSmallInteger('package_length')->nullable();
            $table->unsignedSmallInteger('package_width')->nullable();
            $table->unsignedSmallInteger('package_height')->nullable();
            $table->json('variations')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('jubelio_last_modified')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'jubelio_last_modified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
